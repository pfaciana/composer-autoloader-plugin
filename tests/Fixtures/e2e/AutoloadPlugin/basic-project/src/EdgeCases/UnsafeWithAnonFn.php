<?php

declare(strict_types=1);

namespace Demo\EdgeCases;

use Render\Autoloader\Attributes\AutoRun;

// Side effect - use a real function
define('UNSAFE_ANON_FN_LOADED', true);

class UnsafeWithAnonFn
{
    // Anonymous function with calls inside body - should NOT make file appear unsafe
    // Wait, no - THIS file IS unsafe because of the function call above
    // The anon fn test is about not FALSE-POSITIVE detecting calls inside anon fn bodies

    public static function getCallback(): callable
    {
        return function($x) {
            return json_encode($x);
        };
    }

    #[AutoRun(priority: 34)]
    public static function init(): void
    {
        echo "UnsafeWithAnonFn::init() called (priority 34)\n";
    }
}
