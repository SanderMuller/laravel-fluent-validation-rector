<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\FullPipelineSchema;

use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

/**
 * End-to-end: ALL (convert + group + traits) followed by SCHEMA. Proves a plain
 * FormRequest with string rules converts all the way to the schema(FluentSchema)
 * builder in a single pass — string rules → FluentRule chains → HasFluentRules
 * trait → schema() rename with $rules-> receivers.
 */
final class FullPipelineSchemaRectorTest extends AbstractRectorTestCase
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
        return __DIR__ . '/config/configured_all_plus_schema.php';
    }
}
