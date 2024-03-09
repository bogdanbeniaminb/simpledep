<?php

declare(strict_types=1);

namespace SimpleDep\Solver;

use SimpleDep\DependencySorter\Exceptions\DependencyException;
use SimpleDep\Pool\Pool;
use SimpleDep\Requests\ParsedRequest;
use SimpleDep\Requests\ParsedRequestsCollection;
use SimpleDep\Utils\ObjectMap;
use z4kn4fein\SemVer\Constraints\Constraint;
use z4kn4fein\SemVer\Version;

class DependencyGatherer {
  /**
   * @var ParsedRequestsCollection
   */
  protected ParsedRequestsCollection $requests;
  /**
   * @var Pool
   */
  protected Pool $pool;
  /**
   * @var array<non-empty-string, array{version: (Version | string)}>
   */
  protected array $installed = [];
  /**
   * @param ParsedRequestsCollection $requests The requests
   * @param Pool $pool The pool of packages
   * @param array<non-empty-string, array{
   *   version: Version|string,
   * }> $installed The installed packages
   */
  public function __construct(ParsedRequestsCollection $requests, Pool $pool, array $installed = [])
  {
      $this->requests = $requests;
      $this->pool = $pool;
      $this->installed = $installed;
  }

  /**
   * Gather the dependencies of all requests.
   *
   * @return ParsedRequestsCollection The requests with their dependencies filled in.
   */
  public function gatherDependencies(): ParsedRequestsCollection {
    /** @var ObjectMap<ParsedRequest, ParsedRequest[]> $dependencies */
    $dependencies = new ObjectMap();

    // Gather the dependencies for each request in a WeakMap.
    foreach ($this->requests as $request) {
      $dependencies->set($request, $this->gatherRequestDependencies($request));
    }

    // Set the dependencies for each request.
    foreach ($this->requests as $request) {
      $dependers = [];
      foreach ($dependencies as $depender => $deps) {
        if (in_array($request, $deps)) {
          $dependers[] = $depender;
        }
      }

      $request->setRequiredBy($dependers);
    }

    return $this->requests;
  }

  /**
   * Gather the dependencies for a request.
   *
   * @param ParsedRequest $request The request
   * @return ParsedRequest[] The dependencies
   * @throws DependencyException
   */
  protected function gatherRequestDependencies(ParsedRequest $request): array {
    if ($request->getType() === ParsedRequest::TYPE_UNINSTALL) {
      return [];
    }

    $packageId = $request->getPackageId();
    if (!$packageId) {
      return [];
    }

    $dependencies = [];
    $package = $this->pool->getPackageById($packageId);
    if ($package === null) {
      return [];
    }

    // Go through the links.
    $links = $package->getLinks();
    foreach ($links as $link) {
      $linkVersionConstraint = $link['versionConstraint'] ?? null;

      if ($link['type'] === 'require') {
        $dependency = $this->getDependency($link['name'], $linkVersionConstraint);
        if ($dependency) {
          $dependencies[] = $dependency;
          $subDependencies = $this->gatherRequestDependencies($dependency);
          $dependencies = array_merge($dependencies, $subDependencies);
        }
      } elseif (in_array($link['type'], ['conflict', 'replace'])) {
        $dependency = $this->getRequest(
          $link['name'],
          ParsedRequest::TYPE_UNINSTALL,
          $linkVersionConstraint
        );
        if (!$dependency) {
          continue;
        }

        $dependencies[] = $dependency;
      }
    }

    return $dependencies;
  }

  /**
   * Get the request ID for a dependency.
   *
   * @param non-empty-string $name The package name of the dependency
   * @param Constraint|null $versionConstraint The version constraint of the dependency
   * @return ParsedRequest|null
   */
  protected function getDependency(
    string $name,
    ?Constraint $versionConstraint
  ): ?ParsedRequest {
    return $this->getRequest($name, ParsedRequest::TYPE_INSTALL, $versionConstraint);
  }

  /**
   * Get the request ID by the package name and version constraint.
   *
   * @param non-empty-string $name The package name
   * @param Constraint|null $versionConstraint The version constraint
   * @return ParsedRequest|null The request ID
   */
  protected function getRequest(
    string $name,
    ?int $type = null,
    ?Constraint $versionConstraint = null
  ): ?ParsedRequest {
    foreach ($this->requests as $request) {
      if ($request->getName() !== $name) {
        continue;
      }

      // Check the type of the request.
      if ($type !== null && $request->getType() !== $type) {
        continue;
      }

      $requestVersion = $request->getVersion();
      if ($type === ParsedRequest::TYPE_UNINSTALL && $requestVersion === null) {
        $requestVersion = Version::parseOrNull(
          (string) ($this->installed[$name]['version'] ?? '')
        );
      }

      // Check the version constraint.
      if (
        $versionConstraint !== null &&
        $requestVersion &&
        !$versionConstraint->isSatisfiedBy($requestVersion)
      ) {
        continue;
      }

      return $request;
    }

    return null;
  }
}
