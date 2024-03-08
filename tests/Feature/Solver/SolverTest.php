<?php

use SimpleDep\Package\Package;
use SimpleDep\Pool\Pool;
use SimpleDep\Requests\RequestsCollection;
use SimpleDep\Solver\Exceptions\SolverException;
use SimpleDep\Solver\Operations\Operation;
use SimpleDep\Solver\Solver;

it('can solve requests', function () {
  $foo1 = (new Package('foo', '1.0.0'))->addLink('require', 'bar', '>=1.0.0');
  $foo2 = (new Package('foo', '1.0.1'))->addLink('require', 'bar', '>=1.0.1');
  $bar1 = (new Package('bar', '1.0.0'))->addLink('require', 'baz', '>=1.0.0');
  $bar2 = (new Package('bar', '1.0.1'))->addLink('require', 'baz', '>=1.0.5');
  $boo1 = (new Package('boo', '1.0.5'))
    ->addLink('conflict', 'bee', '>=1.0.0')
    ->addLink('replace', 'wow', '>=1.0.0');
  $boo2 = (new Package('boo', '1.0.7'))
    ->addLink('conflict', 'bee', '>=1.0.0')
    ->addLink('replace', 'wow', '>=1.0.0');
  $baz1 = new Package('baz', '1.0.0');
  $baz2 = new Package('baz', '1.0.1');

  $pool = (new Pool())
    ->addPackage($foo1)
    ->addPackage($bar1)
    ->addPackage($boo1)
    ->addPackage($boo2)
    ->addPackage($baz1)
    ->addPackage($foo2)
    ->addPackage($bar2)
    ->addPackage($baz2);
  $requests = (new RequestsCollection())
    ->install('foo', '>=1.0.0')
    ->update('boo')
    ->uninstall('bee');

  $solver = new Solver($pool, $requests, [
    'boo' => [
      'version' => '1.0.0',
    ],
    'bee' => [
      'version' => '1.0.0',
    ],
  ]);

  expect($solver)
    ->toBeInstanceOf(Solver::class)
    ->and($solution = $solver->solve())
    ->toBeArray()
    ->and(count($solution))
    ->toBeGreaterThanOrEqual(count($requests))
    ->and($solution['foo'])
    ->toBeInstanceOf(Operation::class)
    ->and($solution['foo']->getType())
    ->toBe(Operation::TYPE_INSTALL)
    ->and($solution['foo']->getVersion())
    ->toBe('1.0.0')
    ->and($solution['boo'])
    ->toBeInstanceOf(Operation::class)
    ->and($solution['boo']->getType())
    ->toBe(Operation::TYPE_INSTALL)
    ->and($solution['boo']->getVersion())
    ->toBe('1.0.7')
    ->and($solution['bee'])
    ->toBeInstanceOf(Operation::class)
    ->and($solution['bee']->getType())
    ->toBe(Operation::TYPE_UNINSTALL);
});

it('throws an error if no solution exists', function () {
  $pool = new Pool();
  $requests = (new RequestsCollection())
    ->install('foo', '>=1.0.0')
    ->update('boo')
    ->uninstall('bee');
  $solver = new Solver($pool, $requests, [
    'boo' => [
      'version' => '1.0.0',
    ],
  ]);

  fn() => $solver->solve();
  expect(fn() => $solver->solve())->toThrow(SolverException::class);
});

it('throws an error if there are conflicting dependencies', function () {
  $foo1 = (new Package('foo', '1.0.0'))->addLink('require', 'bar', '>=1.0.0');
  $foo2 = (new Package('foo', '1.0.1'))->addLink('require', 'bar', '>=1.0.1');
  $bar1 = new Package('bar', '1.0.0');
  $bar2 = new Package('bar', '1.0.1');
  $boo1 = (new Package('boo', '1.0.5'))->addLink('require', 'bar', '=1.0.0');
  $pool = (new Pool())
    ->addPackage($foo1)
    ->addPackage($foo2)
    ->addPackage($bar1)
    ->addPackage($bar2)
    ->addPackage($boo1);
  $requests = (new RequestsCollection())
    ->install('foo', '1.0.1')
    ->install('boo', '>=1.0.1');

  $solver = new Solver($pool, $requests, [
    'boo' => [
      'version' => '1.0.0',
    ],
  ]);

  // The "foo" package requires "bar" >=1.0.1, but "boo" requires "bar" =1.0.0.
  expect(fn() => $solver->solve())->toThrow(SolverException::class);

  // Now we add a new version of "boo" that requires "bar" =1.0.1.
  $boo2 = (new Package('boo', '1.0.7'))->addLink('require', 'bar', '=1.0.1');
  $pool->addPackage($boo2);

  // Now the solver should be able to solve the requests.
  expect(fn() => $solver->solve())->not->toThrow(SolverException::class);
});

it('handles dependency packages', function () {
  $foo = (new Package('foo', '1.0.0'))->addDependency('bar', '>=1.0.0');
  $bar = new Package('bar', '1.0.0');
  $pool = (new Pool())->addPackage($foo)->addPackage($bar);
  $requests = (new RequestsCollection())->install('foo', '>=1.0.0');

  $solver = new Solver($pool, $requests);

  $solution = $solver->solve();
  expect($solution)
    ->toBeArray()
    ->and(count($solution))
    ->toBe(2)
    ->and($solution['foo']->getType())
    ->toBe(Operation::TYPE_INSTALL)
    ->and($solution['bar']->getType())
    ->toBe(Operation::TYPE_INSTALL)
    ->and($solution['bar']->getRequiredBy())
    ->toBe([$solution['foo']]);
});

