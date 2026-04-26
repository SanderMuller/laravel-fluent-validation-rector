<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

// Eloquent-shaped `casts()` on a qualifying class. Values like
// 'boolean' and 'array' overlap with KNOWN_RULE_NAMES, so the
// structural shape check would false-positive without the method-name
// denylist (Codex 2026-04-26). Without this guard the converter would
// rewrite cast declarations as FluentRule chains and corrupt model
// behavior wherever this trait was used.
class SkipCastsMethod extends FormRequest
{
    public function rules(): array
    {
        return [];
    }

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'meta' => 'array',
            'verified_at' => 'datetime',
        ];
    }
}

?>
