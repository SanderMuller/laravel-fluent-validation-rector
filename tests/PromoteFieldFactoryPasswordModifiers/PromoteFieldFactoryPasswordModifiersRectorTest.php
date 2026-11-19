<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactoryPasswordModifiers;

use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;
use SanderMuller\FluentValidation\Rules\PasswordRule;

final class PromoteFieldFactoryPasswordModifiersRectorTest extends AbstractRectorTestCase
{
    #[DataProvider('provideData')]
    public function test(string $filePath): void
    {
        // PasswordRule::different() and notIn() arrived in laravel-fluent-validation
        // 1.45. On an older version the rule keeps these chains on string(), by
        // design, so the fixtures do not apply.
        if (! method_exists(PasswordRule::class, 'notIn')) {
            $this->markTestSkipped('The installed laravel-fluent-validation has no PasswordRule::notIn().');
        }

        $this->doTestFile($filePath);
    }

    /** @return Iterator<array<string>> */
    public static function provideData(): Iterator
    {
        return self::yieldFilesFromDirectory(__DIR__ . '/Fixture');
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__ . '/../PromoteFieldFactory/config/configured_promote_field_factory.php';
    }
}
