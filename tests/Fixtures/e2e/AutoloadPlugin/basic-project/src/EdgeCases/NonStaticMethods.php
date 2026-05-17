<?php

declare(strict_types=1);

namespace Demo\EdgeCases;

use Render\Autoloader\Attributes\AutoRun;

class NonStaticMethods
{
    // Non-static method with Autoload - should be detected but may fail at runtime
    // depending on how the plugin handles it
    #[AutoRun(priority: 95)]
    public function instanceMethod(): void
    {
        echo "NonStaticMethods::instanceMethod() called (priority 95)\n";
    }

    #[AutoRun(priority: 96)]
    public static function staticMethod(): void
    {
        echo "NonStaticMethods::staticMethod() called (priority 96)\n";
    }
}
