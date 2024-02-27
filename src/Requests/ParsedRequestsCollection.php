<?php

declare(strict_types=1);

namespace SimpleDep\Requests;

use RuntimeException;
use SimpleDep\DependencySorter\DependencySorter;
use SimpleDep\Package\Package;
use SimpleDep\Pool\Pool;
use SimpleDep\Solver\DependencyGatherer;
use SimpleDep\Solver\RequestCompatibilityChecker;
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
   * The pool of packages
   *
   * @var Pool|null
   */
  protected ?Pool $pool;

  /**
   * The installed packages.
   *
   * @var array<non-empty-string, array{
   *   version: Version|string,
   * }>
   */
  protected array $installed = [];

  /**
   * Create a new collection with the given environment.
   *
   * @param Pool $pool The pool of packages
   * @param array<non-empty-string, array{
   *   version: Version|string,
   * }> $installed The installed packages
   * @return static
   */
  public function setEnvironment(Pool $pool, array $installed = []): static {
    $new = clone $this;
    $new->pool = $pool;
    $new->installed = $installed;
    return $new;
  }

  /**
   * Sort the steps and return a new collection with the sorted steps. Needs the environment to be set first.
   *
   * @return static
   */
  public function sortSteps(): static {
    return (new DependencySorter(
      $this,
      $this->pool ?: new Pool(),
      $this->installed
    ))->sort();
  }

  /**
   * Install a package
   *
   * @param Package $package
   * @return $this
   */
  public function install(Package $package): static {
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
  public function uninstall(string $name): static {
    $this->requests[] = new ParsedRequest(Request::TYPE_UNINSTALL, $name);
    return $this;
  }

  /**
   * Add a request
   *
   * @param Request $request
   * @return $this
   */
  public function addRequest(Request $request): static {
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
  public function gatherDependencies(Pool $pool, array $installed = []): static {
    $new = clone $this;
    $gatherer = new DependencyGatherer($new, $pool, $installed);
    $gatherer->gatherDependencies();
    return $new;
  }

  /**
   * Check the compatibility of the requests.
   *
   * @return bool True if the requests are compatible, false otherwise.
   */
  public function isValid(): bool {
    return (new RequestCompatibilityChecker($this, $this->installed))->check();
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
