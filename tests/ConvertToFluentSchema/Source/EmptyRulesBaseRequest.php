<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\HasFluentRules;

/**
 * Abstract base that uses HasFluentRules and declares an empty rules(). That
 * method beats the trait's rules() fallback, so a child whose rules() is renamed
 * to schema() would resolve `$request->rules()` to this empty array.
 */
abstract class EmptyRulesBaseRequest extends FormRequest
{
    use HasFluentRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
