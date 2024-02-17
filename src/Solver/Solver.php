<?php

declare(strict_types=1);

namespace SimpleDep\Solver;

use SimpleDep\Pool\Pool;
use SimpleDep\Requests\ParsedRequest;
use SimpleDep\Requests\ParsedRequestsCollection;
use SimpleDep\Requests\RequestsCollection;
use SimpleDep\Solver\Exceptions\SolverException;
use z4kn4fein\SemVer\Version;

class Solver {
  public const OPERATION_TYPE_INSTALL = 1;
  public const OPERATION_TYPE_UPDATE = 2;
  public const OPERATION_TYPE_UNINSTALL = 4;

  /**
   * The pool of packages
   *
   * @var Pool
   */
  protected Pool $pool;

  /**
   * The pool of packages
   *
   * @var array<non-empty-string, array{
   *   version: Version|string,
   * }>
   */
  protected array $installed = [];

  /**
   * The pool of packages
   *
   * @var RequestsCollection
   */
  protected RequestsCollection $requests;

  /**
   * The bulk-processed request solutions.
   *
   * @var ParsedRequestsCollection[]
   */
  protected array $requestSolutions = [];

  /**
   * @param Pool $pool The pool of packages
   * @param RequestsCollection $requests The requests
   * @param array<non-empty-string, array{
   *   version: Version|string,
   * }> $installed The installed packages
   */
  public function __construct(
    Pool $pool,
    RequestsCollection $requests,
    array $installed = []
  ) {
    $this->pool = $pool;
    $this->installed = $installed;
    $this->requests = $requests;
  }

  /**
   * Solve the dependencies
   *
   * @return array<non-empty-string, array{
   *   type: Solver::OPERATION_TYPE_*,
   *   name: non-empty-string,
   *   version?: string|null,
   * }>
   * @throws SolverException
   */
  public function solve(): array {
    $bulkParser = new BulkParser($this->pool, $this->requests, $this->installed);
    $this->requestSolutions = $bulkParser->parse();

    // Check if there are solutions.
    if (empty($this->requestSolutions)) {
      throw new SolverException('No solution found');
    }

    // Return the first solution.
    $solution = $this->requestSolutions[0];
    return $this->generateOperations($solution);
  }

  /**
   * Generate the operations from the solution.
   *
   * @param ParsedRequestsCollection $solution
   * @return array<non-empty-string, array{
   *   type: Solver::OPERATION_TYPE_*,
   *   name: non-empty-string,
   *   version?: string|null,
   * }>
   */
  protected function generateOperations(ParsedRequestsCollection $solution): array {
    $operations = [];
    foreach ($solution as $step) {
      $operations[$step->getName()] = $this->generateOperation($step);
    }
    return $operations;
  }

  /**
   * Generate the operation from the request.
   *
   * @param ParsedRequest $request
   * @return array{
   *   type: Solver::OPERATION_TYPE_*,
   *   name: non-empty-string,
   *   version?: string|null,
   * }
   */
  protected function generateOperation(ParsedRequest $request): array {
    $operation = [
      'name' => $request->getName(),
    ];

    $version = null;
    if ($request->getType() === ParsedRequest::TYPE_INSTALL) {
      $operation['type'] = self::OPERATION_TYPE_INSTALL;
      $version = $request->getVersion();
    } elseif ($request->getType() === ParsedRequest::TYPE_UPDATE) {
      $operation['type'] = self::OPERATION_TYPE_UPDATE;
      $version = $request->getVersion();
    } elseif ($request->getType() === ParsedRequest::TYPE_UNINSTALL) {
      $operation['type'] = self::OPERATION_TYPE_UNINSTALL;
    }

    if (!empty($version)) {
      $operation['version'] = (string) $version;
    }

    return $operation;
  }
}
