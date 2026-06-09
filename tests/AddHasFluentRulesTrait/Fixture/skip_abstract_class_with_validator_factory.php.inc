<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentRulesTrait\Fixture;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use SanderMuller\FluentValidation\FluentRule;

// Abstract class with a `validator()` factory method, no `rules()`
// method. A consumer dogfood run (2026-04-26): an
// Action class — abstract, no FormRequest ancestry, no Livewire, but
// uses FluentRule inside an internal validator factory. The widened
// detector matched FluentRule usage and the trait rector added
// `HasFluentRules`. Trait is FormRequest-internal — useless here. Note
// the class is also abstract, but the existing abstract-with-rules()
// guard doesn't fire because there's no `rules()` method.
abstract class GenerateReportExports
{
    abstract public function generate(string $attachmentId): void;

    protected function validator(array $payload): Validator
    {
        return ValidatorFactory::make($payload, [
            'attachment_id' => FluentRule::string()->required(),
            'frame_count' => FluentRule::integer()->required()->min(1),
        ]);
    }
}

?>
