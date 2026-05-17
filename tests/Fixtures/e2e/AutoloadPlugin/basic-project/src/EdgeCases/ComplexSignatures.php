<?php

declare(strict_types=1);

namespace Demo\EdgeCases;

use Render\Autoloader\Attributes\AutoRun;

final class ComplexSignatures
{
    #[AutoRun(priority: 60)]
    final public static function finalMethod(): void
    {
        echo "ComplexSignatures::finalMethod() called (priority 60)\n";
    }

    #[AutoRun(priority: 61)]
    public static function withReturnType(): string
    {
        echo "ComplexSignatures::withReturnType() called (priority 61)\n";
        return 'done';
    }

    #[AutoRun(priority: 62)]
    public static function withParams(string $a = 'default', int $b = 10): void
    {
        echo "ComplexSignatures::withParams() called (priority 62)\n";
    }

    #[\Render\Autoloader\Attributes\AutoRun(priority: 63)]
    public static function withNullable(?string $x = null): ?int
    {
        echo "ComplexSignatures::withNullable() called (priority 63)\n";
        return null;
    }
}
