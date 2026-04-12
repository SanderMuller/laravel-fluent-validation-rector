<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

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

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use App\Rules\SubtitleDelayRules;
use SanderMuller\FluentValidation\FluentRule;
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
