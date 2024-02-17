<?php

declare(strict_types=1);

namespace SimpleDep\Requests;

use RuntimeException;
use SimpleDep\Package\Package;
use Traversable;
use z4kn4fein\SemVer\Constraints\Constraint;

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
   * Convert the requests to an array
   *
   * @return array<non-empty-string, array{
   *   name: non-empty-string,
   *   packageId?: int|null,
   *   type: Request::TYPE_*,
   *   versionConstraint?: string|null,
   * }>
   */
  public function toArray(): array {
    return array_map(
      static fn(ParsedRequest $request) => $request->toArray(),
      $this->requests
    );
  }
}
