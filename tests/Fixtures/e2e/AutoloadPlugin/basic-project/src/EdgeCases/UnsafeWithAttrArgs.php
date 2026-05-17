<?php

declare(strict_types=1);

namespace Demo\EdgeCases;

use Render\Autoloader\Attributes\AutoRun;

// Side effect
define('UNSAFE_ATTR_ARGS_LOADED', true);

class UnsafeWithAttrArgs
{
    // Attribute with args - the (5) should NOT trigger "function call" detection
    #[AutoRun(5)]
    public static function positional(): void
    {
        echo "UnsafeWithAttrArgs::positional() called (priority 5 - but will be after others due to file order)\n";
    }

    #[AutoRun(priority: 33)]
    public static function named(): void
    {
        echo "UnsafeWithAttrArgs::named() called (priority 33)\n";
    }
}
