<?php

declare(strict_types=1);

namespace Demo\EdgeCases;

use Render\Autoloader\Attributes\AutoRun;

class NegativePriority
{
    #[AutoRun(priority: -100)]
    public static function veryEarly(): void
    {
        echo "NegativePriority::veryEarly() called (priority -100)\n";
    }

    #[AutoRun(priority: -50)]
    public static function earlyish(): void
    {
        echo "NegativePriority::earlyish() called (priority -50)\n";
    }
}
