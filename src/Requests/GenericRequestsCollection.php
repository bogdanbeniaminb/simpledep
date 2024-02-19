<?php

declare(strict_types=1);

namespace SimpleDep\Requests;

use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;
use z4kn4fein\SemVer\Constraints\Constraint;

/**
 * A collection of requests
 *
 * @template TRequest of Request
 * @implements RequestsCollectionInterface<TRequest>
 */
class GenericRequestsCollection implements RequestsCollectionInterface {
  /**
   * The pool of packages
   *
   * @var TRequest[]
   */
  protected array $requests = [];

  /**
   * The request class
   *
   * @var class-string<TRequest>
   */
  protected string $requestClass = Request::class;

  /**
   * Create a new collection
   *
   * @param TRequest[] $requests
   */
  public function __construct(array $requests = []) {
    $this->requests = $requests;
  }

  /**
   * Add a request
   *
   * @param TRequest $request
   * @return $this
   */
  public function addRequest(Request $request): static {
    $this->requests[] = $request;
    return $this;
  }

  /**
   * Get the requests
   *
   * @return TRequest[]
   */
  public function getRequests(): array {
    return $this->requests;
  }

  /**
   * Convert the requests to an array
   *
   * @return array<non-empty-string, array{
   *   name: non-empty-string,
   *   type: Request::TYPE_*,
   *   versionConstraint?: string|null,
   * }>
   */
  public function toArray(): array {
    return array_map(static fn(Request $request) => $request->toArray(), $this->requests);
  }

  /**
   * Get the iterator
   *
   * @return Traversable<TRequest>
   */
  public function getIterator(): Traversable {
    foreach ($this->requests as $index => $request) {
      yield $index => $request;
    }
  }

  /**
   * Slice the requests
   *
   * @param int $offset
   * @param int|null $length
   * @return static
   */
  public function slice(int $offset, ?int $length = null): static {
    $new = new static();
    $new->requests = array_values(array_slice($this->requests, $offset, $length));
    return $new;
  }

  public function count(): int {
    return count($this->requests);
  }

  /**
   * Clone the requests
   */
  public function __clone() {
    $this->requests = array_map(
      static fn(Request $request) => clone $request,
      $this->requests
    );
  }

  /**
   * Append requests to the collection.
   *
   * @param TRequest ...$requests
   * @return static
   */
  public function append(Request ...$requests): static {
    $new = clone $this;
    $new->requests = array_merge($new->requests, $requests);
    return $new;
  }

  /**
   * Merge requests into the collection.
   *
   * @param static $requests
   * @return static
   */
  public function merge($requests): static {
    if (!($requests instanceof static)) {
      throw new InvalidArgumentException(
        'The requests must be an instance of ' . static::class
      );
    }

    return $this->append(...$requests->getRequests());
  }

  /**
   * Filter the requests
   *
   * @param callable $callback
   * @phpstan-param callable(TRequest): bool $callback
   * @return static
   */
  public function filter(callable $callback): static {
    $new = new static();
    $new->requests = array_values(array_filter($this->requests, $callback));
    return $new;
  }

  /**
   * Find a request
   *
   * @param callable $callback
   * @phpstan-param callable(TRequest): bool $callback
   * @return TRequest|null
   */
  public function find(callable $callback): ?Request {
    foreach ($this->requests as $request) {
      if ($callback($request)) {
        return $request;
      }
    }

    return null;
  }

  /**
   * Get the first request
   *
   * @return TRequest|null
   */
  public function first(): ?Request {
    return reset($this->requests) ?: null;
  }

  /**
   * Check if the collection has a request by name
   *
   * @param non-empty-string $name
   * @param null|Request::TYPE_* $type
   * @return bool
   */
  public function contains(string $name, ?int $type = null): bool {
    return !empty(
      array_filter(
        $this->requests,
        static fn(Request $request) => $request->getName() === $name &&
          ($type === null || $request->getType() === $type)
      )
    );
  }

  /**
   * Make the requests unique.
   *
   * @return static
   */
  public function unique(): static {
    /** @var TRequest[] $newRequests */
    $newRequests = [];
    foreach ($this->requests as $request) {
      foreach ($newRequests as $newRequest) {
        if (json_encode($newRequest->toArray()) === json_encode($request->toArray())) {
          continue 2;
        }
      }

      $newRequests[] = $request;
    }

    return new static($newRequests);
  }
}
