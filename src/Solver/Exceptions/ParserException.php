<?php

declare(strict_types=1);

namespace SimpleDep\Solver\Exceptions;

use Exception;
use z4kn4fein\SemVer\Constraints\Constraint;
use z4kn4fein\SemVer\Version;

class ParserException extends Exception {
  public const CODE_NO_SOLUTION = 1;
  public const CODE_PACKAGE_NOT_FOUND = 2;
  public const CODE_PACKAGE_VERSION_NOT_FOUND = 3;
  public const CODE_PACKAGE_CONFLICT = 4;
  public const CODE_PACKAGE_REQUIRED = 5;
  public const CODE_INVALID_OPERATION_TYPE = 6;

  public static function noSolution(): self {
    return new self('No solution found', self::CODE_NO_SOLUTION);
  }

  public static function packageNotFound(string $name): self {
    return new self("Package not found: $name", self::CODE_PACKAGE_NOT_FOUND);
  }

  /**
   * @param string $name
   * @param string|Version|Constraint|null $version
   */
  public static function packageVersionNotFound(string $name, $version): self {
    if ($version === null) {
      return new self(
        "Package version not found: $name",
        self::CODE_PACKAGE_VERSION_NOT_FOUND
      );
    }
    $version = (string) $version;
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

  public static function invalidOperationType(int $type): self {
    return new self("Invalid operation type: $type", self::CODE_INVALID_OPERATION_TYPE);
  }

  /**
   * @param string $name
   * @return ParserException
   */
  public static function packageNotInstalled(string $name): self {
    return new self("Package not installed: $name", self::CODE_PACKAGE_NOT_FOUND);
  }
}
