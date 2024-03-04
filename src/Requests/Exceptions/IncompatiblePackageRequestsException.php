<?php

declare(strict_types=1);

namespace SimpleDep\Requests\Exceptions;

use Exception;
use SimpleDep\Pool\Pool;
use SimpleDep\Requests\ParsedRequest;
use z4kn4fein\SemVer\Version;

class IncompatiblePackageRequestsException extends Exception {
  const INCOMPATIBLE_VERSIONS = 1;
  const INCOMPATIBLE_ACTIONS = 2;

  /**
   * The requests that are incompatible.
   *
   * @var array<array{
   *   package: non-empty-string,
   *   version: non-empty-string|null,
   *   type: ParsedRequest::TYPE_*
   * }>
   */
  protected array $incompatibleRequests = [];

  /**
   * Create an exception from a collection of requests.
   *
   * @param ParsedRequest[] $requests
   * @param Pool $pool
   * @param array<non-empty-string, array{
   *   version: Version|string,
   * }> $installed
   * @return self
   */
  public static function incompatibleVersions(
    array $requests,
    Pool $pool,
    array $installed
  ) {
    $exception = new self(
      'Incompatible package versions requested',
      self::INCOMPATIBLE_VERSIONS
    );
    $exception->loadIncompatibleRequests($requests, $pool, $installed);
    return $exception;
  }

  /**
   * Create an exception from a collection of requests.
   *
   * @param ParsedRequest[] $requests
   * @param Pool $pool
   * @param array<non-empty-string, array{
   *   version: Version|string,
   * }> $installed
   * @return self
   */
  public static function incompatibleActions(
    array $requests,
    Pool $pool,
    array $installed
  ) {
    $exception = new self(
      'Incompatible package actions requested',
      self::INCOMPATIBLE_ACTIONS
    );
    $exception->loadIncompatibleRequests($requests, $pool, $installed);
    return $exception;
  }

  /**
   * Load the incompatible requests.
   *
   * @param ParsedRequest[] $requests
   * @param Pool $pool
   * @param array<non-empty-string, array{
   *   version: Version|string,
   * }> $installed
   * @return $this
   */
  public function loadIncompatibleRequests(
    array $requests,
    Pool $pool,
    array $installed
  ): static {
    $this->incompatibleRequests = [];
    foreach ($requests as $incompatibleRequest) {
      $this->incompatibleRequests[] = [
        'package' => $incompatibleRequest->getName(),
        'version' => ((string) $incompatibleRequest->getVersion()) ?: null,
        'type' => $incompatibleRequest->getType(),
      ];
    }
    return $this;
  }

  /**
   * Get the incompatible requests.
   *
   * @return array<array{
   *   package: non-empty-string,
   *   version: non-empty-string|null,
   *   type: ParsedRequest::TYPE_*
   * }>
   */
  public function getIncompatibleRequests(): array {
    return $this->incompatibleRequests;
  }
}
