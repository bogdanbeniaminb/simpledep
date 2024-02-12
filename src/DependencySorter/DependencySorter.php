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
   *   version: Version,
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
   *   version: Version,
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
        'id' => $i,
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
   */
  protected function gatherDependencies(array $requestData): array {

    $request = $requestData['request'];
    if ($request->getType() === ParsedRequest::TYPE_UNINSTALL) {
      return [];
    }

    $dependencies = [];
    $package = $this->pool->getPackageById($request->getPackageId());
    if ($package === null) {
      throw new DependencyException('Package not found in the pool');
    }

    // Go through the links.
    $links = $package->getLinks();
    foreach ($links as $link) {
      if ($link['type'] === 'require') {
        $id = $this->getRequestId($link['name'], ParsedRequest::TYPE_INSTALL, $link['versionConstraint']);
        if (!$id) {
          // Check if the package is installed.
          $installed = $this->installed[$link['name']] ?? null;
          if (!$installed) {
            throw new DependencyException('Missing dependency: ' . $link['name']);
          }
        }

        $dependencies[] = $id;
      } elseif (in_array($link['type'], ['conflict', 'replace'])) {
        $id = $this->getRequestId($link['name'], ParsedRequest::TYPE_UNINSTALL, $link['versionConstraint']);
        if (!$id) {
          continue;
        }

        $dependencies[] = $id;
      }
    }

    return $dependencies;
  }

  /**
   * Get the request ID by the package name and version constraint.
   *
   * @param non-empty-string $name The package name
   * @param Constraint|null $versionConstraint The version constraint
   * @return int|null The request ID
   */
  protected function getRequestId(string $name, ?int $type = null, ?Constraint $versionConstraint = null): ?int {
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
      if ($versionConstraint !== null && (!$request->getVersion() || !$versionConstraint->isSatisfiedBy($request->getVersion()))) {
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
    usort($this->requestsData, Closure::fromCallable([$this, 'sortRequestsCallback']));
  }

  /**
   * Get the callback for sorting the requests.
   *
   * @param array{
   *   id: int,
   *   request: ParsedRequest,
   *   dependencies: int[],
   * } $a The first request data
   * @param array{
   *   id: int,
   *   request: ParsedRequest,
   *   dependencies: int[],
   * } $b The second request data
   * @return int
   */
  protected function sortRequestsCallback(array $a, array $b): int {
    if (in_array($a['id'], $b['dependencies'])) {
      return 1;
    }

    if (in_array($b['id'], $a['dependencies'])) {
      return -1;
    }

    return 0;
  }
}
