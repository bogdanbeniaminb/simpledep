<?php

declare(strict_types=1);

namespace SimpleDep\Solver;

use SimpleDep\Package\Link;
use SimpleDep\Package\Package;
use SimpleDep\Pool\Pool;
use SimpleDep\Requests\Exceptions\IncompatiblePackageRequestsException;
use SimpleDep\Requests\ParsedRequest;
use SimpleDep\Requests\ParsedRequestsCollection;
use z4kn4fein\SemVer\Version;

/**
 * Checks the compatibility between the requests in a collection and also the installed packages. In other words, it checks if the requests can be satisfied.
 */
class RequestCompatibilityChecker {
  /**
   * The requests.
   *
   * @var ParsedRequestsCollection
   */
  protected ParsedRequestsCollection $requests;

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
   * The grouped requests, by name of the package.
   *
   * @var array<non-empty-string, ParsedRequest[]>
   */
  protected array $groupedRequests = [];

  /**
   * @param ParsedRequestsCollection $requests
   * @param Pool $pool
   * @param array<non-empty-string, array{
   *   version: Version|string,
   * }> $installed
   */
  public function __construct(
    ParsedRequestsCollection $requests,
    Pool $pool,
    array $installed = []
  ) {
    $this->requests = $requests;
    $this->pool = $pool;
    $this->installed = $installed;
  }

  /**
   * Validate the compatibility of the requests.
   *
   * @return bool
   */
  public function check(): bool {
    // Group the requests by name.
    $requests = $this->requests->getRequests();
    if (count($requests) <= 1) {
      return true;
    }

    $groupedRequests = [];
    foreach ($requests as $request) {
      $groupedRequests[$request->getName()] ??= [];
      $groupedRequests[$request->getName()][] = $request;
    }

    // Check the compatibility of each group of requests.
    foreach ($groupedRequests as $requests) {
      if (count($requests) === 1) {
        continue;
      }

      if (!$this->checkGroup($requests)) {
        return false;
      }
    }

    return true;
  }

  /**
   * Check the compatibility of a group of requests.
   *
   * @param ParsedRequest[] $requests
   * @return bool
   * @throws IncompatiblePackageRequestsException
   */
  protected function checkGroup(array $requests): bool {
    // To be compatible: all the requests must be of the same type and same package ID.
    $packageIds = [];
    foreach ($requests as $request) {
      $packageId = $request->getPackageId();
      $packageIds[$packageId] = $packageId;
      if (count($packageIds) > 1) {
        return false;
      }
    }

    // If the requests are not of the same type, they are not compatible.
    $types = [];
    foreach ($requests as $request) {
      $type = $request->getType();
      $types[$type] = $type;
      if (count($types) > 1) {
        return false;
      }
    }

    return true;
  }
}
