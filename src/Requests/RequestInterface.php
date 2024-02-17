<?php

declare(strict_types=1);

namespace SimpleDep\Requests;

use z4kn4fein\SemVer\Constraints\Constraint;
use z4kn4fein\SemVer\Version;

interface RequestInterface {
  /**
   * Create a new request
   *
   * @param int $type
   * @phpstan-param Request::TYPE_* $type
   * @param non-empty-string $name
   * @param string|Version|Constraint|null $versionConstraint
   */
  public function __construct(int $type, string $name, $versionConstraint = null);

  /**
   * Create a request from another request
   *
   * @param RequestInterface $request
   * @return static
   */
  public static function fromRequest(RequestInterface $request): static;

  /**
   * @return array{
   *   type: int,
   *   name: non-empty-string,
   * }
   */
  public function toArray(): array;
}
