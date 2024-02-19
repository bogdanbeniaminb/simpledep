<?php

use SimpleDep\Requests\Request;
use SimpleDep\Requests\RequestsCollection;
use z4kn4fein\SemVer\Constraints\Constraint;

it('instantiates a requests collection', function () {
  $requests = new RequestsCollection();
  expect($requests)
    ->toBeInstanceOf(RequestsCollection::class)
    ->and($requests->getRequests())
    ->toBe([]);
});

it('adds requests to the collection and retrieves them', function () {
  $requests = new RequestsCollection();
  $requests
    ->install('foo', Constraint::parse('>=1.0.0'))
    ->update('bar')
    ->uninstall('baz')
    ->install('foo', '>=2.0.0')
    ->install('baz')
    // @phpstan-ignore-next-line
    ->addRequest(new Request(10, 'boo', '>=1.0.0'));
  expect(count($requests))
    ->toBe(6)
    ->and($requests->toArray())
    ->toMatchArray([
      [
        'type' => Request::TYPE_INSTALL,
        'name' => 'foo',
        'versionConstraint' => '>=1.0.0',
      ],
      ['type' => Request::TYPE_UPDATE, 'name' => 'bar', 'versionConstraint' => null],
      ['type' => Request::TYPE_UNINSTALL, 'name' => 'baz', 'versionConstraint' => null],
      [
        'type' => Request::TYPE_INSTALL,
        'name' => 'foo',
        'versionConstraint' => '>=2.0.0',
      ],
      [
        'type' => Request::TYPE_INSTALL,
        'name' => 'baz',
        'versionConstraint' => '>=0.0.0',
      ],
    ])
    // @phpstan-ignore-next-line
    ->and($requests->first()->toArray())
    ->toMatchArray([
      'type' => Request::TYPE_INSTALL,
      'name' => 'foo',
      'versionConstraint' => '>=1.0.0',
    ]);
});

it('can slice the collection', function () {
  $requests = new RequestsCollection();
  $requests
    ->install('foo', Constraint::parse('>=1.0.0'))
    ->update('bar')
    ->uninstall('baz')
    ->install('foo', Constraint::parse('>=2.0.0'))
    ->install('baz', Constraint::parse('>=1.0.0'));
  expect($requests->slice(1, 2)->toArray())->toMatchArray([
    ['type' => Request::TYPE_UPDATE, 'name' => 'bar', 'versionConstraint' => null],
    ['type' => Request::TYPE_UNINSTALL, 'name' => 'baz', 'versionConstraint' => null],
  ]);
});

it('can merge two collections', function () {
  $first = new RequestsCollection();
  $first->install('foo', Constraint::parse('>=1.0.0'))->update('bar')->uninstall('baz');
  $second = new RequestsCollection();
  $second
    ->install('foo', Constraint::parse('>=2.0.0'))
    ->install('baz', Constraint::parse('>=1.0.0'));
  $merged = $first->merge($second);
  expect($merged->toArray())->toMatchArray([
    ['type' => Request::TYPE_INSTALL, 'name' => 'foo', 'versionConstraint' => '>=1.0.0'],
    ['type' => Request::TYPE_UPDATE, 'name' => 'bar', 'versionConstraint' => null],
    ['type' => Request::TYPE_UNINSTALL, 'name' => 'baz', 'versionConstraint' => null],
    ['type' => Request::TYPE_INSTALL, 'name' => 'foo', 'versionConstraint' => '>=2.0.0'],
    ['type' => Request::TYPE_INSTALL, 'name' => 'baz', 'versionConstraint' => '>=1.0.0'],
  ]);
});

it('doesn\'t merge with wrong objects', function () {
  $requests = new RequestsCollection();
  expect(fn() => $requests->merge(new RequestsCollection()))->not->toThrow(
    InvalidArgumentException::class
  );
  // @phpstan-ignore-next-line
  expect(fn() => $requests->merge(new stdClass()))->toThrow(
    InvalidArgumentException::class,
    'must be an instance of'
  );
  // @phpstan-ignore-next-line
  expect(fn() => $requests->merge(new ArrayObject()))->toThrow(
    InvalidArgumentException::class,
    'must be an instance of'
  );
  // @phpstan-ignore-next-line
  expect(fn() => $requests->merge([]))->toThrow(
    InvalidArgumentException::class,
    'must be an instance of'
  );
});

