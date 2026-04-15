<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute;

use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

/**
 * Exercises the opt-out `preserve_realtime_validation => false` config path.
 * Separate test class + fixture directory because one rector test class
 * gets one config; the default preservation-on and the opt-out paths need
 * separate test classes to be exercised independently.
 */
final class ConvertLivewireRuleAttributePreservationDisabledTest extends AbstractRectorTestCase
{
    #[DataProvider('provideData')]
    public function test(string $filePath): void
    {
        $this->doTestFile($filePath);
    }

    /** @return Iterator<array<string>> */
    public static function provideData(): Iterator
    {
        return self::yieldFilesFromDirectory(__DIR__ . '/FixturePreservationDisabled');
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__ . '/config/configured_livewire_attribute_rule_preservation_disabled.php';
    }
}
