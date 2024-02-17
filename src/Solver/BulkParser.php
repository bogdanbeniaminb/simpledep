<?php

declare(strict_types=1);

namespace SimpleDep\Solver;

use RuntimeException;
use SimpleDep\DependencySorter\DependencySorter;
use SimpleDep\Pool\Pool;
use SimpleDep\Requests\ParsedRequest;
use SimpleDep\Requests\ParsedRequestsCollection;
use SimpleDep\Requests\Request;
use SimpleDep\Requests\RequestsCollection;
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
   * @param Pool $pool The pool of packages
   * @param RequestsCollection $requests The requests
   * @param array<non-empty-string, array{
   *   version: Version|string,
   * }> $installed The installed packages
   */
  public function __construct(
    Pool $pool,
    RequestsCollection $requests,
    array $installed = []
  ) {
    $this->pool = $pool;
    $this->pool->ensurePackageIds();
    $this->installed = $installed;
    $this->requests = $requests;
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
   * Parse the dependencies
   *
   * @return ParsedRequestsCollection[]
   * @throws ParserException
   */
  public function parse(): array {
    $solutions = $this->parseRequests($this->requests);
    $solutions = array_filter($solutions, function (ParsedRequestsCollection $solution) {
      return (new RequestCompatibilityChecker($solution, $this->installed))->check();
    });

    if (!count($solutions) && $this->throwExceptions) {
      throw ParserException::noSolution();
    }

    // Sort the solution steps by their dependencies.
    $solutions = array_map(
      fn(ParsedRequestsCollection $solution) => (new DependencySorter(
        $solution,
        $this->pool,
        $this->installed
      ))->sort(),
      $solutions
    );

    return array_values($solutions);
  }

  /**
   * Parse the requests, solving the dependencies.
   * Uses backtracking to solve the dependencies.
   *
   * @param RequestsCollection $requests
   * @return ParsedRequestsCollection[] Possible solutions.
   */
  protected function parseRequests(RequestsCollection $requests): array {
    if (!count($requests)) {
      return [];
    }

    /** @var ParsedRequest $firstRequest */
    $firstRequest = $requests->first();

    $currentSolutions = $this->parseRequest($firstRequest);
    $next = $this->parseRequests($requests->slice(1));
    if (!count($next)) {
      return $currentSolutions;
    }

    $result = [];
    foreach ($currentSolutions as $currentSolution) {
      foreach ($next as $nextSolution) {
        $result[] = $currentSolution->merge($nextSolution);
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
   * @return ParsedRequestsCollection[]
   * @throws ParserException|RuntimeException
   */
  protected function parseInstallRequest(
    string $name,
    ?Constraint $versionConstraint = null
  ): array {
    $packages = $this->pool->getPackageByConstraint($name, $versionConstraint);
    if (!count($packages) && $this->throwExceptions) {
      throw ParserException::packageVersionNotFound($name, $versionConstraint);
    }

    $solutions = [];
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

          if (
            $link['type'] === 'conflict' ||
            $link['type'] === 'provide' ||
            $link['type'] === 'replace'
          ) {
            $linkRequests->uninstall($link['name']);
          }
        }

        // If there are no link requests, we can return the current requests.
        if (!count($linkRequests)) {
          $solutions[] = $requests;
          continue;
        }

        $linkSolutions = (new self($this->pool, $linkRequests, $this->installed))
          ->setThrowExceptions(false)
          ->parse();
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

    return $this->parseInstallRequest($name, $versionConstraint);
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
}
