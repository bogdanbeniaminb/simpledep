<?php

declare(strict_types=1);

namespace SimpleDep\Requests;

use z4kn4fein\SemVer\Constraints\Constraint;
use z4kn4fein\SemVer\Version;

class Request {
  public const TYPE_INSTALL = 1;
  public const TYPE_UPDATE = 2;
  public const TYPE_UNINSTALL = 4;

  /**
   * The versionConstraint of the package to install.
   *
   * @var Constraint|null
   */
  protected ?Constraint $versionConstraint = null;

  /**
   * Create a new request
   *
   * @param int $type
   * @param string $name
   * @param string|Version|null $versionConstraint
   */
  public function __construct(
    protected int $type,
    protected string $name,
    $versionConstraint = null
  ) {
    if (!empty($versionConstraint)) {
      if (is_string($versionConstraint)) {
        $versionConstraint = Constraint::parse($versionConstraint);
      }

      $this->versionConstraint = $versionConstraint;
    }
  }

  public function getType(): int {
    return $this->type;
  }

  public function getName(): string {
    return $this->name;
  }

  public function getVersionConstraint(): ?Constraint {
    return $this->versionConstraint;
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
    return [
      'type' => $this->type,
      'name' => $this->name,
      'versionConstraint' => $this->versionConstraint
        ? (string) $this->versionConstraint
        : null,
    ];
  }

  /**
   * Create a new request from a request
   *
   * @param Request $request
   * @return static
   */
  public static function fromRequest(Request $request): static {
    return new static(
      $request->getType(),
      $request->getName(),
      $request->getVersionConstraint()
    );
  }
}
