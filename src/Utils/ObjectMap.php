<?php

declare(strict_types=1);


namespace SimpleDep\Utils;

use ArrayAccess;
use ArrayIterator;
use Iterator;
use IteratorAggregate;
use Traversable;
use WeakMap;

/**
 * A simple WeakMap implementation
 *
 * @template TKey of object
 * @template TValue
 * @implements IteratorAggregate<TKey, TValue>
 * @implements ArrayAccess<TKey, TValue>
 */
class ObjectMap implements IteratorAggregate, ArrayAccess {
  /**
   * A fallback map.
   *
   * @var array<array{
   *   key: TKey,
   *   value: TValue,
   * }>
   */
  private array $fallbackMap = [];

  /**
   * Native WeakMap, if available
   *
   * @var WeakMap<TKey, TValue>|null
   */
  private $weakMap = null;

  /**
   * Whether to use the fallback map
   *
   * @var bool
   */
  private bool $useFallback = false;

  public function __construct() {
    if (class_exists(WeakMap::class)) {
      $this->weakMap = new WeakMap();
    } else {
      $this->useFallback = true;
    }
  }

  /**
   * Set a value for the given key
   *
   * @param TKey $key The key to set
   * @param TValue $value The value to set
   * @return void
   */
  public function set(mixed $key, mixed $value): void {
    if ($this->useFallback) {
      $this->fallbackMap[] = [
        'key' => $key,
        'value' => $value,
      ];
      return;
    }

    // @phpstan-ignore-next-line
    $this->weakMap->offsetSet($key, $value);
  }

  /**
   * Get the value for the given key
   *
   * @param TKey $key The key to get
   * @return TValue|null The value for the given key, or null if it does not exist
   */
  public function get(mixed $key): mixed {
    if ($this->useFallback) {
      foreach ($this->fallbackMap as $entry) {
        if ($entry['key'] === $key) {
          return $entry['value'];
        }
      }
      return null;
    }

    // @phpstan-ignore-next-line
    return $this->weakMap->offsetGet($key);
  }

  /**
   * Check if the given key exists
   *
   * @param TKey $key The key to check
   * @return bool Whether the key exists
   */
  public function has(mixed $key): bool {
    if ($this->useFallback) {
      foreach ($this->fallbackMap as $entry) {
        if ($entry['key'] === $key) {
          return true;
        }
      }
      return false;
    }

    // @phpstan-ignore-next-line
    return $this->weakMap->offsetExists($key);
  }

  /**
   * Delete the given key
   *
   * @param TKey $key The key to delete
   * @return void
   */
  public function delete(mixed $key): void {
    if ($this->useFallback) {
      foreach ($this->fallbackMap as $i => $entry) {
        if ($entry['key'] === $key) {
          unset($this->fallbackMap[$i]);
          return;
        }
      }
      return;
    }

    // @phpstan-ignore-next-line
    $this->weakMap->offsetUnset($key);
  }

  /**
   * Set a value for the given key
   *
   * @param TKey $key The key to set
   * @param TValue $value The value to set
   * @return void
   */
  public function offsetSet(mixed $key, mixed $value): void {
    $this->set($key, $value);
  }

  /**
   * Get the value for the given key
   *
   * @param TKey $key The key to get
   * @return TValue|null The value for the given key, or null if it does not exist
   */
  public function offsetGet(mixed $key): mixed {
    return $this->get($key);
  }

  /**
   * Check if the given key exists
   *
   * @param TKey $key The key to check
   * @return bool Whether the key exists
   */
  public function offsetExists(mixed $key): bool {
    return $this->has($key);
  }

  /**
   * Delete the given key
   *
   * @param TKey $key The key to delete
   * @return void
   */
  public function offsetUnset(mixed $key): void {
    $this->delete($key);
  }

  /**
   * Get an iterator for the map
   *
   * @return IteratorAggregate<TKey, TValue>
   */
  public function getIterator(): Traversable {
    if ($this->useFallback) {
      return (function () {
        foreach ($this->fallbackMap as $item) {
          yield $item['key'] => $item['value'];
        }
      })();
    }

    return $this->weakMap;
  }
}
