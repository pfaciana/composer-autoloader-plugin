<?php

declare(strict_types=1);

namespace Demo;

use Render\Autoloader\Attributes\AutoRun;

#[AutoRun(priority: 50)]
function middle_thing(): void
{
	echo "middle_thing() called (priority 50)\n";
}

#[AutoRun(priority: 1)]
function first_thing(): void
{
    echo "first_thing() called (priority 1)\n";
}

function not_autoloaded(): void
{
    echo "This function should NOT be called automatically\n";
}
