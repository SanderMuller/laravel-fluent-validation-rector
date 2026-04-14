<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class StorePushSubscriptionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'endpoint' => FluentRule::string()->required()->url()->max(500),
            'keys.p256dh' => FluentRule::string()->required(),
            'keys.auth' => FluentRule::string()->required(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class StorePushSubscriptionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'endpoint' => FluentRule::string()->required()->url()->max(500),
            'keys' => FluentRule::array()->children([
                'p256dh' => FluentRule::string()->required(),
                'auth' => FluentRule::string()->required(),
            ]),
        ];
    }
}

?>
