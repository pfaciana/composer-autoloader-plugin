<?php

declare(strict_types=1);

namespace Demo\EdgeCases;

use Render\Autoloader\Attributes\AutoRun;

// Arrow function at TOP LEVEL with function call in body
// OLD plugin: marks call() as unsafe (false positive)
// NEW plugin: correctly detects arrow fn body, marks as safe
$transformer = fn($x) => strtoupper($x);

class SafeArrowFnTopLevel
{
    #[AutoRun(priority: 35)]
    public static function init(): void
    {
        echo "SafeArrowFnTopLevel::init() called (priority 35)\n";
    }
}
