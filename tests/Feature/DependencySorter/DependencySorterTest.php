<?php

use SimpleDep\DependencySorter\DependencySorter;
use SimpleDep\DependencySorter\Exceptions\DependencyException;
use SimpleDep\Package\Package;
use SimpleDep\Pool\Pool;
use SimpleDep\Requests\ParsedRequest;
use SimpleDep\Requests\ParsedRequestsCollection;

it('gathers request data correctly', function () {
  $foo1 = new Package('foo', '1.0.0');
  $foo2 = new Package('foo', '1.0.1');
  $foo2->setId(1)->addDependency('baz', '1.0.0');
  $baz = new Package('baz', '1.0.0');
  $bar = new Package('bar', '1.0.0');
  $pool = new Pool();
  $pool
    ->addPackage($foo1)
    ->addPackage($foo2)
    ->addPackage($baz)
    ->addPackage($bar)
    ->ensurePackageIds();

  $requests = new ParsedRequestsCollection();
  $requests->install($foo2)->uninstall('bar')->install($baz);

  $sorter = new class ($requests, $pool) extends DependencySorter {
    public function gatherRequestsData(): void {
      parent::gatherRequestsData();
    }

    /**
     * @return array<array{
     *   id: int,
     *   request: ParsedRequest,
     *   dependencies: int[],
     * }>
     */
    public function getRequestsData(): array {
      return $this->requestsData;
    }
  };

  $sorter->gatherRequestsData();
  $requestsData = $sorter->getRequestsData();

  expect($requestsData)->toBeArray()->toHaveCount(3);
  $fooRequest = collect($requestsData)
    ->filter(static fn($request) => $request['request']->getName() === 'foo')
    ->values()
    ->first();
  expect($fooRequest)->toBeArray();
  /** @var array{id: int, request: ParsedRequest, dependencies: int[]} $fooRequest */
  $bazRequest = collect($requestsData)
    ->filter(static fn($request) => $request['request']->getName() === 'baz')
    ->values()
    ->first();
  expect($bazRequest)->toBeArray();
  /** @var array{id: int, request: ParsedRequest, dependencies: int[]} $bazRequest */
  $bazRequestId = $bazRequest['id'];
  expect($fooRequest['request'])
    ->toBeInstanceOf(ParsedRequest::class)
    ->and($fooRequest['request']->getType())
    ->toBe(ParsedRequest::TYPE_INSTALL)
    ->and($fooRequest['request']->getName())
    ->toBe('foo')
    ->and((string) $fooRequest['request']->getVersion())
    ->toBe('1.0.1')
    ->and($fooRequest['dependencies'])
    ->toBeArray()
    ->and($fooRequest['dependencies'])
    ->toMatchArray([$bazRequestId]);

  expect($bazRequest['request'])
    ->toBeInstanceOf(ParsedRequest::class)
    ->and($bazRequest['request']->getType())
    ->toBe(ParsedRequest::TYPE_INSTALL)
    ->and((string) $bazRequest['request']->getVersion())
    ->toBe('1.0.0')
    ->and($bazRequest['dependencies'])
    ->toBeArray()
    ->and($bazRequest['dependencies'])
    ->toBeEmpty();
});

it('sorts requests correctly', function () {
  $foo1 = new Package('foo', '1.0.0');
  $foo2 = new Package('foo', '1.0.1');
  $foo2->setId(1)->addDependency('baz', '1.0.0');
  $baz = new Package('baz', '1.0.0');
  $bar = new Package('bar', '1.0.0');
  $pool = new Pool();
  $pool
    ->addPackage($foo1)
    ->addPackage($foo2)
    ->addPackage($baz)
    ->addPackage($bar)
    ->ensurePackageIds();

  $requests = new ParsedRequestsCollection();
  $requests->install($foo2)->uninstall('bar')->install($baz);

  $sorter = new DependencySorter($requests, $pool);
  $sorted = $sorter->sort();

  expect($sorted)
    ->toBeInstanceOf(ParsedRequestsCollection::class)
    ->and(count($sorted))
    ->toBe(count($requests));
  $sortedRequests = $sorted->toArray();
  $sortedRequestNames = collect($sortedRequests)->pluck('name')->toArray();
  expect($sortedRequestNames)->toBe(['baz', 'foo', 'bar']);
});

it('throws an error when the requests collection is missing dependencies', function () {
  $foo1 = new Package('foo', '1.0.0');
  $foo2 = new Package('foo', '1.0.1');
  $foo2->addDependency('baz', '1.0.0')->addDependency('boo', '1.0.0');
  $baz = new Package('baz', '1.0.0');
  $pool = new Pool();
  $pool->addPackage($foo1)->addPackage($foo2)->addPackage($baz)->ensurePackageIds();

  $requests = new ParsedRequestsCollection();
  $requests->install($foo2);

  $sorter = new DependencySorter($requests, $pool);
  expect(fn() => $sorter->sort())->toThrow(
    DependencyException::class,
    'Missing dependency'
  );
});

