<?php

declare(strict_types=1);

namespace SimpleDep\Requests;

use z4kn4fein\SemVer\Constraints\Constraint;
use z4kn4fein\SemVer\Version;

class Request implements RequestInterface {
  /**
   * @var int
   */
  protected int $type;
  /**
   * @var non-empty-string
   */
  protected string $name;
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
   * @phpstan-param Request::TYPE_* $type
   * @param non-empty-string $name
   * @param string|Version|Constraint|null $versionConstraint
   */
  public function __construct(
    int $type,
    string $name,
    $versionConstraint = null
  ) {
    $this->type = $type;
    $this->name = $name;
    if (!empty($versionConstraint)) {
      if (is_string($versionConstraint)) {
        $versionConstraint = Constraint::parse($versionConstraint);
      } elseif ($versionConstraint instanceof Version) {
        $versionConstraint = Constraint::parse((string) $versionConstraint);
      }

      $this->versionConstraint = $versionConstraint;
    }
  }

  /**
   * Get the type of the request
   *
   * @return int
   * @phpstan-return Request::TYPE_*
   */
  public function getType(): int {
    return $this->type;
  }

  /**
   * Get the name of the package
   *
   * @return non-empty-string
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * Get the versionConstraint of the package
   *
   * @return Constraint|null
   */
  public function getVersionConstraint(): ?Constraint {
    return $this->versionConstraint;
  }

  /**
   * Convert the request to an array
   *
   * @return array{
   *   type: Request::TYPE_*,
   *   name: non-empty-string,
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
   * @param RequestInterface $request
   * @return static
   */
  public static function fromRequest(RequestInterface $request) {
    return new static(
      // @phpstan-ignore-next-line
      $request->getType(),
      $request->getName(),
      $request->getVersionConstraint()
    );
  }
}
