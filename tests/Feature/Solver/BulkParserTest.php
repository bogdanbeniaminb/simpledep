<?php

use SimpleDep\Package\Package;
use SimpleDep\Solver\BulkParser;
use SimpleDep\Pool\Pool;
use SimpleDep\Requests\ParsedRequestsCollection;
use SimpleDep\Requests\Request;
use SimpleDep\Requests\RequestsCollection;
use SimpleDep\Solver\Exceptions\ParserException;

it('instantiates a bulk parser', function () {
  $parser = new BulkParser(new Pool(), new RequestsCollection());
  expect($parser)->toBeInstanceOf(BulkParser::class);
});

it('throws an error if trying to install a missing package', function () {
  $requests = (new RequestsCollection())->install('foo', '>=1.0.0');
  $parser = new BulkParser(new Pool(), $requests, [
    'boo' => [
      'version' => '1.0.0',
    ],
    'bar' => [
      'version' => '1.0.0',
    ],
  ]);

  expect(fn() => $parser->parse())->toThrow(ParserException::class);
});

it("doesn\'t accept invalid operations", function () {
  // @phpstan-ignore-next-line
  $requests = (new RequestsCollection())->addRequest(new Request(10, 'boo', '>=1.0.0'));
  $parser = new BulkParser(new Pool(), $requests, []);

  expect(fn() => $parser->parse())->toThrow(ParserException::class);
});

it('parses dependencies', function () {
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
  $parser = new BulkParser($pool, $requests, [
    'boo' => [
      'version' => '1.0.0',
    ],
  ]);

  $solutions = $parser->parse();
  expect($solutions)->toBeArray()->and(count($solutions))->toBeGreaterThan(0);

  // Check if the solutions contain the expected requests.
  foreach ($solutions as $solution) {
    expect($solution)
      ->toBeInstanceOf(ParsedRequestsCollection::class)
      ->and($solution->contains('foo'))
      ->toBeTrue()
      ->and($solution->contains('boo'))
      ->toBeTrue()
      ->and($solution->contains('baz'))
      ->toBeTrue()
      ->and($solution->contains('bar'))
      ->toBeTrue()
      ->and($solution->contains('bee'))
      ->toBeTrue();
  }
});

it('parses conflicting dependencies', function () {
  $foo1 = (new Package('foo', '1.0.0'))->addLink('require', 'bar', '>=1.0.0');
  $foo2 = (new Package('foo', '1.0.1'))->addLink('require', 'bar', '>=1.0.1');
  $bar1 = (new Package('bar', '1.0.0'));
  $bar2 = (new Package('bar', '1.0.1'));
  $boo1 = (new Package('boo', '1.0.5'))->addLink('require', 'bar', '1.0.0');
  $boo2 = (new Package('boo', '1.0.7'))->addLink('require', 'bar', '1.0.1');
  $pool = (new Pool())
    ->addPackage($foo1)
    ->addPackage($foo2)
    ->addPackage($bar1)
    ->addPackage($bar2)
    ->addPackage($boo1)
    ->addPackage($boo2);
  $requests = (new RequestsCollection())
    ->install('foo', '1.0.1')
    ->update('boo');

  $parser = new BulkParser($pool, $requests, [
    'boo' => [
      'version' => '1.0.0',
    ],
  ]);

  $solutions = $parser->parse();
  expect($solutions)->toBeArray()->and(count($solutions))->toBeGreaterThan(0);
});
