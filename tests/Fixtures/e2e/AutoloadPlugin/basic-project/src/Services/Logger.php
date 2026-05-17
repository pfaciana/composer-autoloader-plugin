<?php

declare(strict_types=1);

namespace Demo\Services;

use Render\Autoloader\Attributes\AutoRun;

class Logger
{
    #[AutoRun(priority: 2)]
    public static function register(): void
    {
        echo "Logger::register() called (priority 2)\n";
    }
}
