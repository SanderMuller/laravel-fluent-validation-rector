<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UniqueWithoutTrashedRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', Rule::unique(User::class)->withoutTrashed()],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UniqueWithoutTrashedRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => FluentRule::string()->required()->unique(User::class, null, fn($rule) => $rule->withoutTrashed()),
        ];
    }
}

?>
