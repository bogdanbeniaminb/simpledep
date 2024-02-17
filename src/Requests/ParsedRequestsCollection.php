<?php

declare(strict_types=1);

namespace SimpleDep\Requests;

use SimpleDep\Package\Package;
use Traversable;
use z4kn4fein\SemVer\Constraints\Constraint;

class ParsedRequestsCollection extends GenericRequestsCollection {
  /**
   * The pool of packages
   *
   * @var Request[]
   */
  protected array $requests = [];

  /**
   * Install a package
   *
   * @param non-empty-string $name
   * @param Constraint|string|null $versionConstraint
   * @return $this
   */
  public function install(Package $package): static {
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
   *   versionConstraint?: Constraint,
   * }>
   */
  public function toArray(): array {
    return array_map(static fn(Request $request) => $request->toArray(), $this->requests);
  }
}
