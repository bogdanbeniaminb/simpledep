<?php

declare(strict_types=1);

namespace SimpleDep\Requests;

use z4kn4fein\SemVer\Constraints\Constraint;

/**
 * @extends GenericRequestsCollection<Request>
 */
class RequestsCollection extends GenericRequestsCollection {
  /**
   * Install a package
   *
   * @param non-empty-string $name
   * @param Constraint|string|null $versionConstraint
   * @return $this
   */
  public function install(string $name, $versionConstraint = null): static {
    if (!$versionConstraint) {
      $versionConstraint = Constraint::default();
    } elseif (is_string($versionConstraint)) {
      $versionConstraint = Constraint::parse($versionConstraint);
    }

    $this->requests[] = new Request(Request::TYPE_INSTALL, $name, $versionConstraint);
    return $this;
  }

  /**
   * Update a package
   *
   * @param non-empty-string $name
   * @param Constraint|string|null $versionConstraint
   * @return $this
   */
  public function update(string $name, $versionConstraint = null): static {
    if (is_string($versionConstraint)) {
      $versionConstraint = Constraint::parse($versionConstraint);
    }

    $this->requests[] = new Request(Request::TYPE_UPDATE, $name, $versionConstraint);
    return $this;
  }

  /**
   * Uninstall a package
   *
   * @param non-empty-string $name
   * @return $this
   */
  public function uninstall(string $name): static {
    $this->requests[] = new Request(Request::TYPE_UNINSTALL, $name);
    return $this;
  }
}
