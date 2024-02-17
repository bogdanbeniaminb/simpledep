<?php

declare(strict_types=1);

namespace SimpleDep\Requests;

use SimpleDep\Package\Package;
use z4kn4fein\SemVer\Constraints\Constraint;
use z4kn4fein\SemVer\Version;

class ParsedRequest extends Request {
  /**
   * The package ID
   *
   * @var int|null
   */
  protected ?int $packageId = null;

  /**
   * The version of the package to install.
   *
   * @var Version|null
   */
  protected ?Version $version = null;

  /**
   * Set the package ID
   *
   * @param int|null $packageId
   * @return $this
   */
  public function setPackageId(?int $packageId): static {
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
   * Set the version of the package to install.
   *
   * @param Version|null $version
   * @return $this
   */
  public function setVersion(?Version $version): static {
    $this->version = $version;
    return $this;
  }

  /**
   * Get the version of the package to install.
   *
   * @return Version|null
   */
  public function getVersion(): ?Version {
    return $this->version;
  }

  /**
   * Create a new request from a package version.
   *
   * @param Package $package
   * @return static
   */
  public static function install(Package $package): static {
    return (new static(
      Request::TYPE_INSTALL,
      $package->getName(),
      Constraint::parse((string) $package->getVersion())
    ))
      ->setPackageId($package->getId())
      ->setVersion($package->getVersion());
  }

  /**
   * Convert the request to an array
   *
   * @return array{
   *   type: Request::TYPE_*,
   *   name: non-empty-string,
   *   packageId: int|null,
   *   versionConstraint: string|null,
   * }
   */
  public function toArray(): array {
    return array_merge(parent::toArray(), [
      'packageId' => $this->packageId,
      'version' => $this->version ? (string) $this->version : null,
    ]);
  }
}
