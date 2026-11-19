<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputDate\Source;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Autoloadable FormRequest for the fixtures, so PHPStan can type safe().
 */
final class StorePostRequest extends FormRequest
{
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
