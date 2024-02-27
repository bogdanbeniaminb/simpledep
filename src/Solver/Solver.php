<?php

declare(strict_types=1);

namespace SimpleDep\Solver;

use SimpleDep\Pool\Pool;
use SimpleDep\Requests\ParsedRequest;
use SimpleDep\Requests\ParsedRequestsCollection;
use SimpleDep\Requests\RequestsCollection;
use SimpleDep\Solver\Exceptions\ParserException;
use SimpleDep\Solver\Exceptions\SolverException;
use SimpleDep\Solver\Operations\Operation;
use SimpleDep\Utils\ObjectMap;
use z4kn4fein\SemVer\Version;

class Solver {
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
   * @return array<non-empty-string, Operation>
   * @throws SolverException
   */
  public function solve(): array {
    $this->parseSolutions();

    // Check if there are solutions.
    if (empty($this->requestSolutions)) {
      return [];
    }

    // Return the first solution.
    $solution = $this->requestSolutions[0];
    return $this->generateOperations($solution);
  }

  /**
   * Parse the solutions.
   *
   * @return ParsedRequestsCollection[]
   * @throws SolverException
   */
  public function parseSolutions(): array {
    try {
      $bulkParser = new BulkParser($this->pool, $this->requests, $this->installed);
      $this->requestSolutions = $bulkParser->parse();
    } catch (ParserException $e) {
      throw new SolverException($e->getMessage(), 0, $e);
    }

    return $this->requestSolutions;
  }

  /**
   * Generate the operations from the solution.
   *
   * @param ParsedRequestsCollection $solution
   * @return array<non-empty-string, Operation>
   */
  public function generateOperations(ParsedRequestsCollection $solution): array {
    // Gather the dependencies for the solution.
    $solution = $solution->gatherDependencies($this->pool, $this->installed);

    // Generate the operations.
    $operations = [];
    /** @var ObjectMap<ParsedRequest, Operation> */
    $operationsMap = new ObjectMap();
    foreach ($solution as $step) {
      $operation = $this->generateOperation($step);
      $operationsMap[$step] = $operation;
      $operations[$step->getName()] = [
        'request' => $step,
        'operation' => $operation,
      ];
    }

    // Transform ParsedRequest dependencies to Operation dependencies.
    foreach ($operations as $operation) {
      /** @var ParsedRequest $request */
      $request = $operation['request'];
      $requiredBy = $request->getRequiredBy();
      /** @var Operation[] $requiredByOperations */
      $requiredByOperations = array_values(
        array_filter(
          array_map(
            static fn(ParsedRequest $request) => $operationsMap[$request] ?? null,
            $requiredBy
          )
        )
      );
      $operation['operation']->setRequiredBy($requiredByOperations);

      // Set the flag if this operation is "new", i.e. installed as a dependency and not explicitly requested.
      if ($requiredByOperations) {
        $operation['operation']->setWasAddedAsDependency(
          !$this->operationWasExplicitlyRequested($operation['operation'])
        );
      }
    }

    // Return the operations.
    return array_map(static fn(array $operation) => $operation['operation'], $operations);
  }

  /**
   * Generate the operation from the request.
   *
   * @param ParsedRequest $request
   * @return Operation
   */
  protected function generateOperation(ParsedRequest $request): Operation {
    if (
      in_array($request->getType(), [
        ParsedRequest::TYPE_INSTALL,
        ParsedRequest::TYPE_UPDATE,
      ])
    ) {
      return Operation::install(
        $request->getName(),
        ((string) $request->getVersion()) ?: null
      );
    } elseif ($request->getType() === ParsedRequest::TYPE_UNINSTALL) {
      return Operation::uninstall($request->getName());
    }

    throw new SolverException('Unknown request type ' . $request->getType());
  }

  /**
   * Check if the operation was explicitly requested.
   *
   * @param Operation $operation
   * @return bool
   */
  protected function operationWasExplicitlyRequested(Operation $operation): bool {
    return $this->requests->contains($operation->getName());
  }
}
