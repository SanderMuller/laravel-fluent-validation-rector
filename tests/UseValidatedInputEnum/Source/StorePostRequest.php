<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\ValidatedInput;

/**
 * Autoloadable FormRequest for the fixtures. Laravel 12 types safe() as
 * ValidatedInput|array; the override gives it the conditional type that
 * Laravel 13 and HasFluentRules (1.40+) declare, on every version.
 */
final class StorePostRequest extends FormRequest
{
    /**
     * @param  array<string>|null  $keys
     * @return ($keys is null ? ValidatedInput : array<string, mixed>)
     */
    public function safe(?array $keys = null)
    {
        return parent::safe($keys);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string',
            'status' => 'nullable|string',
            'published_at' => 'nullable|date',
        ];
    }
}
