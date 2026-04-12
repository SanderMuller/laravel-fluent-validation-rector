<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RuleObjectFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(['admin', 'editor', 'viewer'])],
            'team_id' => ['required', 'integer', Rule::exists('teams', 'id')],
            'category' => ['nullable', 'string', Rule::notIn(['banned', 'restricted'])],
            'status' => ['required', Rule::enum(\App\Enums\Status::class)],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use SanderMuller\FluentValidation\FluentRule;
use App\Enums\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RuleObjectFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => FluentRule::email()->required()->unique('users', 'email'),
            'role' => FluentRule::field()->required()->in(['admin', 'editor', 'viewer']),
            'team_id' => FluentRule::integer()->required()->exists('teams', 'id'),
            'category' => FluentRule::string()->nullable()->notIn(['banned', 'restricted']),
            'status' => FluentRule::field()->required()->enum(Status::class),
        ];
    }
}

?>
