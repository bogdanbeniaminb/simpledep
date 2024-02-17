<?php

use SimpleDep\Requests\Request;
use z4kn4fein\SemVer\Constraints\Constraint;

it('handles install requests', function () {
  $request = new Request(Request::TYPE_INSTALL, 'foo');
  expect($request)
    ->toBeInstanceOf(Request::class)
    ->and($request->getType())
    ->toBe(Request::TYPE_INSTALL)
    ->and($request->getName())
    ->toBe('foo')
    ->and($request->getVersionConstraint())
    ->toBeNull();

  $request = new Request(Request::TYPE_INSTALL, 'foo', '>=1.0.0');
  expect($request->getVersionConstraint())
    ->toBeInstanceOf(Constraint::class)
    ->and((string) $request->getVersionConstraint())
    ->toBe('>=1.0.0');

  $request = new Request(Request::TYPE_INSTALL, 'foo', Constraint::parse('>=1.0.0'));
  expect($request->getVersionConstraint())
    ->toBeInstanceOf(Constraint::class)
    ->and((string) $request->getVersionConstraint())
    ->toBe('>=1.0.0');
});

it('handles uninstall requests', function () {
  $request = new Request(Request::TYPE_UNINSTALL, 'foo');
  expect($request->getType())
    ->toBe(Request::TYPE_UNINSTALL)
    ->and($request->getName())
    ->toBe('foo')
    ->and($request->getVersionConstraint())
    ->toBeNull();
});

it('handles update requests', function () {
  $request = new Request(Request::TYPE_UPDATE, 'foo');
  expect($request->getType())
    ->toBe(Request::TYPE_UPDATE)
    ->and($request->getName())
    ->toBe('foo')
    ->and($request->getVersionConstraint())
    ->toBeNull();

  $request = new Request(Request::TYPE_UPDATE, 'foo', '>=1.0.0');
  expect($request->getVersionConstraint())
    ->toBeInstanceOf(Constraint::class)
    ->and((string) $request->getVersionConstraint())
    ->toBe('>=1.0.0');
});