it('can act as a iterator', function () {
  $requests = new RequestsCollection();
  $requests
    ->install('foo', Constraint::parse('>=1.0.0'))
    ->update('bar')
    ->uninstall('baz')
    ->install('foo', '>=2.0.0')
    ->install('baz')
    ->update('boo', '>=1.0.0');
  $result = [];
  foreach ($requests as $request) {
    $result[] = $request->toArray();
  }
  expect($result)->toMatchArray([
    ['type' => Request::TYPE_INSTALL, 'name' => 'foo', 'versionConstraint' => '>=1.0.0'],
    ['type' => Request::TYPE_UPDATE, 'name' => 'bar', 'versionConstraint' => null],
    ['type' => Request::TYPE_UNINSTALL, 'name' => 'baz', 'versionConstraint' => null],
    ['type' => Request::TYPE_INSTALL, 'name' => 'foo', 'versionConstraint' => '>=2.0.0'],
    ['type' => Request::TYPE_INSTALL, 'name' => 'baz', 'versionConstraint' => '>=0.0.0'],
    ['type' => Request::TYPE_UPDATE, 'name' => 'boo', 'versionConstraint' => '>=1.0.0'],
  ]);
});

it('can filter requests', function () {
  $requests = new RequestsCollection();
  $requests
    ->install('foo', Constraint::parse('>=1.0.0'))
    ->update('bar')
    ->uninstall('baz')
    ->install('foo', '>=2.0.0')
    ->install('baz')
    ->update('boo', '>=1.0.0');
  $filtered = $requests->filter(fn(Request $request) => $request->getName() === 'foo');
  expect($filtered->toArray())->toMatchArray([
    ['type' => Request::TYPE_INSTALL, 'name' => 'foo', 'versionConstraint' => '>=1.0.0'],
    ['type' => Request::TYPE_INSTALL, 'name' => 'foo', 'versionConstraint' => '>=2.0.0'],
  ]);
});

it('can remove duplicates', function () {
  $requests = new RequestsCollection();
  $requests
    ->install('foo', Constraint::parse('>=1.0.0'))
    ->update('bar')
    ->uninstall('baz')
    ->install('foo', '>=2.0.0')
    ->install('baz')
    ->update('boo', '>=1.0.0');
  $requests
    ->install('foo', Constraint::parse('>=1.0.0'))
    ->update('bar')
    ->uninstall('baz')
    ->install('foo', '>=2.0.0')
    ->install('baz')
    ->update('boo', '>=1.0.0');
  expect($requests->toArray())->toMatchArray([
    ['type' => Request::TYPE_INSTALL, 'name' => 'foo', 'versionConstraint' => '>=1.0.0'],
    ['type' => Request::TYPE_UPDATE, 'name' => 'bar', 'versionConstraint' => null],
    ['type' => Request::TYPE_UNINSTALL, 'name' => 'baz', 'versionConstraint' => null],
    ['type' => Request::TYPE_INSTALL, 'name' => 'foo', 'versionConstraint' => '>=2.0.0'],
    ['type' => Request::TYPE_INSTALL, 'name' => 'baz', 'versionConstraint' => '>=0.0.0'],
    ['type' => Request::TYPE_UPDATE, 'name' => 'boo', 'versionConstraint' => '>=1.0.0'],
  ]);
});

it('can check if requests exist by their name', function() {
  $requests = new RequestsCollection();
  $requests
    ->install('foo', Constraint::parse('>=1.0.0'))
    ->update('bar')
    ->uninstall('baz');
  expect($requests->contains('foo'))
    ->toBeTrue()
    ->and($requests->contains('bar'))
    ->toBeTrue()
    ->and($requests->contains('baz'))
    ->toBeTrue()
    ->and($requests->contains('boo'))
    ->toBeFalse();
});

it('can find requests with a callback', function () {
  $requests = new RequestsCollection();
  $requests
    ->install('foo', Constraint::parse('>=1.0.0'))
    ->update('bar')
    ->uninstall('baz');
  $request = $requests->find(fn(Request $request) => $request->getName() === 'bar');
  /** @var Request $request */
  expect($request->getName())->toBe('bar');

  $request = $requests->find(fn(Request $request) => $request->getName() === 'boo');
  expect($request)->toBeNull();
});
