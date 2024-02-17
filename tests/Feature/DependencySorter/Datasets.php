<?php

dataset('simple_dependency_sorter_1', [
  'no dependencies' => [
    'items' => [
      [
        'id' => 1,
        'dependencies' => [],
      ],
      [
        'id' => 2,
        'dependencies' => [],
      ],
    ],
    'expected' => [
      [
        'id' => 1,
        'dependencies' => [],
      ],
      [
        'id' => 2,
        'dependencies' => [],
      ],
    ],
  ],
  'one dependency, ordered already' => [
    'items' => [
      [
        'id' => 1,
        'dependencies' => [],
      ],
      [
        'id' => 2,
        'dependencies' => [1],
      ],
    ],
    'expected' => [
      [
        'id' => 1,
        'dependencies' => [],
      ],
      [
        'id' => 2,
        'dependencies' => [1],
      ],
    ],
  ],
  'one dependency, unordered' => [
    'items' => [
      [
        'id' => 1,
        'dependencies' => [2],
      ],
      [
        'id' => 2,
        'dependencies' => [],
      ],
    ],
    'expected' => [
      [
        'id' => 2,
        'dependencies' => [],
      ],
      [
        'id' => 1,
        'dependencies' => [2],
      ],
    ],
  ],
  'two dependencies, unordered' => [
    'items' => [
      [
        'id' => 1,
        'dependencies' => [2],
      ],
      [
        'id' => 2,
        'dependencies' => [3],
      ],
      [
        'id' => 3,
        'dependencies' => [],
      ],
    ],
    'expected' => [
      [
        'id' => 3,
        'dependencies' => [],
      ],
      [
        'id' => 2,
        'dependencies' => [3],
      ],
      [
        'id' => 1,
        'dependencies' => [2],
      ],
    ],
  ],
  'two dependencies, one already ordered' => [
    'items' => [
      [
        'id' => 1,
        'dependencies' => [2],
      ],
      [
        'id' => 3,
        'dependencies' => [],
      ],
      [
        'id' => 2,
        'dependencies' => [3],
      ],
    ],
    'expected' => [
      [
        'id' => 3,
        'dependencies' => [],
      ],
      [
        'id' => 2,
        'dependencies' => [3],
      ],
      [
        'id' => 1,
        'dependencies' => [2],
      ],
    ],
  ],
]);

dataset('simple_dependency_sorter_circular', [
  'simple circular dependency' => [
    'items' => [
      [
        'id' => 1,
        'dependencies' => [2],
      ],
      [
        'id' => 2,
        'dependencies' => [1],
      ],
    ],
    'expected' => [
      [
        'id' => 2,
        'dependencies' => [1],
      ],
      [
        'id' => 1,
        'dependencies' => [2],
      ],
    ],
  ],
  'complex circular dependency' => [
    'items' => [
      [
        'id' => 1,
        'dependencies' => [2],
      ],
      [
        'id' => 2,
        'dependencies' => [4],
      ],
      [
        'id' => 3,
        'dependencies' => [1],
      ],
      [
        'id' => 4,
        'dependencies' => [3],
      ],
    ],
    'expected' => [
      [
        'id' => 3,
        'dependencies' => [1],
      ],
      [
        'id' => 4,
        'dependencies' => [3],
      ],
      [
        'id' => 2,
        'dependencies' => [4],
      ],
      [
        'id' => 1,
        'dependencies' => [2],
      ],
    ],
  ],
]);
