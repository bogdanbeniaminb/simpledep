<?php

declare(strict_types=1);

namespace SimpleDep\Requests;

use InvalidArgumentException;
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
   * Get a simple array of the requests, used for compatibile merging.
   *
   * @param ParsedRequest[] $requests The requests to use. If not provided, the collection's requests are used.
   * @return array<non-empty-string, non-empty-string>
   */
  protected function getRequestsForQuickComparison(?array $requests = null): array {
    $requests ??= $this->requests;

    $results = [];
    foreach ($requests as $request) {
      $value = $this->getQuickComparisonValue($request);
      $results[$request->getName()] = $value;
    }

    return $results;
  }

  /**
   * Get the quick comparison value for a request.
   *
   * @param ParsedRequest $request
   * @return non-empty-string
   */
  protected function getQuickComparisonValue(ParsedRequest $request): string {
    return $request->getType() . '|' . (string) $request->getVersionConstraint();
  }

  /**
   * Merge requests into the collection if they are compatible.
   *
   * @param static $otherRequests
   * @return static|null The new collection, or null if the requests are not compatible.
   */
  public function mergeIfCompatible($otherRequests): ?static {
    if (!($otherRequests instanceof static)) {
      throw new InvalidArgumentException(
        'The requests must be an instance of ' . static::class
      );
    }

    $quickCompare = $this->getRequestsForQuickComparison();
    $newRequests = $this->requests;

    foreach ($otherRequests->requests as $request) {
      $key = $request->getName();
      if (
        isset($quickCompare[$key]) &&
        $quickCompare[$key] !== $this->getQuickComparisonValue($request)
      ) {
        return null;
      }

      $newRequests[] = $request;
    }

    return static::copy($this, $newRequests);
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
