<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests;

use PHPUnit\Framework\TestCase;
use Rector\NodeTypeResolver\Node\AttributeKey;

/**
 * Pins the Rector-internal APIs we rely on at class/constant level. Rector
 * has churned `AttributeKey` members across past major versions; this test
 * fails fast on Rector 3+ upgrades that rename or remove keys we depend on,
 * rather than letting the absence silently degrade generated output.
 */
final class RectorInternalContractsTest extends TestCase
{
    /**
     * `ConvertLivewireRuleAttributeRector::multilineArray()` attaches
     * `NEWLINED_ARRAY_PRINT` to the synthesized `rules()` return array so the
     * format-preserving printer emits one item per line regardless of
     * `Array_::$items` count. If this constant vanishes, the generated
     * `rules()` method collapses to a single line and quickly blows past
     * reasonable widths on consumers with 3+ properties.
     */
    public function testNewlinedArrayPrintConstantExists(): void
    {
        $this->assertTrue(
            defined(AttributeKey::class . '::NEWLINED_ARRAY_PRINT'),
            AttributeKey::class . '::NEWLINED_ARRAY_PRINT is gone; '
                . 'ConvertLivewireRuleAttributeRector::multilineArray() will no longer '
                . 'force multi-line output. Find the replacement attribute in your Rector '
                . 'version and update multilineArray() accordingly.',
        );
    }
}
