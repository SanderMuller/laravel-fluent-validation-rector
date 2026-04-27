<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Config;

use SanderMuller\FluentValidationRector\Config\Shared\OverlapBehavior;
use SanderMuller\FluentValidationRector\Rector\ConvertLivewireRuleAttributeRector;

/**
 * Typed builder for `ConvertLivewireRuleAttributeRector`'s configuration.
 *
 * Terminates in `->toArray()`; the rector's `configure(array)` signature
 * is unchanged and consumes the same wire-key shape committed in
 * `PUBLIC_API.md`. The DTO is purely consumer-side ergonomics over the
 * canonical array transport.
 *
 *     $rectorConfig->ruleWithConfiguration(
 *         ConvertLivewireRuleAttributeRector::class,
 *         LivewireConvertOptions::default()
 *             ->withMessageMigration()
 *             ->withOverlapBehavior(OverlapBehavior::Partial)
 *             ->toArray(),
 *     );
 */
final readonly class LivewireConvertOptions
{
    private function __construct(
        public bool $preserveRealtimeValidation,
        public bool $migrateMessages,
        public OverlapBehavior $keyOverlapBehavior,
    ) {}

    public static function default(): self
    {
        return new self(
            preserveRealtimeValidation: true,
            migrateMessages: false,
            keyOverlapBehavior: OverlapBehavior::Bail,
        );
    }

    public function withRealtimeValidationDisabled(): self
    {
        return new self(false, $this->migrateMessages, $this->keyOverlapBehavior);
    }

    public function withMessageMigration(): self
    {
        return new self($this->preserveRealtimeValidation, true, $this->keyOverlapBehavior);
    }

    public function withOverlapBehavior(OverlapBehavior $behavior): self
    {
        return new self($this->preserveRealtimeValidation, $this->migrateMessages, $behavior);
    }

    /**
     * @return array{preserve_realtime_validation: bool, migrate_messages: bool, key_overlap_behavior: string}
     */
    public function toArray(): array
    {
        return [
            ConvertLivewireRuleAttributeRector::PRESERVE_REALTIME_VALIDATION => $this->preserveRealtimeValidation,
            ConvertLivewireRuleAttributeRector::MIGRATE_MESSAGES => $this->migrateMessages,
            ConvertLivewireRuleAttributeRector::KEY_OVERLAP_BEHAVIOR => $this->keyOverlapBehavior->value,
        ];
    }
}
