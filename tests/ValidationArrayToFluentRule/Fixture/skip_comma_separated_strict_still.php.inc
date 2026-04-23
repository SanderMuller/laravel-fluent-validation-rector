<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

class StrictCommaSeparatedFormRequest extends FormRequest
{
    public function rules(): array
    {
        $cond = true;

        return [
            'a' => [
                'string',
                ['required_if', 'type', $this->complexMethod()],
            ],
            'b' => [
                'string',
                ['required_if', 'type', $cond ? 'a' : 'b'],
            ],
            'c' => [
                'string',
                ['required_if', fn (): bool => true],
            ],
            'd' => [
                'string',
                ['required_if', 'ratio', 1.5],
            ],
            'e' => [
                'string',
                ['required_if', 'state', null],
            ],
        ];
    }

    public function complexMethod(): string
    {
        return 'admin';
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;

class StrictCommaSeparatedFormRequest extends FormRequest
{
    public function rules(): array
    {
        $cond = true;

        return [
            'a' => FluentRule::string()->rule(['required_if', 'type', $this->complexMethod()]),
            'b' => FluentRule::string()->rule(['required_if', 'type', $cond ? 'a' : 'b']),
            'c' => [
                'string',
                ['required_if', fn (): bool => true],
            ],
            'd' => FluentRule::string()->rule(['required_if', 'ratio', 1.5]),
            'e' => FluentRule::string()->rule(['required_if', 'state', null]),
        ];
    }

    public function complexMethod(): string
    {
        return 'admin';
    }
}

?>
