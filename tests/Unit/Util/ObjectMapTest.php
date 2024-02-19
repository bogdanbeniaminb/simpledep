<?php

use SimpleDep\Utils\ObjectMap;

it('instantiates an object map', function () {
  $objectMap = new ObjectMap();
  expect($objectMap)->toBeInstanceOf(ObjectMap::class);
});

it('sets and gets a value', function (bool $useFallback) {
  $objectMap = new ObjectMap($useFallback);
  $key = new stdClass();
  $value = new stdClass();
  $objectMap->set($key, $value);
  expect($objectMap->get($key))->toBe($value);
  expect($objectMap->get(new stdClass()))->toBeNull();
})->with([
  'WeakMap' => [false],
  'fallback' => [true],
]);

it('checks if a key exists', function (bool $useFallback) {
  $objectMap = new ObjectMap($useFallback);
  $key = new stdClass();
  $value = new stdClass();
  $objectMap->set($key, $value);
  expect($objectMap->has($key))->toBeTrue();
  expect($objectMap->has(new stdClass()))->toBeFalse();
})->with([
  'WeakMap' => [false],
  'fallback' => [true],
]);

it('deletes a key', function (bool $useFallback) {
  $objectMap = new ObjectMap($useFallback);
  $key = new stdClass();
  $value = new stdClass();
  $objectMap->set($key, $value);

  $key2 = new stdClass();
  $value2 = new stdClass();
  $objectMap->set($key2, $value2);

  $objectMap->delete($key);
  expect($objectMap->has($key))->toBeFalse();
  expect($objectMap->get($key))->toBeNull();
  expect($objectMap->has($key2))->toBeTrue();

  unset($objectMap[$key2]);
  expect($objectMap->has($key2))->toBeFalse();
  expect($objectMap->get($key2))->toBeNull();

  expect(fn() => $objectMap->delete(new stdClass()))->not->toThrow(Throwable::class);
})->with([
  'WeakMap' => [false],
  'fallback' => [true],
]);

it('iterates over the map', function (bool $useFallback) {
  $objectMap = new ObjectMap($useFallback);
  $key = new stdClass();
  $value = new stdClass();
  $objectMap->set($key, $value);
  foreach ($objectMap as $k => $v) {
    expect($k)->toBe($key);
    expect($v)->toBe($value);
  }
})->with([
  'WeakMap' => [false],
  'fallback' => [true],
]);
