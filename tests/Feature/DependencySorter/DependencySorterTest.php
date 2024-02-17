<?php

use SimpleDep\DependencySorter\DependencySorter;
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
