<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString;

use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;
use SanderMuller\FluentValidation\ValidatedInput;

require_once __DIR__ . '/Support/namespaced_strval.php';

final class UseValidatedInputNullableStringRectorTest extends AbstractRectorTestCase
{
    #[DataProvider('provideData')]
    public function test(string $filePath): void
    {
        // The prefer-lowest CI leg installs a laravel-fluent-validation
        // version without this accessor. The rule then does not fire, by
        // design, so the fixtures do not apply.
        if (! method_exists(ValidatedInput::class, 'nullableString')) {
            $this->markTestSkipped('The installed laravel-fluent-validation has no ValidatedInput::nullableString().');
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
        return __DIR__ . '/config/configured_rule.php';
    }
}
