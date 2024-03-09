<?php

declare(strict_types=1);

namespace SimpleDep\Requests;

use Countable;
use IteratorAggregate;

/**
 * A collection of requests
 *
 * @template TRequest of Request
 * @extends IteratorAggregate<int, TRequest>
 */
interface RequestsCollectionInterface extends IteratorAggregate, Countable {
  /**
   * Create a new collection
   *
   * @param TRequest[] $requests
   */
  public function __construct(array $requests = []);

  /**
   * Add a request
   *
   * @param TRequest $request
   * @return $this
   */
  public function addRequest(Request $request);

  /**
   * Get the requests
   *
   * @return TRequest[]
   */
  public function getRequests(): array;
}
