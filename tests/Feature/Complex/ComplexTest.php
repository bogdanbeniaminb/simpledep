<?php

use SimpleDep\Package\Package;
use SimpleDep\Pool\Pool;
use SimpleDep\Requests\RequestsCollection;
use SimpleDep\Solver\Solver;
use z4kn4fein\SemVer\Version;

it('uses simpledep to process a request', function (bool $fixed, int $expectedCount) {
  /** @var array{
   *   modules: array<array{
   *     name: non-empty-string,
   *     version: non-empty-string,
   *   }>,
   * } $moduleVersions */
  $moduleVersions = json_decode(
    file_get_contents(__DIR__ . '/module-versions.json') ?: '[]',
    true
  );
  /** @var array<non-empty-string, non-empty-string> $installedVersions */
  $installedVersions = json_decode(
    file_get_contents(__DIR__ . '/installed-versions.json') ?: '[]',
    true
  );
  /**
   * @var array<array{
   *   id: int,
   *   name: non-empty-string,
   *   versions: array<array{
   *     id: int,
   *     version: non-empty-string,
   *     dependencies: array<array{
   *       name: non-empty-string,
   *       type: non-empty-string,
   *       version_constraint: non-empty-string,
   *     }>,
   *   }>,
   * }> $modules */
  $modules = json_decode(file_get_contents(__DIR__ . '/modules.json') ?: '[]', true);

  $pool = new Pool();
  foreach ($modules as $module) {
    foreach ($module['versions'] as $version) {
      // if (!$version['compatible_with_environment']) {
      //   continue;
      // }

      $package = new Package($module['name'], $version['version']);
      $package->setId($version['id']);

      foreach ($version['dependencies'] as $dependency) {
        if ($dependency['type'] != 'module') {
          continue;
        }

        $package->addDependency($dependency['name'], $dependency['version_constraint']);
      }

      $pool->addPackage($package);
    }
  }

  $installed = [];
  foreach ($installedVersions as $name => $installedVersion) {
    $installed[$name] = [
      'version' => Version::parse($installedVersion),
    ];
  }

  $requests = new RequestsCollection();
  $moduleVersions = $moduleVersions['modules'];
  foreach ($moduleVersions as $moduleVersion) {
    $requests->install(
      $moduleVersion['name'],
      ($fixed ? '=' : '^') . $moduleVersion['version']
    );
  }

  $solver = new Solver($pool, $requests, $installed);
  $solution = $solver->solve();
  expect($solution)->toBeArray()->toHaveCount($expectedCount);
})->with([
  'fixed' => [true, 5],
  'caret' => [false, 2],
]);
