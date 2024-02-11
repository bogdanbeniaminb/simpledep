<?php

declare(strict_types=1);

namespace SimpleDep\Requests;

use Countable;
use IteratorAggregate;
use Traversable;
use z4kn4fein\SemVer\Constraints\Constraint;

class RequestsCollection implements IteratorAggregate, Countable {
  /**
   * The pool of packages
   *
   * @var Request[]
   */
  protected array $requests = [];

  /**
   * Install a package
   *
   * @param non-empty-string $name
   * @param Constraint|string|null $versionConstraint
   * @return $this
   */
  public function install(string $name, $versionConstraint = null): static {
    if (!$versionConstraint) {
      $versionConstraint = Constraint::default();
    } elseif (is_string($versionConstraint)) {
      $versionConstraint = Constraint::parse($versionConstraint);
    }

    $this->requests[] = new Request(Request::TYPE_INSTALL, $name, $versionConstraint);
    return $this;
  }

  /**
   * Update a package
   *
   * @param non-empty-string $name
   * @param Constraint|string|null $versionConstraint
   * @return $this
   */
  public function update(string $name, $versionConstraint = null): static {
    if (is_string($versionConstraint)) {
      $versionConstraint = Constraint::parse($versionConstraint);
    }

    $this->requests[] = new Request(Request::TYPE_UPDATE, $name, $versionConstraint);
    return $this;
  }

  /**
   * Uninstall a package
   *
   * @param non-empty-string $name
   * @return $this
   */
  public function uninstall(string $name): static {
    $this->requests[] = new Request(Request::TYPE_UNINSTALL, $name);
    return $this;
  }

  /**
   * Add a request
   *
   * @param Request $request
   * @return $this
   */
  public function addRequest(Request $request): static {
    $this->requests[] = $request;
    return $this;
  }

  /**
   * Get the requests
   *
   * @return Request[]
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
   *   versionConstraint?: Constraint,
   * }>
   */
  public function toArray(): array {
    return array_map(
      static fn (Request $request) => $request->toArray(),
      $this->requests
    );
  }

  /**
   * Get the iterator
   *
   * @return Traversable<Request>
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
    $new->requests = array_slice($this->requests, $offset, $length);
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
      static fn (Request $request) => clone $request,
      $this->requests
    );
  }

  /**
   * Append requests to the collection.
   *
   * @param Request ...$requests
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
   * @param RequestsCollection $requests
   * @return static
   */
  public function merge(RequestsCollection $requests): static {
    return $this->append(...$requests->getRequests());
  }

  /**
   * Get the first request
   *
   * @return Request|null
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
  public function contains(string $name, $type = null): bool {
    return !empty(array_filter(
        $this->requests,
        static fn (Request $request) => $request->getName() === $name && ($type === null || $request->getType() === $type)
      ));
  }
}
