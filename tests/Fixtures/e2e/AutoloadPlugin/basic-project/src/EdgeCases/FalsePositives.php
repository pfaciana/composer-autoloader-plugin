<?php

declare(strict_types=1);

namespace Demo\EdgeCases;

use Render\Autoloader\Attributes\AutoRun;

class FalsePositives
{
    // Arrow functions with calls inside should NOT make file unsafe
    private static \Closure $callback;

    public static function setup(): void
    {
        self::$callback = fn($x) => strtoupper($x);
    }

    // Anonymous functions with calls inside should NOT make file unsafe
    public static function getFormatter(): callable
    {
        return function ($value) {
            return json_encode($value);
        };
    }

    // String content that looks like code should NOT make file unsafe
    public const CODE_EXAMPLE = 'echo "hello"; some_function();';

    // Comments with attribute syntax should be ignored
    // #[AutoRun(priority: 999)]
    // public static function commented(): void {}

    /**
     * DocBlock with #[AutoRun] should be ignored
     */
    #[AutoRun(80)]
    public static function actualMethod(): void
    {
        echo "FalsePositives::actualMethod() called (priority 80)\n";
    }
}
