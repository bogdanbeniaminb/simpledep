<?php

declare(strict_types=1);

namespace SimpleDep\Solver;

use SimpleDep\Requests\ParsedRequest;
use SimpleDep\Requests\ParsedRequestsCollection;

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
   * @var array<non-empty-string, array{
   *   version: Version,
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
   * @var array<non-empty-string, array{
   *   version: Version,
   * }>
   * @return void
   */
  public function __construct(
    ParsedRequestsCollection $requests,
    array $installed = []
  ) {
    $this->requests = $requests;
    $this->installed = $installed;
  }

  /**
   * Check the compatibility of the requests.
   *
   * @return bool
   */
  public function check(): bool {
    // Group the requests by name.
    $requests = $this->requests->getRequests();
    $groupedRequests = [];
    foreach ($requests as $request) {
      $groupedRequests[$request->getName()] ??= [];
      $groupedRequests[$request->getName()][] = $request;
    }

    // Check the compatibility of each group of requests.
    foreach ($groupedRequests as $requests) {
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
   */
  protected function checkGroup(array $requests): bool {
    $types = array_map(static fn (ParsedRequest $request) => $request->getType(), $requests);
    $packageIds = array_map(static fn (ParsedRequest $request) => $request->getPackageId(), $requests);

    // To be compatible: all the requests must be of the same type and same package ID.
    if (count(array_unique($types)) !== 1 || count(array_unique($packageIds)) > 1) {
      return false;
    }

    return true;
  }
}
