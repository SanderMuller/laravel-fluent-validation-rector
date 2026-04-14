<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

class SpreadOperatorFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'delay' => ['nullable', ...new \App\Rules\SubtitleDelayRules()],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use App\Rules\SubtitleDelayRules;
use Illuminate\Foundation\Http\FormRequest;

class SpreadOperatorFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'delay' => ['nullable', ...new SubtitleDelayRules()],
            'name' => FluentRule::string()->required()->max(255),
        ];
    }
}

?>
