<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\HasFluentRules;

/**
 * Concrete base that uses HasFluentRules but whose rules() still returns plain
 * string rules (not yet migrated to FluentRule). The converter bails it — there
 * is no FluentRule / self-parent call to convert — so it keeps rules(). The
 * parent-conversion probe parses this body and sees no convertible content, so
 * a child's parent::rules() is left alone rather than stranded.
 */
class StringRulesBaseRequest extends FormRequest
{
    use HasFluentRules;

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
        ];
    }
}
