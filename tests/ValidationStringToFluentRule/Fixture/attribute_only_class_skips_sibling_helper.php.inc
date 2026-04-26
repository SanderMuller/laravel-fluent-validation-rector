<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRules;
use SanderMuller\FluentValidation\FluentValidator;

// Class qualifies ONLY via the per-method `#[FluentRules]` attribute
// (no FormRequest, no fluent trait, no Livewire). The attributed
// method must convert; the sibling rules-shaped helper must NOT — it
// has not been opted in. Codex 2026-04-26 catch: a single attributed
// method previously expanded into class-wide auto-detection, letting
// sibling helpers with rule-like tokens get rewritten. Auto-detect is
// now gated on a class-wide qualifying signal.
class AttributeOnlySiblingHelperValidator extends FluentValidator
{
    #[FluentRules]
    public function rulesWithoutPrefix(): array
    {
        return [
            'title' => 'required|string',
        ];
    }

    public function legacyMessageRules(): array
    {
        return [
            'subject' => 'required|string',
            'body' => 'nullable|string',
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\FluentRules;
use SanderMuller\FluentValidation\FluentValidator;

// Class qualifies ONLY via the per-method `#[FluentRules]` attribute
// (no FormRequest, no fluent trait, no Livewire). The attributed
// method must convert; the sibling rules-shaped helper must NOT — it
// has not been opted in. Codex 2026-04-26 catch: a single attributed
// method previously expanded into class-wide auto-detection, letting
// sibling helpers with rule-like tokens get rewritten. Auto-detect is
// now gated on a class-wide qualifying signal.
class AttributeOnlySiblingHelperValidator extends FluentValidator
{
    #[FluentRules]
    public function rulesWithoutPrefix(): array
    {
        return [
            'title' => FluentRule::string()->required(),
        ];
    }

    public function legacyMessageRules(): array
    {
        return [
            'subject' => 'required|string',
            'body' => 'nullable|string',
        ];
    }
}

?>
