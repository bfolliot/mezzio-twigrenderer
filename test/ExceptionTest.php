<?php

declare(strict_types=1);

namespace MezzioTest\Twig;

use Generator;
use Mezzio\Template\Exception\ExceptionInterface as TemplateExceptionInterface;
use Mezzio\Twig\Exception\ExceptionInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function basename;
use function glob;
use function is_a;
use function strrpos;
use function substr;

class ExceptionTest extends TestCase
{
    public function testExceptionInterfaceExtendsTemplateExceptionInterface(): void
    {
        $this->assertTrue(is_a(ExceptionInterface::class, TemplateExceptionInterface::class, true));
    }

    public static function exception(): Generator
    {
        $namespace = substr(ExceptionInterface::class, 0, strrpos(ExceptionInterface::class, '\\') + 1);

        $exceptions = glob(__DIR__ . '/../src/Exception/*.php');
        foreach ($exceptions as $exception) {
            $class = substr(basename($exception), 0, -4);

            yield $class => [$namespace . $class];
        }
    }

    #[DataProvider('exception')]
    public function testExceptionIsInstanceOfExceptionInterface(string $exception): void
    {
        $this->assertStringContainsString('Exception', $exception);
        $this->assertTrue(is_a($exception, ExceptionInterface::class, true));
    }
}