it('handles uninstalling conflicting packages', function () {
  $foo = (new Package('foo', '1.0.0'))->addConflict('bar', '>=1.0.0');
  $bar = new Package('bar', '1.0.0');
  $pool = (new Pool())->addPackage($foo)->addPackage($bar);
  $requests = (new RequestsCollection())->install('foo', '>=1.0.0');

  $solver = new Solver($pool, $requests, [
    'bar' => [
      'version' => '1.0.0',
    ],
  ]);

  $solution = $solver->solve();
  expect($solution)
    ->toBeArray()
    ->and(count($solution))
    ->toBe(2)
    ->and($solution['foo']->getType())
    ->toBe(Operation::TYPE_INSTALL)
    ->and($solution['bar']->getType())
    ->toBe(Operation::TYPE_UNINSTALL)
    ->and($solution['bar']->getRequiredBy())
    ->toBe([$solution['foo']]);
});

it('doesn\'t install an existing package again', function () {
  $foo = new Package('foo', '1.0.0');
  $pool = (new Pool())->addPackage($foo);
  $requests = (new RequestsCollection())->install('foo', '>=1.0.0');

  $solver = new Solver($pool, $requests, [
    'foo' => [
      'version' => '1.0.0',
    ],
  ]);

  $solution = $solver->solve();
  expect($solution)->toBeArray()->and(count($solution))->toBe(0);
});

it('doesn\'t uninstall packages that are not installed', function () {
  $foo = new Package('foo', '1.0.0');
  $pool = (new Pool())->addPackage($foo);
  $requests = (new RequestsCollection())->uninstall('foo');

  $solver = new Solver($pool, $requests);

  $solution = $solver->solve();
  expect($solution)->toBeArray()->and(count($solution))->toBe(0);
});

it(
  'doesn\'t install a newer version of a package that is already installed and fulfills the constraints',
  function () {
    $foo1 = new Package('foo', '1.0.0');
    $foo2 = new Package('foo', '1.0.1');
    $foo3 = new Package('foo', '1.0.2');
    $pool = (new Pool())->addPackage($foo1)->addPackage($foo2)->addPackage($foo3);
    $requests = (new RequestsCollection())->install('foo', '^1.0.0');

    $solver = new Solver($pool, $requests, [
      'foo' => [
        'version' => '1.0.0',
      ],
    ]);

    $solution = $solver->solve();
    expect($solution)->toBeArray()->and(count($solution))->toBe(0);
  }
);

it(
  'handles dependencies that are not in the pool, but are already installed',
  function () {
    $foo = (new Package('foo', '1.0.0'))->addLink('require', 'bar', '>=1.0.0');
    $pool = (new Pool())->addPackage($foo);
    $requests = (new RequestsCollection())->install('foo', '>=1.0.0');

    $solver = new Solver($pool, $requests, [
      'bar' => [
        'version' => '1.0.0',
      ],
    ]);

    $solution = $solver->solve();
    expect($solution)
      ->toBeArray()
      ->and(count($solution))
      ->toBe(1)
      ->and($solution['foo']->getType())
      ->toBe(Operation::TYPE_INSTALL);
  }
);

it(
  'throws an error when a package is required by one request and is conflicted by another',
  function () {
    $foo = (new Package('foo', '1.0.0'))->addLink('require', 'bar', '>=1.0.0');
    $bar = (new Package('bar', '1.0.0'))->addLink('conflict', 'baz', '>=1.0.0');
    $baz = new Package('baz', '1.0.0');
    $pool = (new Pool())->addPackage($foo)->addPackage($bar)->addPackage($baz);
    $requests = (new RequestsCollection())
      ->install('foo', '>=1.0.0')
      ->install('baz', '>=1.0.0');

    $solver = new Solver($pool, $requests);
    expect(fn() => $solver->solve())->toThrow(SolverException::class);
  }
);

it(
  'retrieves whether operations were performed due to direct request or due to dependencies',
  function () {
    $foo = (new Package('foo', '1.0.0'))->addLink('require', 'bar', '>=1.0.0');
    $bar = (new Package('bar', '1.0.0'))->addLink('require', 'baz', '>=1.0.0');
    $baz = new Package('baz', '1.0.0');
    $pool = (new Pool())->addPackage($foo)->addPackage($bar)->addPackage($baz);
    $requests = (new RequestsCollection())
      ->install('foo', '>=1.0.0')
      ->install('baz', '>=1.0.0');

    $solver = new Solver($pool, $requests);

    $solution = $solver->solve();
    expect($solution)
      ->toBeArray()
      ->and(count($solution))
      ->toBe(3)
      ->and($solution)
      ->toHaveKeys(['foo', 'bar', 'baz'])
      ->and($solution['foo']->getType())
      ->toBe(Operation::TYPE_INSTALL)
      ->and($solution['foo']->getRequiredBy())
      ->toBe([])
      ->and($solution['foo']->wasAddedAsDependency())
      ->toBeFalse()
      ->and($solution['bar']->getType())
      ->toBe(Operation::TYPE_INSTALL)
      ->and($solution['bar']->getRequiredBy())
      ->toBe([$solution['foo']])
      ->and($solution['bar']->wasAddedAsDependency())
      ->toBeTrue()
      ->and($solution['baz']->getType())
      ->toBe(Operation::TYPE_INSTALL)
      ->and($solution['baz']->getRequiredBy())
      ->toBe([$solution['bar'], $solution['foo']])
      ->and($solution['baz']->wasAddedAsDependency())
      ->toBeFalse();
  }
);
