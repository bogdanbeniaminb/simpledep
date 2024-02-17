<?php

use SimpleDep\Package\Package;
use SimpleDep\Requests\ParsedRequest;
use SimpleDep\Requests\ParsedRequestsCollection;
use SimpleDep\Requests\Request;

it('instantiates a requests collection', function () {
  $requests = new ParsedRequestsCollection();
  expect($requests)
    ->toBeInstanceOf(ParsedRequestsCollection::class)
    ->and($requests->getRequests())
    ->toBe([]);
});

it('adds requests to the collection and retrieves them', function () {
  $package1 = (new Package('foo', '1.0.0'))->setId(1);
  $package2 = (new Package('bar', '1.0.0'))->setId(2);

  $requests = new ParsedRequestsCollection();
  $requests
    ->install($package1)
    ->addRequest(new Request(Request::TYPE_INSTALL, 'bar', '1.0.0'))
    ->uninstall('baz');
  expect(count($requests))
    ->toBe(3)
    ->and($requests->toArray())
    ->toMatchArray([
      [
        'type' => ParsedRequest::TYPE_INSTALL,
        'name' => 'foo',
        'versionConstraint' => '=1.0.0',
        'packageId' => 1,
        'version' => '1.0.0',
      ],
      [
        'type' => ParsedRequest::TYPE_INSTALL,
        'name' => 'bar',
        'versionConstraint' => '=1.0.0',
        'packageId' => null,
        'version' => null,
      ],
      [
        'type' => ParsedRequest::TYPE_UNINSTALL,
        'name' => 'baz',
        'versionConstraint' => null,
        'packageId' => null,
        'version' => null,
      ],
    ]);
});

it('refuses to install packages without ID', function () {
  $requests = new ParsedRequestsCollection();
  $package = new Package('foo', '1.0.0');
  expect(fn() => $requests->install($package))->toThrow(
    new RuntimeException('The package must have an ID')
  );
});
