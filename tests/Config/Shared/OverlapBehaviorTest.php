<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\Config\Shared;

use PHPUnit\Framework\TestCase;
use SanderMuller\FluentValidationRector\Config\Shared\OverlapBehavior;

final class OverlapBehaviorTest extends TestCase
{
    public function testCaseValuesMatchLiteralWireStrings(): void
    {
        $this->assertSame('bail', OverlapBehavior::Bail->value);
        $this->assertSame('partial', OverlapBehavior::Partial->value);
    }

    public function testCasesReturnsExactlyTwoCases(): void
    {
        $this->assertCount(2, OverlapBehavior::cases());
    }

    public function testFromParsesLiteralWireStrings(): void
    {
        $this->assertSame(OverlapBehavior::Bail, OverlapBehavior::from('bail'));
        $this->assertSame(OverlapBehavior::Partial, OverlapBehavior::from('partial'));
    }

    public function testTryFromReturnsNullForInvalidInput(): void
    {
        $this->assertNull(OverlapBehavior::tryFrom('invalid'));
        $this->assertNull(OverlapBehavior::tryFrom(''));
    }
}
