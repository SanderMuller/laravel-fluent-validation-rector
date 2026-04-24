<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\SimplifySet;

use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

/**
 * End-to-end verification of the `SIMPLIFY` set: PromoteFieldFactoryRector +
 * SimplifyFluentRuleRector + SimplifyRuleWrappersRector + InlineMessageParamRector
 * wired together. Targets the field-factory → typed-factory → rule-lowering
 * cascade that requires multiple rectors in the same pass.
 */
final class SimplifySetRectorTest extends AbstractRectorTestCase
{
    #[DataProvider('provideData')]
    public function test(string $filePath): void
    {
        $this->doTestFile($filePath);
    }

    /** @return Iterator<array<string>> */
    public static function provideData(): Iterator
    {
        return self::yieldFilesFromDirectory(__DIR__ . '/Fixture');
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__ . '/config/configured_simplify_set.php';
    }
}
