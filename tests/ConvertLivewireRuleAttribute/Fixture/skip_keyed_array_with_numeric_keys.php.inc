<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Regression pin from Phase 1 codex review: numeric-string keys in the
 * keyed-array first arg (`['0' => 'required', '1' => 'email']`) are not a
 * documented Livewire shape. PHP internally normalises them to int keys,
 * but PhpParser preserves the String_ node as written — our keyed-shape
 * detector matches the literal form. Before the guard, each numeric key
 * would be emitted verbatim as a top-level `rules()` entry (`'0'`, `'1'`),
 * which has nothing to do with the annotated property and silently breaks
 * validation.
 *
 * Fail-closed is the correct behaviour — a visible no-op lets the user
 * see the malformed attribute and fix it by hand.
 */
final class NumericKeyedArrayComponent extends Component
{
    #[Validate([
        '0' => 'required',
        '1' => 'email',
    ])]
    public string $email = '';
}

?>
