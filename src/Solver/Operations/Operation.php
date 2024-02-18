<?php

declare(strict_types=1);

namespace SimpleDep\Solver\Operations;

class Operation {
  public const TYPE_INSTALL = 'install';
  public const TYPE_UNINSTALL = 'uninstall';

  /**
   * The type of the operation
   *
   * @phpstan-var Operation::TYPE_*
   * @var non-empty-string
   */
  protected string $type;

  /**
   * The name of the package
   *
   * @var non-empty-string
   */
  protected string $name;

  /**
   * The version of the package
   *
   * @var string|null
   */
  protected ?string $version = null;

  /**
   * The packages that require this package
   *
   * @var Operation[]
   */
  protected array $requiredBy = [];

  /**
   * @param non-empty-string $type The type of the operation
   * @phpstan-param Operation::TYPE_* $type
   * @param non-empty-string $name The name of the package
   * @param string|null $version The version of the package
   */
  public function __construct(string $type, string $name, ?string $version = null) {
    $this->type = $type;
    $this->name = $name;
    $this->version = $version;
  }

  /**
   * Create a new install operation
   *
   * @param non-empty-string $name The name of the package
   * @param string|null $version The version of the package
   * @return Operation
   */
  public static function install(string $name, ?string $version = null): Operation {
    return new Operation(self::TYPE_INSTALL, $name, $version);
  }

  /**
   * Create a new uninstall operation
   *
   * @param non-empty-string $name The name of the package
   * @return Operation
   */
  public static function uninstall(string $name): Operation {
    return new Operation(self::TYPE_UNINSTALL, $name);
  }

  /**
   * Get the type of the operation
   *
   * @phpstan-return Operation::TYPE_*
   * @return string
   */
  public function getType(): string {
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
   * Get the version of the package
   *
   * @return string|null
   */
  public function getVersion(): ?string {
    return $this->version;
  }

  /**
   * Get the operations that require this one.
   *
   * @return Operation[]
   */
  public function getRequiredBy(): array {
    return $this->requiredBy;
  }

  /**
   * Set the packages that require this package
   *
   * @param Operation[] $requiredBy The packages that require this package
   * @return $this
   */
  public function setRequiredBy(array $requiredBy): static {
    $this->requiredBy = $requiredBy;
    return $this;
  }

  /**
   * Convert the operation to an array
   *
   * @return array{
   *   type: Operation::TYPE_*,
   *   name: non-empty-string,
   *   version: string|null,
   *   requiredBy: array<array{
   *     type: Operation::TYPE_*,
   *     name: non-empty-string,
   *     version: string|null,
   *   }>,
   * }
   */
  public function toArray(): array {
    return [
      'type' => $this->type,
      'name' => $this->name,
      'version' => $this->version,
      'requiredBy' => array_map(
        static fn(Operation $operation): array => $operation->toArray(),
        $this->requiredBy
      ),
    ];
  }
}
