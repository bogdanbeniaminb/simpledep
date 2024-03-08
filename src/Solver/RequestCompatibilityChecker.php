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
   * Check the compatibility of the requests.
   *
   * @return bool
   */
  public function check(): bool {
    try {
      $this->validate();
      return true;
    } catch (IncompatiblePackageRequestsException $e) {
      return false;
    }
  }

  /**
   * Validate the compatibility of the requests.
   *
   * @return bool
   * @throws IncompatiblePackageRequestsException
   */
  public function validate(): bool {
    // Group the requests by name.
    $requests = $this->requests->getRequests();
    $groupedRequests = [];
    foreach ($requests as $request) {
      $groupedRequests[$request->getName()] ??= [];
      $groupedRequests[$request->getName()][] = $request;
    }

    // Check the compatibility of each group of requests.
    foreach ($groupedRequests as $requests) {
      $this->checkGroup($requests);
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
    $types = array_map(
      static fn(ParsedRequest $request) => $request->getType(),
      $requests
    );
    $packageIds = array_map(
      static fn(ParsedRequest $request) => $request->getPackageId(),
      $requests
    );

    // If the requests are not of the same type, they are not compatible.
    if (count(array_unique($types)) !== 1) {
      throw IncompatiblePackageRequestsException::incompatibleActions(
        $requests,
        $this->pool,
        $this->installed
      );
    }

    // To be compatible: all the requests must be of the same type and same package ID.
    if (count(array_unique($packageIds)) > 1) {
      throw IncompatiblePackageRequestsException::incompatibleVersions(
        $requests,
        $this->pool,
        $this->installed
      );
    }

    return true;
  }

  /**
   * Parse the packages that require the given package and version.
   *
   * @param ParsedRequest $request The request to check
   * @return array<array{
   *   package: non-empty-string,
   *   constraint: string|null,
   *   type: key-of<Package::SUPPORTED_LINK_TYPES>,
   * }>
   */
  protected function parseRequiredBy(ParsedRequest $request): array {
    $requiredBy = $request->getRequiredBy();
    $results = [];

    foreach ($requiredBy as $request) {
      $packageId = $request->getPackageId();
      $package = $this->pool->getPackageById($packageId);
      if (!$package) {
        continue;
      }

      $link = $package->getLinkByName($package->getName());
      if (!$link) {
        continue;
      }

      // Check if the link is compatible with the request for uninstalling the package.
      if (
        in_array($link['type'], [
          Link::TYPE_CONFLICT,
          Link::TYPE_PROVIDE,
          Link::TYPE_REPLACE,
        ]) &&
        $request->getType() !== ParsedRequest::TYPE_UNINSTALL
      ) {
        continue;
      }

      // Check if the link is compatible with the request for installing the package.
      if (
        in_array($link['type'], [Link::TYPE_REQUIRE, Link::TYPE_REQUIRE_DEV]) &&
        $request->getType() !== ParsedRequest::TYPE_INSTALL
      ) {
        continue;
      }

      // If the link has a version constraint, check if it is compatible.
      $constraint = $link['versionConstraint'] ?? null;
      $version = $request->getVersion();
      if ($constraint && (!$version || !$constraint->isSatisfiedBy($version))) {
        continue;
      }

      $results[] = [
        'package' => $package->getName(),
        'constraint' => ((string) $link['versionConstraint']) ?: null,
        'type' => $link['type'],
      ];
    }

    return $results;
  }
}
