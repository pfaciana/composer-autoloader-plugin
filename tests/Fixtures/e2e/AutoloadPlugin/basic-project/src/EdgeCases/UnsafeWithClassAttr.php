<?php

declare(strict_types=1);

namespace Demo\EdgeCases;

use Render\Autoloader\Attributes\AutoRun;
use Attribute;

// Side effect
define('UNSAFE_CLASS_ATTR_LOADED', true);

#[Attribute]
class MarkerAttr {}

// Class has attribute - should NOT leak to methods
#[MarkerAttr]
class UnsafeWithClassAttr
{
    // This method has NO Autoload attribute - should NOT be included
    public static function noAttr(): void
    {
        echo "UnsafeWithClassAttr::noAttr() - should NOT be called\n";
    }

    #[AutoRun(priority: 32)]
    public static function withAttr(): void
    {
        echo "UnsafeWithClassAttr::withAttr() called (priority 32)\n";
    }
}
