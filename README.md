# Simple Dependency Tree Solver

This is a simple solver for install/uninstall requests and their dependencies.

## Purpose

The purpose of this project is to provide a simple dependency tree solver for package management tools like internal marketplaces. It is not intended to be a full-featured package manager like Composer or NPM.

## How to run

The main class is the `Solver` class. Its constructor method takes a `Pool` of packages, a `RequestsCollection` of requests and an array of installed packages. The `solve` method returns an array of `Operation` objects. Each `Operation` object has a `type` property that can be `install` or `uninstall`, a `name` property with the package name and a `version` property with the package version.

```php
<?php
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

$installed = [
  'boo' => [
    'version' => '1.0.0',
  ],
  'bee' => [
    'version' => '1.0.0',
  ],
];

$solver = new Solver($pool, $requests, $installed);
$solution = $solver->solve();
// print_r($solution);

// Convert the solution to an array of arrays.
$solutionArray = array_map(
  fn(Operation $operation) => $operation->toArray(),
  $solution
);
echo (json_encode($solutionArray, JSON_PRETTY_PRINT));
```

The code above will output the following:

```json
{
  "baz": {
    "type": "install",
    "name": "baz",
    "version": "1.0.1",
    "requiredBy": [{
        "type": "install",
        "name": "bar",
        "version": "1.0.0"
      },
      {
        "type": "install",
        "name": "foo",
        "version": "1.0.0"
      }
    ]
  },
  "bar": {
    "type": "install",
    "name": "bar",
    "version": "1.0.0",
    "requiredBy": [{
      "type": "install",
      "name": "foo",
      "version": "1.0.0"
    }]
  },
  "foo": {
    "type": "install",
    "name": "foo",
    "version": "1.0.0",
    "requiredBy": []
  },
  "boo": {
    "type": "install",
    "name": "boo",
    "version": "1.0.7",
    "requiredBy": []
  },
  "bee": {
    "type": "uninstall",
    "name": "bee",
    "version": null,
    "requiredBy": [{
      "type": "install",
      "name": "boo",
      "version": "1.0.7"
    }]
  }
}
```
