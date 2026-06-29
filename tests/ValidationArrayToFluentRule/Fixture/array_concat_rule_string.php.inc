<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConcatRuleStringFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['bail', 'required_if_accepted:' . self::PREFIX . '.enabled', 'nullable', Rule::email()->rfcCompliant(strict: true)->validateMxRecord()->preventSpoofing()],
            'slug' => ['string', 'starts_with:' . self::PREFIX],
            'confirm' => ['string', 'same:' . self::PREFIX],
            'prefixed' => ['string', 'required_if_accepted:prefix_' . self::PREFIX],
            'pattern' => ['string', 'regex:/^' . self::PREFIX . '$/'],
            'needs' => ['string', 'required_with:' . self::PREFIX],
            'cond' => ['string', 'required_if:' . self::PREFIX . ',active'],
            'count' => ['integer', 'min:' . self::THRESHOLD],
            'opaque' => ['string', self::PREFIX . ':foo'],
        ];
    }

    const PREFIX = 'container';

    const THRESHOLD = '5';
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConcatRuleStringFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => FluentRule::email()
                ->bail()
                ->requiredIfAccepted(self::PREFIX . '.enabled')
                ->nullable()
                ->rfcCompliant(strict: true)
                ->validateMxRecord()
                ->preventSpoofing(),
            'slug' => FluentRule::string()
                ->rule('starts_with:' . self::PREFIX),
            'confirm' => FluentRule::string()
                ->rule('same:' . self::PREFIX),
            'prefixed' => FluentRule::string()
                ->requiredIfAccepted('prefix_' . self::PREFIX),
            'pattern' => FluentRule::string()
                ->regex('/^' . self::PREFIX . '$/'),
            'needs' => FluentRule::string()
                ->rule('required_with:' . self::PREFIX),
            'cond' => FluentRule::string()
                ->rule('required_if:' . self::PREFIX . ',active'),
            'count' => FluentRule::integer()
                ->rule('min:' . self::THRESHOLD),
            'opaque' => FluentRule::string()
                ->rule(self::PREFIX . ':foo'),
        ];
    }

    const PREFIX = 'container';

    const THRESHOLD = '5';
}

?>
