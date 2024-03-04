<?php

declare(strict_types=1);

namespace SimpleDep\Requests;

use SimpleDep\DependencySorter\DependencySorter;
use SimpleDep\Pool\Pool;
use SimpleDep\Requests\Exceptions\IncompatiblePackageRequestsException;
use SimpleDep\Solver\RequestCompatibilityChecker;
use z4kn4fein\SemVer\Version;

/**
 * A collection of parsed requests
 */
class ValidatedParsedRequestsCollection extends ParsedRequestsCollection {
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
   * Create a new collection with the given requests.
   *
   * @param ParsedRequestsCollection $collection The collection of parsed requests
   * @param Pool $pool The pool of packages
   * @param array<non-empty-string, array{
   *  version: Version|string,
   * }> $installed The installed packages
   * @return static
   */
  public static function fromParsedRequestsCollection(
    ParsedRequestsCollection $collection,
    Pool $pool,
    array $installed = []
  ): static {
    $new = new static($collection->getRequests());
    $new->pool = $pool;
    $new->installed = $installed;
    return $new;
  }

  /**
   * Create a new collection with the given environment.
   *
   * @param Pool $pool The pool of packages
   * @param array<non-empty-string, array{
   *   version: Version|string,
   * }> $installed The installed packages
   * @return static
   */
  public function withEnvironment(Pool $pool, array $installed = []): static {
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
   * Check the compatibility of the requests.
   *
   * @return bool True if the requests are compatible, false otherwise.
   */
  public function isValid(): bool {
    try {
      (new RequestCompatibilityChecker(
        $this,
        $this->pool ?: new Pool(),
        $this->installed
      ))->validate();
      return true;
    } catch (IncompatiblePackageRequestsException) {
      return false;
    }
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
