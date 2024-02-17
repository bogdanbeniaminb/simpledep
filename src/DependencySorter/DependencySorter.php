<?php

declare(strict_types=1);

namespace SimpleDep\DependencySorter;

use Closure;
use SimpleDep\DependencySorter\Exceptions\DependencyException;
use SimpleDep\Pool\Pool;
use SimpleDep\Requests\ParsedRequest;
use SimpleDep\Requests\ParsedRequestsCollection;
use z4kn4fein\SemVer\Constraints\Constraint;
use z4kn4fein\SemVer\Version;

class DependencySorter {
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
   * @var ParsedRequestsCollection
   */
  protected ParsedRequestsCollection $requests;

  /**
   * The requests with their info.
   *
   * @var array<array{
   *   id: int,
   *   request: ParsedRequest,
   *   dependencies: int[],
   * }>
   */
  protected array $requestsData = [];

  /**
   * @param ParsedRequestsCollection $requests The requests
   * @param Pool $pool The pool of packages
   * @param array<non-empty-string, array{
   *   version: Version|string,
   * }> $installed The installed packages
   */
  public function __construct(
    ParsedRequestsCollection $requests,
    Pool $pool,
    array $installed = []
  ) {
    $this->pool = $pool;
    $this->installed = $installed;
    $this->requests = $requests;
  }

  /**
   * Sort the requests in the order of their dependencies (the order in which they should be installed).
   * Both "requires" and "conflicts" are taken into account and the requests are sorted accordingly.
   *
   * @return ParsedRequestsCollection
   */
  public function sort(): ParsedRequestsCollection {
    // Make sure the requests lists doesn't contain any duplicates.
    $this->requests = $this->requests->unique();

    // Gather the requests data.
    $this->gatherRequestsData();

    // Sort the requests by their dependency tree.
    $this->sortRequests();

    // Create the new requests collection.
    $requests = new ParsedRequestsCollection();
    foreach ($this->requestsData as $requestData) {
      $requests->addRequest($requestData['request']);
    }

    return $requests;
  }

  /**
   * Gather the requests data.
   *
   * @return void
   */
  protected function gatherRequestsData(): void {
    // Generate the IDs for the requests.
    $i = 0;
    foreach ($this->requests as $request) {
      $this->requestsData[] = [
        'id' => ++$i,
        'request' => $request,
        'dependencies' => [],
      ];
    }

    // Gather the dependencies for each request.
    $this->requestsData = array_map(function ($requestData) {
      $requestData['dependencies'] = $this->gatherDependencies($requestData);
      return $requestData;
    }, $this->requestsData);
  }

  /**
   * Gather the dependencies for a request.
   *
   * @param array{
   *   id: int,
   *   request: ParsedRequest,
   *   dependencies: int[],
   * } $requestData The request data
   * @return int[] The dependencies
   * @throws DependencyException
   */
  protected function gatherDependencies(array $requestData): array {
    $request = $requestData['request'];
    if ($request->getType() === ParsedRequest::TYPE_UNINSTALL) {
      return [];
    }

    $packageId = $request->getPackageId();
    if (!$packageId) {
      throw new DependencyException('Package ID not found in the request');
    }

    $dependencies = [];
    $package = $this->pool->getPackageById($packageId);
    if ($package === null) {
      throw new DependencyException('Package not found in the pool');
    }

    // Go through the links.
    $links = $package->getLinks();
    foreach ($links as $link) {
      $linkVersionConstraint = $link['versionConstraint'] ?? null;

      if ($link['type'] === 'require') {
        $id = $this->gatherRequireDependency($link['name'], $linkVersionConstraint);
        if ($id) {
          $dependencies[] = $id;
        }
      } elseif (in_array($link['type'], ['conflict', 'replace'])) {
        $id = $this->getRequestId(
          $link['name'],
          ParsedRequest::TYPE_UNINSTALL,
          $linkVersionConstraint
        );
        if (!$id) {
          continue;
        }

        $dependencies[] = $id;
      } else {
        throw new DependencyException('Unsupported link type: ' . $link['type']);
      }
    }

    return $dependencies;
  }

  /**
   * Gather the request ID for a require dependency.
   *
   * @param non-empty-string $name The package name of the dependency
   * @param Constraint|null $versionConstraint The version constraint of the dependency
   * @return int|null
   */
  protected function gatherRequireDependency(
    string $name,
    ?Constraint $versionConstraint
  ): ?int {
    $id = $this->getRequestId($name, ParsedRequest::TYPE_INSTALL, $versionConstraint);
    if ($id) {
      return $id;
    }

    // Check if the package is installed.
    $installed = $this->installed[$name] ?? null;
    if (!$installed) {
      throw new DependencyException('Missing dependency: ' . $name);
    }

    // Check if the installed version satisfies the version constraint.
    if (
      $versionConstraint &&
      !$versionConstraint->isSatisfiedBy(Version::parse((string) $installed['version']))
    ) {
      throw new DependencyException(
        sprintf(
          'Installed version does not satisfy the version constraint: %s. Installed: %s, Required: %s',
          $name,
          (string) $installed['version'],
          (string) $versionConstraint
        )
      );
    }

    // The installed version satisfies the version constraint. Nothing more to do.
    return null;
  }

  /**
   * Get the request ID by the package name and version constraint.
   *
   * @param non-empty-string $name The package name
   * @param Constraint|null $versionConstraint The version constraint
   * @return int|null The request ID
   */
  protected function getRequestId(
    string $name,
    ?int $type = null,
    ?Constraint $versionConstraint = null
  ): ?int {
    foreach ($this->requestsData as $requestData) {
      $request = $requestData['request'];
      if ($request->getName() !== $name) {
        continue;
      }

      // Check the type of the request.
      if ($type !== null && $request->getType() !== $type) {
        continue;
      }

      // Check the version constraint.
      if (
        $versionConstraint !== null &&
        (!$request->getVersion() ||
          !$versionConstraint->isSatisfiedBy($request->getVersion()))
      ) {
        continue;
      }

      return $requestData['id'];
    }

    return null;
  }

  /**
   * Sort the requests by their dependency tree.
   *
   * @return void
   */
  protected function sortRequests(): void {
    $sorter = new SimpleDependencySorter($this->requestsData);
    $this->requestsData = $sorter->sort();
  }
}
