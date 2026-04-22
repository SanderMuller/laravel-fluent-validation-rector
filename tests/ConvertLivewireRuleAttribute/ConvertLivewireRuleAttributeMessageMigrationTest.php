<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute;

use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

/**
 * Exercises the opt-in `migrate_messages => true` config path. Separate
 * test class + fixture directory because one rector test class gets one
 * config; the default off-path coverage stays in
 * `ConvertLivewireRuleAttributeRectorTest`.
 */
final class ConvertLivewireRuleAttributeMessageMigrationTest extends AbstractRectorTestCase
{
    #[DataProvider('provideData')]
    public function test(string $filePath): void
    {
        $this->doTestFile($filePath);
    }

    /** @return Iterator<array<string>> */
    public static function provideData(): Iterator
    {
        return self::yieldFilesFromDirectory(__DIR__ . '/FixtureMessageMigration');
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__ . '/config/configured_livewire_attribute_rule_message_migration.php';
    }
}
