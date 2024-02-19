<?php

declare(strict_types=1);

namespace SimpleDep\Requests;

use RuntimeException;
use SimpleDep\Package\Package;
use SimpleDep\Pool\Pool;
use SimpleDep\Solver\DependencyGatherer;
use Traversable;
use z4kn4fein\SemVer\Constraints\Constraint;
use z4kn4fein\SemVer\Version;

/**
 * A collection of parsed requests
 *
 * @extends GenericRequestsCollection<ParsedRequest>
 */
class ParsedRequestsCollection extends GenericRequestsCollection {
  /**
   * Install a package
   *
   * @param Package $package
   * @return $this
   */
  public function install(Package $package) {
    if (!$package->getId()) {
      throw new RuntimeException('The package must have an ID');
    }

    $this->requests[] = ParsedRequest::install($package);
    return $this;
  }

  /**
   * Uninstall a package
   *
   * @param non-empty-string $name
   * @return $this
   */
  public function uninstall(string $name) {
    $this->requests[] = new ParsedRequest(Request::TYPE_UNINSTALL, $name);
    return $this;
  }

  /**
   * Add a request
   *
   * @param Request $request
   * @return $this
   */
  public function addRequest(Request $request) {
    if (!($request instanceof ParsedRequest)) {
      $request = ParsedRequest::fromRequest($request);
    }

    $this->requests[] = $request;
    return $this;
  }

  public function __clone() {
    $this->requests = array_map(
      static fn(ParsedRequest $request) => clone $request,
      $this->requests
    );
  }

  /**
   * Gather the dependencies of all requests. Returns a new collection with the dependencies filled in.
   *
   * @param Pool $pool The pool of packages
   * @param array<non-empty-string, array{
   *  version: Version|string,
   * }> $installed The installed packages
   * @return static A new collection with the gathered dependencies.
   */
  public function gatherDependencies(Pool $pool, array $installed = []) {
    $new = clone $this;
    $gatherer = new DependencyGatherer($new, $pool, $installed);
    $gatherer->gatherDependencies();
    return $new;
  }

  /**
   * Convert the requests to an array
   *
   * @return array<non-empty-string, array{
   *   name: non-empty-string,
   *   packageId?: int|null,
   *   type: Request::TYPE_*,
   *   versionConstraint?: string|null,
   *   requiredBy?: array<non-empty-string, array{
   *     name: non-empty-string,
   *     packageId?: int|null,
   *     type: Request::TYPE_*,
   *     versionConstraint?: string|null,
   *   }>,
   * }>
   */
  public function toArray(): array {
    return array_map(
      static fn(ParsedRequest $request) => $request->toArray(),
      $this->requests
    );
  }
}
