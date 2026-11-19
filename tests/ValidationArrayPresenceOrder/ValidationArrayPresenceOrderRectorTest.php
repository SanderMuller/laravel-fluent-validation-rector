<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayPresenceOrder;

use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;
use SanderMuller\FluentValidationRector\Internal\PresenceConditionalOrder;

/**
 * laravel-fluent-validation 1.45.1 compiles a closure or bool presence
 * conditional before the password / email check. Earlier versions compiled it
 * after, so the rule bails there. Only the fixtures for the installed
 * library's behavior run; CI covers both sides (latest and prefer-lowest).
 */
final class ValidationArrayPresenceOrderRectorTest extends AbstractRectorTestCase
{
    #[DataProvider('provideData')]
    public function test(string $filePath): void
    {
        $this->doTestFile($filePath);
    }

    /** @return Iterator<array<string>> */
    public static function provideData(): Iterator
    {
        $order = PresenceConditionalOrder::runsFirst('password') ? 'RunsFirst' : 'RunsLast';

        return self::yieldFilesFromDirectory(__DIR__ . '/Fixture/' . $order);
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__ . '/../ValidationArrayToFluentRule/config/configured_array_rule.php';
    }
}
