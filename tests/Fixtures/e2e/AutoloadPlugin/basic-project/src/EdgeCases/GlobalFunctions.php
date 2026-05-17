<?php

declare(strict_types=1);

use Render\Autoloader\Attributes\AutoRun;

// No namespace - global functions

#[AutoRun(priority: 40)]
function global_init(): void
{
    echo "global_init() called (priority 40)\n";
}

#[AutoRun(priority: 41, check: false)]
function global_setup(): void
{
    echo "global_setup() called (priority 41)\n";
}
