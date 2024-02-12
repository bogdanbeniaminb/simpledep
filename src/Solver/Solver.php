<?php

declare(strict_types=1);

namespace SimpleDep\Solver;

use Link;
use RuntimeException;
use SimpleDep\Pool\Pool;
use SimpleDep\Requests\RequestsCollection;
use SimpleDep\Solver\Exceptions\SolverException;
use z4kn4fein\SemVer\Constraints\Constraint;
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
   *   version: Version,
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
   * @var RequestsCollection[]
   */
  protected array $requestSolutions = [];

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
   *   version?: Version,
   * }>
   * @throws SolverException
   */
  public function solve(): array {
    $bulkParser = new BulkParser($this->pool, $this->requests, $this->installed);
    $this->requestSolutions = $bulkParser->parse();

    // Go through each request and try to solve it.
  }
}
