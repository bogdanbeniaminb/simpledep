<?php

declare(strict_types=1);

namespace SimpleDep\Solver;

use Exception;
use RuntimeException;
use SimpleDep\Package\Package;
use SimpleDep\Pool\Pool;
use SimpleDep\Requests\ParsedRequest;
use SimpleDep\Requests\ParsedRequestsCollection;
use SimpleDep\Requests\Request;
use SimpleDep\Requests\RequestsCollection;
use SimpleDep\Requests\ValidatedParsedRequestsCollection;
use SimpleDep\Solver\Exceptions\ParserException;
use z4kn4fein\SemVer\Constraints\Constraint;
use z4kn4fein\SemVer\Version;

class BulkParser {
  /**
   * The pool of packages
   *
   * @var Pool
   */
  protected Pool $pool;

  /**
   * The pool of packages
   *
   * @var array<non-empty-string, array{
   *   version: Version|string,
   * }>
   */
  protected array $installed = [];

  /**
   * The requests.
   *
   * @var RequestsCollection
   */
  protected RequestsCollection $requests;

  /**
   * Whether to throw exceptions or not.
   *
   * @var bool
   */
  protected bool $throwExceptions = true;

  /**
   * Whether the current level is the first level of requests.
   *
   * @var bool
   */
  protected bool $isFirstLevel = true;

  /**
   * @param Pool $pool The pool of packages
   * @param RequestsCollection $requests The requests
   * @param array<non-empty-string, array{
   *   version: Version|string,
   * }> $installed The installed packages
   * @param bool $isFirstLevel Whether the current level is the first level of requests.
   */
  public function __construct(
    Pool $pool,
    RequestsCollection $requests,
    array $installed = [],
    bool $isFirstLevel = true
  ) {
    $this->installed = $installed;
    $this->pool = $pool;
    $this->isFirstLevel = $isFirstLevel;

    if ($this->isFirstLevel) {
      $this->addInstalledPackagesToPool();
    }

    $this->pool->ensurePackageIds();
    $this->requests = $requests;
  }

  /**
   * Add the installed packages to the pool, if they are not already there.
   *
   * @return void
   */
  protected function addInstalledPackagesToPool(): void {
    foreach ($this->installed as $name => $data) {
      if ($this->pool->getPackageByVersion($name, $data['version'])) {
        continue;
      }

      $package = new Package($name, $data['version']);
      $this->pool->addPackage($package);
    }
  }

  /**
   * Set whether to throw exceptions or not.
   *
   * @param bool $throwExceptions
   * @return static
   */
  public function setThrowExceptions(bool $throwExceptions): static {
    $this->throwExceptions = $throwExceptions;
    return $this;
  }

  /**
   * Parse the dependencies and return the valid solutions.
   *
   * @return ParsedRequestsCollection[]
   * @throws ParserException
   */
  public function parse(): array {
    $solutions = array_values(
      array_filter(
        $this->getAllSolutions(true),
        static fn(ValidatedParsedRequestsCollection $solution) => $solution->isValid()
      )
    );

    if (!count($solutions) && $this->throwExceptions) {
      throw ParserException::noSolution();
    }

    return $solutions;
  }

