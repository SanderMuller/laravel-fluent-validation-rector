<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\FixtureSupport;

/**
 * Stand-in for Filament v5's `Filament\Schemas\Concerns\InteractsWithSchemas`.
 * The real trait replaces `InteractsWithForms` in v5 and exposes a similar
 * validate()/validateOnly() surface. The rector matches trait names by
 * substring against `FILAMENT_FORM_TRAIT_NEEDLES`, so the stand-in only
 * needs the right short-name to trigger detection.
 */
trait InteractsWithSchemas
{
    /**
     * @return array{}
     */
    public function validate(?array $rules = null, array $messages = [], array $attributes = []): array
    {
        return [];
    }
}
