<?php

use SimpleDep\Package\Package;
use SimpleDep\Pool\Pool;
use z4kn4fein\SemVer\Constraints\Constraint;

it('instantiates a pool', function () {
  $pool = new Pool();
  expect($pool)
    ->toBeInstanceOf(Pool::class)
    ->and($pool->getPackages())
    ->toBe([]);
});

it('adds packages to the pool and retrieves them', function () {
  $pool = new Pool();
  $foo = new Package('foo', '1.0.0');
  $foo2 = new Package('foo', '1.0.1');
  $baz = new Package('baz', '1.0.0');

  $pool->addPackage($foo)->addPackage($foo2)->addPackage($baz);
  expect($pool->getPackages())
    ->toMatchArray([$foo, $foo2, $baz])
    ->and($pool->getPackageVersions('foo'))
    ->toMatchArray([$foo2, $foo])
    ->and($pool->getPackageVersions('bar'))
    ->toBe([])
    ->and($pool->getPackagesByConstraint('foo', '>=1.0.0'))
    ->toMatchArray([$foo2, $foo])
    ->and($pool->getPackagesByConstraint('foo', '>=1.0.1'))
    ->toBe([$foo2])
    ->and($pool->getPackagesByConstraint('foo', '>=1.0.2'))
    ->toBe([])
    ->and($pool->getPackagesByConstraint('foo', '*'))
    ->toBe([$foo2, $foo])
    ->and($pool->getPackagesByConstraint('foo', Constraint::parse('>=1.0.0')))
    ->toMatchArray([$foo2, $foo])
    ->and($pool->getPackagesByConstraint('foo'))
    ->toMatchArray([$foo2, $foo])
    ->and($pool->getLatestPackage('foo'))
    ->toBe($foo2)
    ->and($pool->getPackagesByConstraint('bar', '>=1.0.0'))
    ->toBe([])
    ->and($pool->getLatestPackage('baz'))
    ->toBe($baz)
    ->and($pool->getPackageVersions('baz'))
    ->toBe([$baz])
    ->and($pool->getPackagesByConstraint('baz', '^1.0.0'))
    ->toBe([$baz])
    ->and($pool->getPackagesByConstraint('baz', '>=1.0.1'))
    ->toBe([])
    ->and($pool->hasPackage('foo'))
    ->toBeTrue()
    ->and($pool->hasPackage('bar'))
    ->toBeFalse()
    ->and(fn() => $pool->getPackagesByConstraint('foo', 'invalid'))
    ->toThrow(RuntimeException::class)
    ->and($pool->getPackageById(null))
    ->toBeNull();
});

it('can generate missing package IDs', function () {
  $pool = new Pool();
  $foo = new Package('foo', '1.0.0');
  $foo2 = new Package('foo', '1.0.1');
  $foo2->setId(25);
  $baz = new Package('baz', '1.0.0');

  $pool->addPackage($foo)->addPackage($foo2)->addPackage($baz);
  $pool->ensurePackageIds();
  $minFakePackageId = $pool->getStartingFakePackageId();
  expect($minFakePackageId)->toBeGreaterThan(25);

  expect($foo->getId())
    ->toBeGreaterThanOrEqual($minFakePackageId)
    ->and($foo2->getId())
    ->toBe(25)
    ->and($baz->getId())
    ->toBeGreaterThanOrEqual($minFakePackageId)
    ->and($pool->getPackageById($foo->getId()))
    ->toBe($foo)
    ->and($pool->getPackageById($foo2->getId()))
    ->toBe($foo2)
    ->and($pool->getPackageById($baz->getId()))
    ->toBe($baz)
    ->and($pool->getPackageById(3))
    ->toBeNull();
});
