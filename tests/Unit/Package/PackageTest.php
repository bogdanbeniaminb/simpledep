<?php

use SimpleDep\Package\Package;
use z4kn4fein\SemVer\Constraints\Constraint;
use z4kn4fein\SemVer\Version;

it('instatiates a package', function () {
  $package = new Package('foo', '1.0.0');
  expect($package->getName())->toBe('foo');
  expect($package->getVersion())->toBeInstanceOf(Version::class);
  expect($package->getVersion()->__toString())->toBe('1.0.0');
});

it('sets the package version', function () {
  $package = new Package('foo', '1.0.0');
  $package->setVersion('2.0.0');
  expect($package->getVersion()->__toString())->toBe('2.0.0');
});

it('sets the package id', function () {
  $package = new Package('foo', '1.0.0');
  $package->setId(1);
  expect($package->getId())->toBe(1);
});

it('sets links - requires', function () {
  $package = new Package('foo', '1.0.0');
  $constraint = Constraint::parse('>=1.0.4');
  $package->addLink('require', 'bar', $constraint);
  expect($package->getLinks())->toBe([
    'bar' => [
      'type' => 'require',
      'name' => 'bar',
      'versionConstraint' => $constraint
    ]
  ]);

  $constraint2 = Constraint::parse('>=1.0.5');
  $package->addDependency('baz', $constraint2);
  expect($package->getLinks())->toBe([
    'bar' => [
      'type' => 'require',
      'name' => 'bar',
      'versionConstraint' => $constraint
    ],
    'baz' => [
      'type' => 'require',
      'name' => 'baz',
      'versionConstraint' => $constraint2
    ]
  ]);
});

it('sets links - conflicts', function () {
  $package = new Package('foo', '1.0.0');
  $constraint = Constraint::parse('>=1.0.4');
  $package->addLink('conflict', 'bar', $constraint);
  $package->addConflict('baz', $constraint);
  expect($package->getLinks())->toBe([
    'bar' => [
      'type' => 'conflict',
      'name' => 'bar',
      'versionConstraint' => $constraint
    ],
    'baz' => [
      'type' => 'conflict',
      'name' => 'baz',
      'versionConstraint' => $constraint
    ]
  ]);
});

it('doesn\'t set links with invalid type', function () {
  $package = new Package('foo', '1.0.0');
  expect(fn() => $package->addLink('invalid', 'bar', Constraint::parse('>=1.0.4')))
    ->toThrow('Invalid');
});

it('doesn\'t instantiate with empty name', function () {
  expect(fn() => new Package('', '1.0.0'))->toThrow('Package name cannot be empty');
});

it('handles links with string, missing or invalid versions', function () {
  $package = new Package('foo', '1.0.0');
  $package->addLink('require', 'bar', '>=1.0.4');
  $package->addLink('require', 'baz');
  $package->addLink('require', 'qux', 'invalid');
  $links = $package->getLinks();
  expect($links)->toHaveKeys(['bar', 'baz', 'qux']);
  expect($links['bar']['versionConstraint']->__toString())->toBe('>=1.0.4');
  expect($links['baz']['versionConstraint']->__toString())->toBe(Constraint::default()->__toString());
  expect($links['qux']['versionConstraint']->__toString())->toBe(Constraint::default()->__toString());
});