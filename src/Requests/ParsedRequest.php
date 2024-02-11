<?php

declare(strict_types=1);

namespace SimpleDep\Requests;

use SimpleDep\Package\Package;
use z4kn4fein\SemVer\Constraints\Constraint;

class ParsedRequest extends Request {
  /**
   * The package ID
   *
   * @var int|null
   */
  protected ?int $packageId = null;

  /**
   * Set the package ID
   *
   * @param int $packageId
   * @return $this
   */
  public function setPackageId(int $packageId): static {
    $this->packageId = $packageId;
    return $this;
  }

  /**
   * Get the package ID
   *
   * @return int|null
   */
  public function getPackageId(): ?int {
    return $this->packageId;
  }

  /**
   * Create a new request from a package version.
   *
   * @param Request $request
   * @return static
   */
  public static function install(Package $package): static {
    return (new static(
      Request::TYPE_INSTALL,
      $package->getName(),
      Constraint::parse((string)$package->getVersion())
    ))
      ->setPackageId($package->getId());
  }

  /**
   * Convert the request to an array
   *
   * @return array{
   *   type: Request::TYPE_*,
   *   name: string,
   *   versionConstraint: string|null,
   * }
   */
  public function toArray(): array {
    return array_merge(parent::toArray(), [
      'packageId' => $this->packageId,
    ]);
  }
}
