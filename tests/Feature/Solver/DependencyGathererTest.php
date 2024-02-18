<?php

use SimpleDep\Package\Package;
use SimpleDep\Pool\Pool;
use SimpleDep\Requests\ParsedRequest;
use SimpleDep\Requests\ParsedRequestsCollection;
use SimpleDep\Solver\DependencyGatherer;

it('gathers dependencies', function () {
  $requests = new ParsedRequestsCollection();
  $foo = new Package('foo', '1.0.0');
  $foo->setId(1)->addDependency('bar', '1.0.0');
  $bar = new Package('bar', '1.0.0');
  $bar->setId(2)->addDependency('baz', '1.0.0');
  $baz = new Package('baz', '1.0.0');
  $pool = new Pool();
  $pool->addPackage($foo)->addPackage($bar)->addPackage($baz)->ensurePackageIds();

  $requests->install($foo)->install($bar)->install($baz);
  $gatherer = new DependencyGatherer($requests, $pool);
  $requests = $gatherer->gatherDependencies();

  expect(count($requests))->toBe(3);

  $getRequestIds = fn(array $requests) => array_map(
    fn(ParsedRequest $request) => $request->getPackageId(),
    $requests
  );

  $fooRequest = $requests->find(
    fn(ParsedRequest $request) => $request->getName() === 'foo'
  );
  expect($fooRequest)->not->toBeNull();
  $barRequest = $requests->find(
    fn(ParsedRequest $request) => $request->getName() === 'bar'
  );
  expect($barRequest)->not->toBeNull();
  expect($getRequestIds($barRequest->getRequiredBy()))->toBe(
    [$foo->getId()],
    'bar is required by foo'
  );
  $bazRequest = $requests->find(
    fn(ParsedRequest $request) => $request->getName() === 'baz'
  );
  expect($getRequestIds($bazRequest->getRequiredBy()))->toBe(
    [$foo->getId(), $bar->getId()],
    'baz is required by foo and bar'
  );
});

it('gathers uninstall dependencies', function () {
  $foo = (new Package('foo', '1.0.0'))->addConflict('bar', '>=1.0.0');
  $bar = new Package('bar', '1.0.0');
  $pool = (new Pool())->addPackage($foo)->addPackage($bar)->ensurePackageIds();
  $requests = (new ParsedRequestsCollection())->install($foo)->uninstall('bar');
  $installed = [
    'bar' => [
      'version' => '1.0.0',
    ],
  ];

  $gatherer = new DependencyGatherer($requests, $pool, $installed);
  $requests = $gatherer->gatherDependencies();
  expect(count($requests))->toBe(2);

  $getRequestIds = fn(array $requests) => array_map(
    fn(ParsedRequest $request) => $request->getPackageId(),
    $requests
  );

  $fooRequest = $requests->find(
    fn(ParsedRequest $request) => $request->getName() === 'foo'
  );
  expect($fooRequest)->not->toBeNull();
  $barRequest = $requests->find(
    fn(ParsedRequest $request) => $request->getName() === 'bar'
  );
  expect($barRequest)->not->toBeNull();
  expect($getRequestIds($barRequest->getRequiredBy()))->toBe(
    [$foo->getId()],
    'bar is required by foo'
  );
});
