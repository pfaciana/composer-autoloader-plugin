<?php

declare(strict_types=1);

namespace Demo\EdgeCases;

use Render\Autoloader\Attributes\AutoRun;

class SamePriority
{
    #[AutoRun(priority: 10)]
    public static function alpha(): void
    {
        echo "SamePriority::alpha() called (priority 10)\n";
    }

    #[AutoRun(priority: 10)]
    public static function beta(): void
    {
        echo "SamePriority::beta() called (priority 10)\n";
    }

    #[AutoRun(priority: 10)]
    public static function gamma(): void
    {
        echo "SamePriority::gamma() called (priority 10)\n";
    }
}
