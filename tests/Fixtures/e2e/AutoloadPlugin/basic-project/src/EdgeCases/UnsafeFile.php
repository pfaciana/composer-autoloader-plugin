<?php

declare(strict_types=1);

namespace Demo\EdgeCases;

use Render\Autoloader\Attributes\AutoRun;

// This file has side effects - should use token scanning only
echo "UnsafeFile loaded (side effect)\n";

class UnsafeFile
{
    #[AutoRun(priority: 30)]
    public static function init(): void
    {
        echo "UnsafeFile::init() called (priority 30)\n";
    }
}
