<?php

use SimpleDep\Solver\Operations\Operation;

it('can be instantiated', function () {
  $operation = new Operation(Operation::TYPE_INSTALL, 'foo', '1.0.0');
  expect($operation)
    ->toBeInstanceOf(Operation::class)
    ->and($operation->getType())
    ->toBe(Operation::TYPE_INSTALL)
    ->and($operation->getName())
    ->toBe('foo')
    ->and($operation->getVersion())
    ->toBe('1.0.0')
    ->and($operation->getRequiredBy())
    ->toBeArray()
    ->and($operation->getRequiredBy())
    ->toBeEmpty();

  $operation->setRequiredBy([1, 2, 3]);
  expect($operation->getRequiredBy())->toBe([1, 2, 3]);
});

it('can be instantiated using helper classes', function () {
  $operation = Operation::install('foo', '1.0.0');
  expect($operation)
    ->toBeInstanceOf(Operation::class)
    ->and($operation->getType())
    ->toBe(Operation::TYPE_INSTALL)
    ->and($operation->getName())
    ->toBe('foo')
    ->and($operation->getVersion())
    ->toBe('1.0.0')
    ->and($operation->getRequiredBy())
    ->toBeArray()
    ->and($operation->getRequiredBy())
    ->toBeEmpty();
});

it('can be converted to an array', function () {
  $operation = new Operation(Operation::TYPE_INSTALL, 'foo', '1.0.0');
  expect($operation->toArray())
    ->toMatchArray([
      'type' => Operation::TYPE_INSTALL,
      'name' => 'foo',
      'version' => '1.0.0',
      'requiredBy' => [],
    ]);
});
