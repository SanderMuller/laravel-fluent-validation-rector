<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\FullPipelinePolish;

use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

/**
 * Integration test for the documented opt-in `ALL + POLISH` consumer
 * configuration. The split exists because POLISH is described as an
 * additive post-migration cleanup set (README §Sets); this test
 * pins the contract for consumers who follow the docs and load both.
 *
 * 0.19.1 origin: hihaho dogfood reported the docblock-narrow rector
 * skipping post-fold output. The FullPipeline test (ALL only) didn't
 * cover this because POLISH wasn't loaded; this test does.
 */
final class FullPipelinePolishRectorTest extends AbstractRectorTestCase
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
        return __DIR__ . '/config/configured_all_plus_polish.php';
    }
}
