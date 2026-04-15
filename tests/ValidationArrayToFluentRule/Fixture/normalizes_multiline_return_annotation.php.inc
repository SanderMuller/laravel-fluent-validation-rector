<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\StringRule;

/**
 * Regression pin for a NormalizesRulesDocblock multi-line handling bug: a
 * `@return` annotation that spans multiple physical lines inside the
 * docblock (continuation lines starting with ` * `). The 0.4.17 draft
 * implementation only matched single-line annotations, so the continuation
 * `* StringRule>` was left behind after the first line was rewritten,
 * producing broken PHPDoc. The tightened pattern treats the whole tag —
 * head line plus continuations — as one match.
 */
class MultilineReturnFormRequest extends FormRequest
{
    /**
     * @return array<
     *     string,
     *     StringRule
     * >
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\StringRule;

/**
 * Regression pin for a NormalizesRulesDocblock multi-line handling bug: a
 * `@return` annotation that spans multiple physical lines inside the
 * docblock (continuation lines starting with ` * `). The 0.4.17 draft
 * implementation only matched single-line annotations, so the continuation
 * `* StringRule>` was left behind after the first line was rewritten,
 * producing broken PHPDoc. The tightened pattern treats the whole tag —
 * head line plus continuations — as one match.
 */
class MultilineReturnFormRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
        ];
    }
}

?>
