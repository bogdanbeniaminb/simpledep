<?php

declare(strict_types=1);

namespace SimpleDep\Solver;

use RuntimeException;
use SimpleDep\Pool\Pool;
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
   *   version: Version,
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
   * The requests after parsing.
   *
   * @var RequestsCollection
   */
  protected RequestsCollection $parsedRequests;

  /**
   * Whether to throw exceptions or not.
   *
   * @var bool
   */
  protected bool $throwExceptions = true;

  public function __construct(
    Pool $pool,
    RequestsCollection $requests,
    array $installed = []
  ) {
    $this->pool = $pool;
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
   * @return RequestsCollection[]
   * @throws ParserException
   */
  public function parse(): array {
    return $this->parseRequests($this->requests);
  }

  /**
   * Parse the requests, solving the dependencies.
   * Uses backtracking to solve the dependencies.
   *
   * @param RequestsCollection $requests
   * @return RequestsCollection[] Possible solutions.
   */
  protected function parseRequests(RequestsCollection $requests): array {
    if (!count($requests)) {
      return [];
    }

    $currentSolutions = $this->parseRequest($requests->first());
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
   * @return RequestsCollection[] Possible solutions.
   */
  protected function parseRequest(
    Request $request
  ): array {
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

    throw ParserException::invalidOperationType($type);
  }

  /**
   * Try to parse an install request.
   *
   * @param non-empty-string $name
   * @param Constraint|null $versionConstraint
   * @return RequestsCollection[]
   * @throws ParserException
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
        $requests = new RequestsCollection();
        $requests->install($name, Constraint::parse((string) $package->getVersion()));

        $links = $package->getLinks();
        $linkRequests = new RequestsCollection();
        foreach ($links as $link) {
          if ($link['type'] === 'require') {
            $linkRequests->install($link['name'], $link['versionConstraint']);
          }

          if ($link['type'] === 'conflict' || $link['type'] === 'provide' || $link['type'] === 'replace') {
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
   * @return RequestsCollection[]
   * @throws ParserException
   */
  protected function parseUpdateRequest(
    string $name,
    ?Constraint $versionConstraint = null
  ): array {
    $versionConstraint = $versionConstraint ?? Constraint::parseOrNull($this->installed[$name]['version'] ?? '*');
    return $this->parseInstallRequest($name, $versionConstraint);
  }

  /**
   * Try to parse an uninstall request.
   *
   * @param non-empty-string $name
   * @return RequestsCollection[]
   */
  protected function parseUninstallRequest(string $name): array {
    return [(new RequestsCollection())->uninstall($name)];
  }
}
