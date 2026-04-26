<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\Parity;

/**
 * Documented-divergence categories. Pairs each `allowed_divergences` entry
 * with the structural outcome shape it permits, so a category change must
 * be acknowledged when the divergence shape changes.
 *
 * @internal
 */
enum DivergenceCategory: string
{
    /** Promoted typed rule (`boolean()`, `numeric()`, etc.) attaches an implicit constraint absent from the pre-rector rule. */
    case ImplicitTypeConstraint = 'implicit_type_constraint';

    /** Same fail outcome but the underlying message-key path changed (e.g. `validation.required` vs `validation.required_array`). */
    case MessageKeyDrift = 'message_key_drift';

    /** Same fail outcome but the `:attribute` substitution renders differently (e.g. snake_case → Title Case). */
    case AttributeLabelDrift = 'attribute_label_drift';

    /** Both sides fail with the same messages but the per-field message order differs (validator pipeline ordering). */
    case OrderDependentPipeline = 'order_dependent_pipeline';

    /**
     * Outcome shapes this category legitimately produces.
     *
     * @return list<ParityType>
     */
    public function allowedOutcomes(): array
    {
        return match ($this) {
            self::ImplicitTypeConstraint => [
                // Implicit-type promotion can only narrow: the post-rector rule
                // adds a typed pre-check (boolean / numeric / etc.) absent from
                // the pre-rector form. Allowed direction is strictly
                // "after rejects, before passes." A regression making the post-
                // rector rule MORE permissive would surface as the inverse
                // outcome and must NOT be slotted into this category.
                ParityType::AfterRejectsBeforePasses,
            ],
            self::MessageKeyDrift => [ParityType::BothRejectDifferentMessages],
            self::AttributeLabelDrift => [ParityType::BothRejectDifferentMessages],
            self::OrderDependentPipeline => [ParityType::BothRejectDifferentOrder],
        };
    }
}