it(
  'marks dependencies correctly when multiple packages require the same package',
  function () {
    $foo1 = new Package('foo', '1.0.0');
    $foo2 = new Package('foo', '1.0.1');
    $foo2->setId(1)->addDependency('baz', '1.0.0')->addDependency('boo', '1.0.0');
    $baz = new Package('baz', '1.0.0');
    $baz->setId(2);
    $bar = new Package('bar', '1.0.0');
    $bar->setId(3)->addDependency('baz', '1.0.0');
    $boo = new Package('boo', '1.0.0');
    $boo->setId(4)->addDependency('baz', '1.0.0');
    $pool = new Pool();
    $pool
      ->addPackage($foo1)
      ->addPackage($foo2)
      ->addPackage($baz)
      ->addPackage($bar)
      ->addPackage($boo)
      ->ensurePackageIds();

    $requests = new ParsedRequestsCollection();
    $requests->install($foo2)->install($bar)->install($baz)->install($boo);

    $sorter = new class ($requests, $pool) extends DependencySorter {
      public function gatherRequestsData(): void {
        parent::gatherRequestsData();
      }

      /**
       * @return array<array{
       *   id: int,
       *   request: ParsedRequest,
       *   dependencies: int[],
       * }>
       */
      public function getRequestsData(): array {
        return $this->requestsData;
      }
    };

    $sorter->gatherRequestsData();
    $requestsData = $sorter->getRequestsData();

    expect($requestsData)->toBeArray()->toHaveCount(count($requests));
    $fooRequest = collect($requestsData)
      ->filter(static fn($request) => $request['request']->getName() === 'foo')
      ->values()
      ->first();
    expect($fooRequest)->toBeArray();
    /** @var array{id: int, request: ParsedRequest, dependencies: int[]} $fooRequest */

    $bazRequest = collect($requestsData)
      ->filter(static fn($request) => $request['request']->getName() === 'baz')
      ->values()
      ->first();
    expect($bazRequest)->toBeArray();
    /** @var array{id: int, request: ParsedRequest, dependencies: int[]} $bazRequest */

    $barRequest = collect($requestsData)
      ->filter(static fn($request) => $request['request']->getName() === 'bar')
      ->values()
      ->first();
    expect($barRequest)->toBeArray();
    /** @var array{id: int, request: ParsedRequest, dependencies: int[]} $barRequest */

    $booRequest = collect($requestsData)
      ->filter(static fn($request) => $request['request']->getName() === 'boo')
      ->values()
      ->first();
    expect($booRequest)->toBeArray();
    /** @var array{id: int, request: ParsedRequest, dependencies: int[]} $booRequest */

    expect($fooRequest['dependencies'])
      ->toBeArray()
      ->and($fooRequest['dependencies'])
      ->toBe([$bazRequest['id'], $booRequest['id']], 'Foo should depend on Baz and Boo');
    expect($booRequest['dependencies'])
      ->toBeArray()
      ->and($booRequest['dependencies'])
      ->toBe([$bazRequest['id']], 'Boo should depend on Baz');
  }
);

it('throws error if packages not found in pool', function() {
  $requests = new ParsedRequestsCollection();
  $foo = new Package('foo', '1.0.0');
  $foo->setId(1)->addDependency('bar', '1.0.0');
  $bar = new Package('bar', '1.0.0');
  $bar->setId(2)->addDependency('baz', '1.0.0');
  $baz = new Package('baz', '1.0.0');
  $baz->setId(3);
  $pool = new Pool();
  $pool->addPackage($foo)->addPackage($bar)->ensurePackageIds();

  $requests->install($foo)->install($bar)->install($baz);
  $gatherer = new DependencySorter($requests, $pool);
  $requests = $gatherer->sort();
})->throws(DependencyException::class, 'Package not found');

it('throws an error if a request has no package ID', function() {
  $requests = new ParsedRequestsCollection();
  $foo = new Package('foo', '1.0.0');
  $pool = new Pool();
  $pool->addPackage($foo);
  $requests->addRequest(ParsedRequest::install($foo));
  $gatherer = new DependencySorter($requests, $pool);
  $requests = $gatherer->sort();
})->throws(DependencyException::class, 'Package ID not found');

it('throws an error if there\'s a request that needs a package that is installed with a wrong version and isn\'t planned for installing', function() {
  $requests = new ParsedRequestsCollection();
  $foo = new Package('foo', '1.0.0');
  $foo->setId(1)->addDependency('bar', '2.0.0');
  $bar = new Package('bar', '2.0.0');
  $bar->setId(2);
  $pool = new Pool();
  $pool->addPackage($foo)->addPackage($bar)->ensurePackageIds();
  $requests->install($foo);

  $gatherer = new DependencySorter($requests, $pool, [
    'bar' => [
      'version' => '1.0.0',
    ],
  ]);
  $requests = $gatherer->sort();
})->throws(DependencyException::class, 'Installed version does not satisfy');
