<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\Concerns;

use PHPUnit\Framework\TestCase;
use SanderMuller\FluentValidationRector\Tests\Concerns\Support\NormalizesRulesDocblockHarness;

final class NormalizesRulesDocblockTest extends TestCase
{
    private NormalizesRulesDocblockHarness $harness;

    private const string STANDARD = 'array<string, \\Illuminate\\Contracts\\Validation\\ValidationRule|string|array<mixed>>';

    protected function setUp(): void
    {
        parent::setUp();
        $this->harness = new NormalizesRulesDocblockHarness();
    }

    public function testAcceptsExactStandardBody(): void
    {
        $this->assertTrue(
            $this->harness->annotationBodyMatchesStandardUnionExactlyOrProse(self::STANDARD)
        );
    }

    public function testAcceptsStandardBodyWithTrailingProse(): void
    {
        $this->assertTrue(
            $this->harness->annotationBodyMatchesStandardUnionExactlyOrProse(
                self::STANDARD . ' List of rules keyed by field'
            )
        );
    }

    public function testAcceptsStandardBodyWithPunctuatedProse(): void
    {
        $this->assertTrue(
            $this->harness->annotationBodyMatchesStandardUnionExactlyOrProse(
                self::STANDARD . " Indexed, space-separated, comma'd"
            )
        );
    }

    public function testRejectsUnionSuffix(): void
    {
        $this->assertFalse(
            $this->harness->annotationBodyMatchesStandardUnionExactlyOrProse(
                self::STANDARD . '|\\Illuminate\\Support\\Collection'
            )
        );
    }

    public function testRejectsIntersectionSuffix(): void
    {
        $this->assertFalse(
            $this->harness->annotationBodyMatchesStandardUnionExactlyOrProse(
                self::STANDARD . '&\\Countable'
            )
        );
    }

    public function testRejectsTrailingGenericBracket(): void
    {
        $this->assertFalse(
            $this->harness->annotationBodyMatchesStandardUnionExactlyOrProse(
                self::STANDARD . '<int>'
            )
        );
    }

    public function testRejectsTrailingAtTag(): void
    {
        $this->assertFalse(
            $this->harness->annotationBodyMatchesStandardUnionExactlyOrProse(
                self::STANDARD . ' @see Rules'
            )
        );
    }

    public function testRejectsUnrelatedAnnotation(): void
    {
        $this->assertFalse(
            $this->harness->annotationBodyMatchesStandardUnionExactlyOrProse(
                'array<string, \\Illuminate\\Contracts\\Validation\\ValidationRule>'
            )
        );
    }

    public function testRejectsPlainArray(): void
    {
        $this->assertFalse(
            $this->harness->annotationBodyMatchesStandardUnionExactlyOrProse('array')
        );
    }

    public function testTolerantOfLeadingOrTrailingWhitespaceOnEntireBody(): void
    {
        $this->assertTrue(
            $this->harness->annotationBodyMatchesStandardUnionExactlyOrProse('  ' . self::STANDARD . '  ')
        );
    }

    public function testRejectsEmptyString(): void
    {
        $this->assertFalse(
            $this->harness->annotationBodyMatchesStandardUnionExactlyOrProse('')
        );
    }
}
