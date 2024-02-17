<?php

declare(strict_types=1);

namespace SimpleDep\Package;

class Link {
  public const TYPE_REQUIRE = 1;
  public const TYPE_REQUIRE_DEV = 2;
  public const TYPE_PROVIDE = 4;
  public const TYPE_REPLACE = 8;
  public const TYPE_CONFLICT = 16;
  public const TYPE_DEV_REQUIRE = 32;
}
