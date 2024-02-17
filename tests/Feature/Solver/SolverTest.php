<?php

use SimpleDep\Package\Package;
use SimpleDep\Pool\Pool;
use SimpleDep\Requests\RequestsCollection;
use SimpleDep\Solver\Exceptions\SolverException;
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
  ]);

  expect($solver)
    ->toBeInstanceOf(Solver::class)
    ->and($solution = $solver->solve())
    ->toBeArray()
    ->and(count($solution))
    ->toBeGreaterThanOrEqual(count($requests));
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
    ->install('boo', '>=1.0.0');

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

it('handles uninstalling replaced packages', function () {
  $foo = (new Package('foo', '1.0.0'))->addLink('replace', 'bar', '>=1.0.0');
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
    ->and($solution['foo']['type'])
    ->toBe(Solver::OPERATION_TYPE_INSTALL)
    ->and($solution['bar']['type'])
    ->toBe(Solver::OPERATION_TYPE_UNINSTALL);
});
