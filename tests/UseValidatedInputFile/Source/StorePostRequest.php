<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\Source;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

/**
 * Autoloadable FormRequest for the fixtures, so the type of safe() comes
 * from the conditional return type on HasFluentRules.
 */
final class StorePostRequest extends FormRequest
{
    use HasFluentRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => FluentRule::string()->nullable()->max(255),
            'avatar' => FluentRule::image()->nullable(),
        ];
    }
}
