<?php

declare(strict_types=1);

namespace Demo\EdgeCases;

use Render\Autoloader\Attributes\AutoRun;
use Attribute;

#[Attribute]
class CustomAttr {}

class MultipleAttributes
{
    // Multiple attributes - should use the Autoload one
    #[CustomAttr]
    #[AutoRun(priority: 90)]
    public static function withMultiple(): void
    {
        echo "MultipleAttributes::withMultiple() called (priority 90)\n";
    }

    // Attribute on class should NOT leak to methods
    // This is tested by having no attribute on this method
    public static function noAttribute(): void
    {
        echo "MultipleAttributes::noAttribute() - should NOT be called\n";
    }
}
