<?php

declare(strict_types=1);

namespace Demo;

use Render\Autoloader\Attributes\AutoRun;

class Bootstrap
{
    #[AutoRun(priority: 5)]
    public static function early(): void
    {
        echo "Bootstrap::early() called (priority 5)\n";
    }

    #[AutoRun]
    public static function init(): void
    {
        echo "Bootstrap::init() called (priority 10)\n";
    }

    #[AutoRun(priority: 99)]
	#[Other]
    public static function late(): void
    {
        echo "Bootstrap::late() called (priority 99)\n";
    }

    public static function notAutoloaded(): void
    {
        echo "This should NOT be called automatically\n";
    }
}
