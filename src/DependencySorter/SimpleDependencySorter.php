<?php

declare(strict_types=1);

namespace SimpleDep\DependencySorter;

use InvalidArgumentException;

/**
 * A simple dependency sorter
 *
 * @template T of array{
 *   id: int,
 *   dependencies?: int[],
 * }
 */
class SimpleDependencySorter {
  /**
   * The items
   *
   * @var T[]
   */
  protected array $items;

  /**
   * The sorted items
   *
   * @var T[]
   */
  protected array $sorted = [];

  /**
   * The remaining items
   *
   * @var T[]
   */
  protected array $remaining = [];

  /**
   * @param T[] $items The items to sort. Each item must have an `id` and `dependencies`.
   */
  public function __construct(array $items) {
    // Validate the items.
    foreach ($items as $item) {
      // @phpstan-ignore-next-line
      if (!isset($item['id'])) {
        throw new InvalidArgumentException('The items must have an `id`');
      }
    }

    $this->items = $items;
  }

  /**
   * Sort the items
   *
   * @return list<T>
   */
  public function sort(): array {
    $this->sorted = [];
    $this->remaining = $this->items;

    while (!empty($this->remaining)) {
      $item = reset($this->remaining);
      // @phpstan-ignore-next-line
      $this->addItemWithDependencies($item);
    }

    return $this->sorted;
  }

  /**
   * Add an item to the sorted list with its dependencies
   *
   * @param T $item The item to add
   * @param int[] $parents The parent item IDs
   * @return int[] The added item IDs.
   */
  protected function addItemWithDependencies(array $item, array $parents = []): array {
    $addedIds = [];

    // Get the missing dependencies of the item.
    $missingDeps = $this->getMissingDependencies($item);

    // If there are missing dependencies, add them to the sorted list first.
    if (!empty($missingDeps)) {
      foreach ($missingDeps as $missingDep) {
        if (in_array($missingDep['id'], $parents)) {
          // Circular dependency detected.
          continue;
        }

        array_push(
          $addedIds,
          ...$this->addItemWithDependencies(
            $missingDep,
            array_merge($parents, [$item['id']])
          )
        );
      }
    }

    // Add the item to the sorted list.
    $addedIds[] = $item['id'];
    $this->addItem($item);

    return $addedIds;
  }

  /**
   * Add an item to the sorted list and remove it from the remaining list.
   *
   * @param T $item The item to add
   */
  protected function addItem(array $item): void {
    // @phpstan-ignore-next-line
    $this->sorted[] = $item;
    $this->remaining = array_values(
      array_filter(
        $this->remaining,
        static fn(array $remainingItem) => $remainingItem['id'] !== $item['id']
      )
    );
  }

  /**
   * Get the dependencies of an item
   *
   * @param T $item The item.
   * @return T[]
   */
  protected function getMissingDependencies(array $item): array {
    $missingDeps = [];

    // Go through the dependencies of the item and check if they are in the added list. If they are not, add them to the missing dependencies list.
    $addedIds = array_map(fn($item) => $item['id'], $this->sorted);
    $missingIds = array_diff($item['dependencies'] ?? [], $addedIds);
    foreach ($this->remaining as $remainingItem) {
      if (in_array($remainingItem['id'], $missingIds)) {
        $missingDeps[] = $remainingItem;
      }
    }

    return $missingDeps;
  }
}