  /**
   * Parse the dependencies and return the solutions, valid or not.
   *
   * @param bool $validOnly Whether to return only the valid solutions.
   * @return ValidatedParsedRequestsCollection[]
   * @throws ParserException
   */
  public function getAllSolutions(bool $validOnly = false): array {
    $solutions = $this->parseRequests($this->requests, $validOnly);

    // Transform the solutions into ValidatedParsedRequestsCollection objects, with the environment set.
    $solutions = array_map(
      fn(
        ParsedRequestsCollection $solution
      ) => ValidatedParsedRequestsCollection::fromParsedRequestsCollection(
        $solution,
        $this->pool,
        $this->installed
      ),
      $solutions
    );

    // If we only want the valid solutions, filter them out, to avoid unnecessary operations.
    if ($validOnly) {
      $solutions = array_values(
        array_filter(
          $solutions,
          static fn(ValidatedParsedRequestsCollection $solution) => $solution->isValid()
        )
      );
    }

    // If it's the first level, do some extra operations.
    // For secondary levels, these operations are not only unnecessary, but actually harmful, because they can hide conflicts or other issues.
    // We need to apply them only to the first level, where we have the full set of requests.
    if ($this->isFirstLevel) {
      // Remove unnecessary requests from the solutions.
      $solutions = array_map(
        fn(
          ValidatedParsedRequestsCollection $solution
        ) => $this->removeUnnecessaryRequests($solution),
        $solutions
      );

      // Sort the steps.
      $solutions = array_map(
        fn(ValidatedParsedRequestsCollection $solution) => $solution->sortSteps(),
        $solutions
      );
    }

    return array_values($solutions);
  }

  /**
   * Parse the requests, solving the dependencies.
   * Uses backtracking to solve the dependencies.
   *
   * @param RequestsCollection $requests
   * @param bool $validOnly Whether to return only the valid solutions.
   * @return ParsedRequestsCollection[] Possible solutions.
   */
  protected function parseRequests(
    RequestsCollection $requests,
    bool $validOnly = true
  ): array {
    if (!count($requests)) {
      return [];
    }

    /** @var ParsedRequest $firstRequest */
    $firstRequest = $requests->first();

    $currentSolutions = $this->parseRequest($firstRequest);
    $next = $this->parseRequests($requests->slice(1), $validOnly);
    if (!count($next)) {
      return $currentSolutions;
    }

    $result = [];
    foreach ($currentSolutions as $currentSolution) {
      foreach ($next as $nextSolution) {
        if ($validOnly) {
          // Don't merge the solutions if they are not compatible.
          $merged = $currentSolution->mergeIfCompatible($nextSolution);
          if (!$merged) {
            continue;
          }
        } else {
          $merged = $currentSolution->merge($nextSolution);
        }

        $result[] = $merged;
      }
    }

    return $result;
  }

  /**
   * Try to parse a request.
   *
   * @param Request $request
   * @return ParsedRequestsCollection[] Possible solutions.
   */
  protected function parseRequest(Request $request): array {
    $name = $request->getName();
    $versionConstraint = $request->getVersionConstraint();
    $type = $request->getType();

    switch ($type) {
      case Request::TYPE_INSTALL:
        return $this->parseInstallRequest($name, $versionConstraint);
      case Request::TYPE_UPDATE:
        return $this->parseUpdateRequest($name, $versionConstraint);
      case Request::TYPE_UNINSTALL:
        return $this->parseUninstallRequest($name);
    }

    // @phpstan-ignore-next-line
    throw ParserException::invalidOperationType($type);
  }

