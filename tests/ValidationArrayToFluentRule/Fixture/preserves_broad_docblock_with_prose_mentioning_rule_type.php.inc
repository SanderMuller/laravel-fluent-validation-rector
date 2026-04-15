<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Regression pin for a NormalizesRulesDocblock false positive: prose elsewhere
 * in the docblock mentions `StringRule` (e.g. a description sentence that
 * references the rule classes emitted), but the `@return` itself is broad and
 * correct. The normalizer must evaluate staleness on the `@return` tag body
 * specifically — not the whole docblock text — so the broad annotation is
 * preserved.
 */
class BroadDocblockWithProseFormRequest extends FormRequest
{
    /**
     * Rules for the request. Returns a map keyed by field name, with
     * values produced by factories like FluentRule::string() that emit
     * `StringRule` instances at runtime.
     *
     * @return array<string, mixed>
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
use Illuminate\Foundation\Http\FormRequest;

/**
 * Regression pin for a NormalizesRulesDocblock false positive: prose elsewhere
 * in the docblock mentions `StringRule` (e.g. a description sentence that
 * references the rule classes emitted), but the `@return` itself is broad and
 * correct. The normalizer must evaluate staleness on the `@return` tag body
 * specifically — not the whole docblock text — so the broad annotation is
 * preserved.
 */
class BroadDocblockWithProseFormRequest extends FormRequest
{
    /**
     * Rules for the request. Returns a map keyed by field name, with
     * values produced by factories like FluentRule::string() that emit
     * `StringRule` instances at runtime.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
        ];
    }
}

?>
