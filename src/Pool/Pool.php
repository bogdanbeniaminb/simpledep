<?php

declare(strict_types=1);

namespace SimpleDep\Pool;

use RuntimeException;
use SimpleDep\Package\Package;
use z4kn4fein\SemVer\Constraints\Constraint;
use z4kn4fein\SemVer\Version;

class Pool {
  /**
   * The pool of packages
   *
   * @var array<non-empty-string, Package[]>
   */
  protected array $packages = [];

  /**
   * The minimum fake package ID, for ensuring unique package IDs.
   *
   * @var int
   */
  protected int $minFakePackageId = 1000000;

  /**
   * Add a package to the pool
   *
   * @param Package $package
   * @return $this
   */
  public function addPackage(Package $package): static {
    $this->packages[$package->getName()] ??= [];
    $this->packages[$package->getName()][] = $package;
    return $this;
  }

  /**
   * Get a package from the pool
   *
   * @param non-empty-string $name
   * @return Package[]
   */
  public function getPackageVersions(string $name): array {
    $packages = $this->packages[$name] ?? [];
    return $this->sortPackages($packages);
  }

  /**
   * Sort packages by version, descending, in place.
   *
   * @param Package[] $packages
   * @return Package[]
   */
  protected function sortPackages(array $packages): array {
    usort($packages, static function (Package $a, Package $b) {
      return Version::compare($b->getVersion(), $a->getVersion());
    });

    return $packages;
  }

  /**
   * Get all packages from the pool
   *
   * @return Package[]
   */
  public function getPackages(): array {
    return array_merge(...array_values($this->packages));
  }

  /**
   * Check if the pool has a package by name
   *
   * @param non-empty-string $name
   * @return bool
   */
  public function hasPackage(string $name): bool {
    return !empty($this->packages[$name]);
  }

  /**
   * Get a package by name and version constraint
   *
   * @param non-empty-string $name
   * @param Constraint|string|null $constraint
   * @return Package[]
   * @throws RuntimeException
   */
  public function getPackageByConstraint(
    string $name,
    Constraint|string|null $constraint = null
  ): array {
    $packages = $this->getPackageVersions($name);

    if (!$constraint) {
      $constraint = Constraint::parseOrNull('*');
    }

    if (is_string($constraint)) {
      $constraint = Constraint::parseOrNull($constraint);
    }

    if (!$constraint) {
      throw new RuntimeException('Invalid constraint');
    }

    return array_values(
      array_filter(
        $packages,
        static fn(Package $package) => $constraint->isSatisfiedBy($package->getVersion())
      )
    );
  }

  /**
   * Get a package by name and version
   *
   * @param non-empty-string $name
   * @param Version|string $version
   * @return Package|null
   */
  public function getPackageByVersion(string $name, string|Version $version): ?Package {
    $packages = $this->getPackageByConstraint(
      $name,
      Constraint::parse((string) $version)
    );
    if (!empty($packages)) {
      return reset($packages);
    }

    return null;
  }

  /**
   * Get the latest package by name
   *
   * @param non-empty-string $name
   * @return Package|null
   */
  public function getLatestPackage(string $name): ?Package {
    $packages = $this->getPackageVersions($name);
    return reset($packages) ?: null;
  }

  /**
   * Ensure package IDs.
   * This is useful for ensuring that each package has a unique ID.
   *
   * @return $this
   */
  public function ensurePackageIds(): static {
    $id = $this->detectStartingFakePackageId();
    foreach ($this->getPackages() as $package) {
      if ($package->getId()) {
        continue;
      }

      $package->setId($id++);
    }

    return $this;
  }

  /**
   * Get the starting fake package ID.
   *
   * @return int
   */
  protected function detectStartingFakePackageId(): int {
    $packages = $this->getPackages();
    if (empty($packages)) {
      return $this->minFakePackageId;
    }

    $maxPackageId = max(
      array_values(
        array_map(static fn(Package $package) => $package->getId() ?: 0, $packages)
      )
    );

    $this->minFakePackageId = max($maxPackageId + 1, $this->minFakePackageId);

    return $this->minFakePackageId;
  }

  /**
   * Get the minimum fake package ID.
   *
   * @return int
   */
  public function getStartingFakePackageId(): int {
    return $this->minFakePackageId;
  }

  /**
   * Get a package by ID
   *
   * @param int|null $id
   * @return Package|null
   */
  public function getPackageById(?int $id): ?Package {
    if ($id === null) {
      return null;
    }

    foreach ($this->getPackages() as $package) {
      if ($package->getId() === $id) {
        return $package;
      }
    }

    return null;
  }
}
