<?php

namespace SimpleDep\Package;

use InvalidArgumentException;
use z4kn4fein\SemVer\Constraints\Constraint;
use z4kn4fein\SemVer\Version;

class Package {
  /**
   * @phpstan-var array<non-empty-string, array{
   *   description: string,
   *   type: Link::TYPE_*
   * }>
   * @internal
   */
  public static array $supportedLinkTypes = [
    'require' => ['description' => 'requires', 'type' => Link::TYPE_REQUIRE],
    'conflict' => ['description' => 'conflicts', 'type' => Link::TYPE_CONFLICT],
    'provide' => ['description' => 'provides', 'type' => Link::TYPE_PROVIDE],
    'replace' => ['description' => 'replaces', 'type' => Link::TYPE_REPLACE],
    'require-dev' => [
      'description' => 'requires (for development)',
      'type' => Link::TYPE_DEV_REQUIRE,
    ],
  ];

  /**
   * The package ID.
   *
   * @internal
   * @var int|null
   */
  protected ?int $id = null;

  /**
   * The package name
   *
   * @var non-empty-string
   */
  protected string $name;

  /**
   * The package version
   *
   * @var Version
   */
  protected Version $version;

  /**
   * The package links
   *
   * @var array<non-empty-string, array{
   *   type: non-empty-string,
   *   name: non-empty-string,
   *   versionConstraint?: Constraint|null,
   * }>
   */
  protected array $links = [];

  /**
   * Create a new package
   *
   * @param non-empty-string $name
   * @param string|Version|null $version
   */
  public function __construct(string $name, $version = null) {
    if (empty($name)) {
      throw new InvalidArgumentException('Package name cannot be empty');
    }

    $this->name = $name;
    $version =
      $version instanceof Version ? $version : Version::parseOrNull($version ?: '');
    $this->version = $version ?: Version::parse('0.0.0');
  }

  /**
   * Returns the package id
   *
   * @return int|null
   */
  public function getId(): ?int {
    return $this->id;
  }

  /**
   * Set the package id
   *
   * @param int $id
   * @return $this
   */
  public function setId(int $id) {
    $this->id = $id;
    return $this;
  }

  /**
   * Returns the package name
   *
   * @return non-empty-string
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * Returns the package version
   *
   * @return Version
   */
  public function getVersion(): Version {
    return $this->version;
  }

  /**
   * Set the package version
   *
   * @param string|Version $version
   * @return $this
   */
  public function setVersion($version) {
    if (is_string($version)) {
      $version = Version::parseOrNull($version) ?: Version::parse('0.0.0');
    }
    $this->version = $version;
    return $this;
  }

  /**
   * Returns the package links
   *
   * @return array<non-empty-string, array{
   *   type: non-empty-string,
   *   name: non-empty-string,
   *   versionConstraint?: Constraint|null,
   * }>
   */
  public function getLinks(): array {
    return $this->links;
  }

  /**
   * Add a link to another package
   *
   * @param non-empty-string $name
   * @param non-empty-string $type
   * @param string|Constraint|null $versionConstraint
   * @return $this
   */
  public function addLink(string $type, string $name, $versionConstraint = null) {
    if (!isset(self::$supportedLinkTypes[$type])) {
      throw new InvalidArgumentException('Invalid link type: ' . $type);
    }

    if (is_string($versionConstraint)) {
      $versionConstraint =
        Constraint::parseOrNull($versionConstraint) ?: Constraint::default();
    } elseif ($versionConstraint === null) {
      $versionConstraint = Constraint::default();
    }

    $this->links[$name] = [
      'type' => $type,
      'name' => $name,
      'versionConstraint' => $versionConstraint,
    ];
    return $this;
  }

  /**
   * Add a require link to another package.
   *
   * @param non-empty-string $name
   * @param string|Constraint|null $versionConstraint
   * @return $this
   */
  public function addDependency(string $name, $versionConstraint = '*') {
    return $this->addLink('require', $name, $versionConstraint);
  }

  /**
   * Add a conflict link to another package.
   *
   * @param non-empty-string $name
   * @param string|Constraint|null $versionConstraint
   * @return $this
   */
  public function addConflict(string $name, $versionConstraint = '*') {
    return $this->addLink('conflict', $name, $versionConstraint);
  }
}
