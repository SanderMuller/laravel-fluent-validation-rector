<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureGrouping;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class CopyInteractionsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'interactions' => FluentRule::array(),
            'interactions.*' => FluentRule::field()->filled(),
            'questions' => FluentRule::array(),
            'questions.*' => FluentRule::field()->filled(),
            'timeShift' => FluentRule::numeric()->nullable(),
            'translate_content' => FluentRule::boolean()->nullable(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureGrouping;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class CopyInteractionsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'interactions' => FluentRule::array()->each(FluentRule::field()->filled()),
            'questions' => FluentRule::array()->each(FluentRule::field()->filled()),
            'timeShift' => FluentRule::numeric()->nullable(),
            'translate_content' => FluentRule::boolean()->nullable(),
        ];
    }
}

?>
