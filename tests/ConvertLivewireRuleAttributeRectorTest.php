<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests;

use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

final class ConvertLivewireRuleAttributeRectorTest extends AbstractRectorTestCase
{
    #[DataProvider('provideData')]
    public function test(string $filePath): void
    {
        $this->doTestFile($filePath);
    }

    /** @return Iterator<array<string>> */
    public static function provideData(): Iterator
    {
        return self::yieldFilesFromDirectory(__DIR__ . '/FixtureLivewireAttribute');
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__ . '/config/configured_livewire_attribute_rule.php';
    }
}
