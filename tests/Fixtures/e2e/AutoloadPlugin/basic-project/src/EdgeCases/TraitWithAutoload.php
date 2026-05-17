<?php

declare(strict_types=1);

namespace Demo\EdgeCases;

use Render\Autoloader\Attributes\AutoRun;

trait TraitWithAutoload
{
    #[AutoRun(priority: 70)]
    public static function traitMethod(): void
    {
        echo "TraitWithAutoload::traitMethod() called (priority 70)\n";
    }
}
