<?php

declare(strict_types=1);

namespace Demo\EdgeCases;

use Render\Autoloader\Attributes\AutoRun;

// Side effect makes this unsafe
define('UNSAFE_ARROW_LOADED', true);

class UnsafeWithArrowFn
{
    // Arrow function with call inside - should NOT make token analyzer miss the real method
    private static \Closure $transform;

    public static function setupTransform(): void
    {
        self::$transform = fn($x) => strtoupper($x);
    }

    #[AutoRun(priority: 31)]
    public static function init(): void
    {
        echo "UnsafeWithArrowFn::init() called (priority 31)\n";
    }
}
