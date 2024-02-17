<?php

use SimpleDep\DependencySorter\SimpleDependencySorter;

it('doesn\'t accept items without an `id`', function () {
  // @phpstan-ignore-next-line
  new SimpleDependencySorter([
    [
      'dependencies' => [
        'test' => 1,
      ],
    ],
  ]);
})->throws(InvalidArgumentException::class);

it('accepts items without dependencies', function () {
  /** @var array<array{id: int}> */
  $array = [['id' => 1], ['id' => 2]];
  expect(new SimpleDependencySorter($array))
    ->not()
    ->toThrow(InvalidArgumentException::class);
  expect((new SimpleDependencySorter($array))->sort())->toBe($array);
});

it('can sort the items based on dependencies', function (array $items, array $expected) {
  $sorter = new SimpleDependencySorter($items);
  expect($sorter->sort())->toBe($expected);
})->with('simple_dependency_sorter_1');

it('can sort items with circular dependencies', function (array $items, array $expected) {
  $sorter = new SimpleDependencySorter($items);
  expect($sorter->sort())->toBe($expected);
})->with('simple_dependency_sorter_circular');
