<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers;

use PhpParser\Node\Expr;
use PhpParser\Node\Stmt\Expression;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SanderMuller\FluentValidationRector\Rector\SimplifyRuleWrappersRector;

/**
 * Pins the output shape of {@see SimplifyRuleWrappersRector::describeUnparseablePayload()}
 * — the verbose skip line for "rule payload not statically resolvable to a v1
 * shape". Added 0.12.0 as sequencing-gate for FieldRule upgrade + payload-
 * specific converter follow-ons: the peer dogfood pass buckets the 57-entry
 * noise category using the class + truncated expression emitted by this
 * helper, so the format is a consumer-facing contract.
 */
final class DescribeUnparseablePayloadTest extends TestCase
{
    public function testStaticCallPayloadShowsFactoryNotation(): void
    {
        $description = $this->describe('Password::default()');
        $this->assertSame('StaticCall Password::default()', $description);
    }

    public function testNewExprPayloadShowsConstructorShorthand(): void
    {
        $description = $this->describe('new DoesNotContainUrlRule()');
        $this->assertSame('New_ new DoesNotContainUrlRule()', $description);
    }

    public function testMethodCallTailPayloadKeepsChain(): void
    {
        $description = $this->describe('User::uniqueRule(User::EMAIL)->withoutTrashed()');
        $this->assertSame('MethodCall User::uniqueRule(User::EMAIL)->withoutTrashed()', $description);
    }

    public function testLongPayloadsAreTruncatedWithEllipsis(): void
    {
        $description = $this->describe(
            'SomeLongHelper::withTonsOfArguments($a, $b, $c, $d, $e, $f, $g, $h, $i, $j, $k)',
        );
        $this->assertStringEndsWith('...', $description);
        // Short class name prefix ("StaticCall") + space + 60-char max = bounded total.
        $this->assertLessThanOrEqual(80, strlen($description));
    }

    public function testMultiLineExpressionIsFlattenedToSingleLine(): void
    {
        $description = $this->describe(
            "Rule::unique('users', 'email')\n        ->where('company_id', 1)",
        );
        $this->assertStringNotContainsString("\n", $description);
        $this->assertStringNotContainsString("\t", $description);
    }

    private function describe(string $exprSource): string
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $stmts = $parser->parse('<?php ' . $exprSource . ';');

        if ($stmts === null || ! isset($stmts[0]) || ! $stmts[0] instanceof Expression) {
            $this->fail("Could not parse expression: {$exprSource}");
        }

        $expr = $stmts[0]->expr;
        $this->assertInstanceOf(Expr::class, $expr);

        $rector = new SimplifyRuleWrappersRector();

        $method = (new ReflectionClass(SimplifyRuleWrappersRector::class))
            ->getMethod('describeUnparseablePayload');

        return (string) $method->invoke($rector, $expr);
    }
}
