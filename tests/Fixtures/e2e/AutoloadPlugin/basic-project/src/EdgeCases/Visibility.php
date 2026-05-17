<?php

declare(strict_types=1);

namespace Demo\EdgeCases;

use Render\Autoloader\Attributes\AutoRun;

class Visibility
{
    #[AutoRun(priority: 20)]
    public static function publicMethod(): void
    {
        echo "Visibility::publicMethod() called (priority 20)\n";
    }

    #[AutoRun(priority: 21)]
    protected static function protectedMethod(): void
    {
        echo "Visibility::protectedMethod() called (priority 21)\n";
    }

    #[AutoRun(priority: 22)]
    private static function privateMethod(): void
    {
        echo "Visibility::privateMethod() called (priority 22)\n";
    }
}
