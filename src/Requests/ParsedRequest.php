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
   * The requests that require this.
   *
   * @var ParsedRequest[]
   */
  protected array $requiredBy = [];

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
  public function setPackageId(?int $packageId) {
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
  public function setVersion(?Version $version) {
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
   * Set the requests that require this.
   *
   * @param ParsedRequest[] $requiredBy
   * @return $this
   */
  public function setRequiredBy(array $requiredBy) {
    $this->requiredBy = $requiredBy;
    return $this;
  }

  /**
   * Get the requests that require this.
   *
   * @return ParsedRequest[] $requiredBy
   */
  public function getRequiredBy(): array {
    return $this->requiredBy;
  }

  /**
   * Create a new request from a package version.
   *
   * @param Package $package
   * @return static
   */
  public static function install(Package $package) {
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
   * @param bool $includeRequiredBy Whether to include the requiredBy field
   * @return array{
   *   type: Request::TYPE_*,
   *   name: non-empty-string,
   *   packageId: int|null,
   *   versionConstraint: string|null,
   *   requiredBy?: array<non-empty-string, array{
   *     type: Request::TYPE_*,
   *     name: non-empty-string,
   *     versionConstraint: string|null,
   *   }>,
   * }
   */
  public function toArray(bool $includeRequiredBy = true): array {
    $result = array_merge(parent::toArray(), [
      'packageId' => $this->packageId,
      'version' => $this->version ? (string) $this->version : null,
    ]);

    if ($includeRequiredBy) {
      $result['requiredBy'] = array_map(
        static fn(ParsedRequest $request) => $request->toArray(false),
        $this->requiredBy
      );
    }

    return $result;
  }
}
