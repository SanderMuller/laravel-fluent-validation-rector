<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\Config;

use PHPUnit\Framework\TestCase;
use SanderMuller\FluentValidationRector\Config\LivewireConvertOptions;
use SanderMuller\FluentValidationRector\Config\Shared\OverlapBehavior;
use SanderMuller\FluentValidationRector\Rector\ConvertLivewireRuleAttributeRector;

/**
 * Two-layer assertions per spec §2:
 *
 *  - Layer A — `->toArray()` against HARD-CODED LITERAL STRING KEYS. Pins
 *    the wire format independent of the rector's constants. If a future
 *    lockstep rename moves both the constant value and the DTO output to a
 *    new string, this layer catches it because literal-key consumers
 *    (`['preserve_realtime_validation' => true]`) would silently break.
 *  - Layer B — constant-value pin. Asserts each rector constant's runtime
 *    value equals its documented literal wire key. Catches the inverse
 *    drift.
 */
final class LivewireConvertOptionsTest extends TestCase
{
    public function testDefaultProducesDocumentedDefaults(): void
    {
        $dto = LivewireConvertOptions::default();

        $this->assertTrue($dto->preserveRealtimeValidation);
        $this->assertFalse($dto->migrateMessages);
        $this->assertSame(OverlapBehavior::Bail, $dto->keyOverlapBehavior);
    }

    // Layer A — literal-string-key assertions on toArray().

    public function testToArrayProducesLiteralWireKeysForDefault(): void
    {
        $this->assertSame(
            [
                'preserve_realtime_validation' => true,
                'migrate_messages' => false,
                'key_overlap_behavior' => 'bail',
            ],
            LivewireConvertOptions::default()->toArray(),
        );
    }

    public function testToArrayProducesLiteralWireKeysForMessageMigration(): void
    {
        $this->assertSame(
            [
                'preserve_realtime_validation' => true,
                'migrate_messages' => true,
                'key_overlap_behavior' => 'bail',
            ],
            LivewireConvertOptions::default()->withMessageMigration()->toArray(),
        );
    }

    public function testToArrayProducesLiteralWireKeysForRealtimeDisabled(): void
    {
        $this->assertSame(
            [
                'preserve_realtime_validation' => false,
                'migrate_messages' => false,
                'key_overlap_behavior' => 'bail',
            ],
            LivewireConvertOptions::default()->withRealtimeValidationDisabled()->toArray(),
        );
    }

    public function testToArrayProducesLiteralWireKeysForOverlapPartial(): void
    {
        $this->assertSame(
            [
                'preserve_realtime_validation' => true,
                'migrate_messages' => false,
                'key_overlap_behavior' => 'partial',
            ],
            LivewireConvertOptions::default()->withOverlapBehavior(OverlapBehavior::Partial)->toArray(),
        );
    }

    // Layer B — constant-value pin.

    public function testConvertLivewireRuleAttributeRectorConstantsMatchWireKeys(): void
    {
        $this->assertSame('preserve_realtime_validation', ConvertLivewireRuleAttributeRector::PRESERVE_REALTIME_VALIDATION);
        $this->assertSame('migrate_messages', ConvertLivewireRuleAttributeRector::MIGRATE_MESSAGES);
        $this->assertSame('key_overlap_behavior', ConvertLivewireRuleAttributeRector::KEY_OVERLAP_BEHAVIOR);
        $this->assertSame('bail', ConvertLivewireRuleAttributeRector::OVERLAP_BEHAVIOR_BAIL);
        $this->assertSame('partial', ConvertLivewireRuleAttributeRector::OVERLAP_BEHAVIOR_PARTIAL);
    }

    // Immutability + chainability.

    public function testWithMessageMigrationReturnsNewInstance(): void
    {
        $dto = LivewireConvertOptions::default();
        $next = $dto->withMessageMigration();

        $this->assertNotSame($dto, $next);
        $this->assertFalse($dto->migrateMessages);
        $this->assertTrue($next->migrateMessages);
    }

    public function testWithRealtimeValidationDisabledReturnsNewInstance(): void
    {
        $dto = LivewireConvertOptions::default();
        $next = $dto->withRealtimeValidationDisabled();

        $this->assertNotSame($dto, $next);
        $this->assertTrue($dto->preserveRealtimeValidation);
        $this->assertFalse($next->preserveRealtimeValidation);
    }

    public function testWithOverlapBehaviorReturnsNewInstance(): void
    {
        $dto = LivewireConvertOptions::default();
        $next = $dto->withOverlapBehavior(OverlapBehavior::Partial);

        $this->assertNotSame($dto, $next);
        $this->assertSame(OverlapBehavior::Bail, $dto->keyOverlapBehavior);
        $this->assertSame(OverlapBehavior::Partial, $next->keyOverlapBehavior);
    }

    public function testBuildersLeaveOtherFieldsUntouched(): void
    {
        $dto = LivewireConvertOptions::default()
            ->withMessageMigration()
            ->withOverlapBehavior(OverlapBehavior::Partial);

        $this->assertTrue($dto->preserveRealtimeValidation);
        $this->assertTrue($dto->migrateMessages);
        $this->assertSame(OverlapBehavior::Partial, $dto->keyOverlapBehavior);
    }

    public function testBuildersAreOrderIndependent(): void
    {
        $a = LivewireConvertOptions::default()
            ->withMessageMigration()
            ->withRealtimeValidationDisabled()
            ->withOverlapBehavior(OverlapBehavior::Partial)
            ->toArray();

        $b = LivewireConvertOptions::default()
            ->withOverlapBehavior(OverlapBehavior::Partial)
            ->withRealtimeValidationDisabled()
            ->withMessageMigration()
            ->toArray();

        $this->assertSame($a, $b);
    }
}
