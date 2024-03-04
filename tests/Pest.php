<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

// uses(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

use SimpleDep\Package\Package;
use SimpleDep\Pool\Pool;

expect()->extend('toBeOne', function () {
  // return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * @param array<array{
 *   name: non-empty-string,
 *   version: non-empty-string,
 *   links?: array<array{
 *     type: key-of<Package::SUPPORTED_LINK_TYPES>,
 *     name: non-empty-string,
 *     versionConstraint: non-empty-string|null,
 *    }>,
 * }> $poolData
 * @return Pool
 */
function createPoolFromArray(array $poolData): Pool {
  $pool = new Pool();

  foreach ($poolData as $packageData) {
    $package = new Package($packageData['name'], $packageData['version']);
    if (!empty($packageData['links'])) {
      foreach ($packageData['links'] as $linkData) {
        $package->addLink(
          $linkData['type'],
          $linkData['name'],
          $linkData['versionConstraint']
        );
      }
    }
    $pool->addPackage($package);
  }

  return $pool;
}
