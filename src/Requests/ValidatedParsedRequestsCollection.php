<?php

declare(strict_types=1);

namespace SimpleDep\Requests;

use SimpleDep\DependencySorter\DependencySorter;
use SimpleDep\Pool\Pool;
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
  ) {
    $new = new static($collection->getRequests());
    $new->pool = $pool;
    $new->installed = $installed;
    return $new;
  }

  /**
   * Sort the steps and return a new collection with the sorted steps. Needs the environment to be set first.
   *
   * @return static
   */
  public function sortSteps() {
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
    return (new RequestCompatibilityChecker(
      $this,
      $this->pool ?: new Pool(),
      $this->installed
    ))->check();
  }
}
