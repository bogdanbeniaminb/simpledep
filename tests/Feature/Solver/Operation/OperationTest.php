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
    ->toBeEmpty()
    ->and($operation->getPackageId())
    ->toBeNull();

  $operation->setPackageId(123);
  expect($operation->getPackageId())->toBe(123);

  $operation2 = new Operation(Operation::TYPE_UNINSTALL, 'foo');
  $operation->setRequiredBy([$operation2]);
  expect($operation->getRequiredBy())->toBe([$operation2]);
});

it('can be instantiated using helper classes', function () {
  $operation = Operation::install('foo', '1.0.0', 123);
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
    ->toBeEmpty()
    ->and($operation->getPackageId())
    ->toBe(123);
});

it('can be converted to an array', function () {
  $operation = new Operation(Operation::TYPE_INSTALL, 'foo', '1.0.0');
  expect($operation->toArray())->toMatchArray([
    'type' => Operation::TYPE_INSTALL,
    'name' => 'foo',
    'version' => '1.0.0',
    'packageId' => null,
    'requiredBy' => [],
  ]);

  $operation->setPackageId(123);
  expect($operation->toArray())->toMatchArray([
    'type' => Operation::TYPE_INSTALL,
    'name' => 'foo',
    'version' => '1.0.0',
    'packageId' => 123,
    'requiredBy' => [],
  ]);
});