  /**
   * Try to parse an install request.
   *
   * @param non-empty-string $name
   * @param Constraint|null $versionConstraint
   * @param bool $prioritizeInstalledVersion Whether to prioritize the installed version, if available.
   * @return ParsedRequestsCollection[]
   * @throws ParserException|RuntimeException
   */
  protected function parseInstallRequest(
    string $name,
    ?Constraint $versionConstraint = null,
    bool $prioritizeInstalledVersion = true
  ): array {
    $packages = $this->pool->getPackagesByConstraint($name, $versionConstraint);
    if (!count($packages) && $this->throwExceptions) {
      throw ParserException::packageVersionNotFound($name, $versionConstraint);
    }

    $solutions = [];

    // If the package is already installed, keep the installed version at the top.
    if ($prioritizeInstalledVersion && isset($this->installed[$name]['version'])) {
      $installedVersion = $this->installed[$name]['version'];
      usort($packages, static function (Package $a, Package $b) use ($installedVersion) {
        if ((string) $a->getVersion() === (string) $installedVersion) {
          return -1;
        }
        if ((string) $b->getVersion() === (string) $installedVersion) {
          return 1;
        }
        return 0;
      });
    }

    // Add solutions for each package.
    foreach ($packages as $package) {
      try {
        $requests = new ParsedRequestsCollection();
        $requests->install($package);

        $links = $package->getLinks();
        $linkRequests = new RequestsCollection();
        foreach ($links as $link) {
          if ($link['type'] === 'require') {
            $linkRequests->install($link['name'], $link['versionConstraint'] ?? '*');
          }

          if (in_array($link['type'], ['conflict', 'replace'])) {
            $linkRequests->uninstall($link['name']);
          }

          if ($link['type'] === 'provide') {
            // TODO: Go through the installed array and the packages array and uninstall the ones that provide the same package.
          }
        }

        // If there are no link requests, we can return the current requests.
        if (!count($linkRequests)) {
          $solutions[] = $requests;
          continue;
        }

        // Parse the second level of requests.
        $linksParser = new self($this->pool, $linkRequests, $this->installed, false);
        $linkSolutions = $linksParser->setThrowExceptions(false)->parse();
        foreach ($linkSolutions as $linkSolution) {
          $solutions[] = $requests->merge($linkSolution);
        }
      } catch (RuntimeException $e) {
        continue;
      }
    }

    if (!count($solutions) && $this->throwExceptions) {
      throw ParserException::noSolution();
    }

    return $solutions;
  }

  /**
   * Try to parse an update request.
   *
   * @param non-empty-string $name
   * @param Constraint|null $versionConstraint
   * @return ParsedRequestsCollection[]
   * @throws ParserException
   */
  protected function parseUpdateRequest(
    string $name,
    ?Constraint $versionConstraint = null
  ): array {
    if (!$versionConstraint) {
      $installedVersion = $this->installed[$name]['version'] ?? null;

      // If not installed, we can't update it.
      if (!$installedVersion) {
        if ($this->throwExceptions) {
          throw ParserException::packageNotInstalled($name);
        }
        return [];
      }

      // If installed, we can update it to the latest version compatible with the installed version.
      $versionConstraint =
        Constraint::parseOrNull('^' . $installedVersion) ?? Constraint::default();
    }

    return $this->parseInstallRequest($name, $versionConstraint, false);
  }

  /**
   * Try to parse an uninstall request.
   *
   * @param non-empty-string $name
   * @return ParsedRequestsCollection[]
   */
  protected function parseUninstallRequest(string $name): array {
    return [(new ParsedRequestsCollection())->uninstall($name)];
  }

  /**
   * Remove the unnecessary requests.
   *
   * @template TCollection of ParsedRequestsCollection
   * @param TCollection $solution
   * @return TCollection
   */
  protected function removeUnnecessaryRequests(
    ParsedRequestsCollection $solution
  ): ParsedRequestsCollection {
    return $solution->filter(
      fn(ParsedRequest $request) => !$this->isUnnecesaryRequest($request, $solution)
    );
  }

  /**
   * Check if a request is unnecessary.
   *
   * @param ParsedRequest $request
   * @param ParsedRequestsCollection $requests
   * @return bool True if the request is unnecessary, false otherwise.
   */
  protected function isUnnecesaryRequest(
    ParsedRequest $request,
    ParsedRequestsCollection $requests
  ): bool {
    // If the request is an uninstall request, check if the package is actually installed.
    if ($request->getType() === ParsedRequest::TYPE_UNINSTALL) {
      return !isset($this->installed[$request->getName()]);
    }

    // If the request is an install request, check if the package with the exact version is already installed.
    if ($request->getType() === ParsedRequest::TYPE_INSTALL) {
      $installedVersion = $this->installed[$request->getName()]['version'] ?? null;
      if (is_string($installedVersion)) {
        $installedVersion = Version::parse($installedVersion);
      }
      if ($installedVersion && $request->getVersion()) {
        return $installedVersion->isEqual($request->getVersion());
      }
    }

    return false;
  }
}
