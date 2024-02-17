<?php

declare(strict_types=1);

namespace SimpleDep\Solver\Exceptions;

use Exception;

class SolverException extends Exception {
  public const CODE_NO_SOLUTION = 1;
  public const CODE_PACKAGE_NOT_FOUND = 2;
  public const CODE_PACKAGE_VERSION_NOT_FOUND = 3;
  public const CODE_PACKAGE_CONFLICT = 4;
  public const CODE_PACKAGE_REQUIRED = 5;

  public static function noSolution(): self {
    return new self('No solution found', self::CODE_NO_SOLUTION);
  }

  public static function packageNotFound(string $name): self {
    return new self("Package not found: $name", self::CODE_PACKAGE_NOT_FOUND);
  }

  public static function packageVersionNotFound(string $name, string $version): self {
    return new self(
      "Package version not found: $name ($version)",
      self::CODE_PACKAGE_VERSION_NOT_FOUND
    );
  }

  public static function packageConflict(
    string $name,
    string $version,
    string $conflictName,
    string $conflictVersion
  ): self {
    return new self(
      "Package conflict: $name ($version) conflicts with $conflictName ($conflictVersion)",
      self::CODE_PACKAGE_CONFLICT
    );
  }

  public static function packageRequired(
    string $name,
    string $requiredName,
    string $requiredVersion
  ): self {
    return new self(
      "Package $name is required by $requiredName ($requiredVersion)",
      self::CODE_PACKAGE_REQUIRED
    );
  }
}
