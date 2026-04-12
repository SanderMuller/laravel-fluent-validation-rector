<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChainedDatabaseRuleFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'team_id' => ['required', 'integer', Rule::exists('teams', 'id')->where('active', true)],
            'username' => ['required', 'string', Rule::unique('users')->ignore($this->user()->id)->where('org_id', $this->org_id)],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\FluentRule;

class ChainedDatabaseRuleFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => FluentRule::email()->required()->unique('users', 'email', fn($rule) => $rule->ignore($this->user()->id)),
            'team_id' => FluentRule::integer()->required()->exists('teams', 'id', fn($rule) => $rule->where('active', true)),
            'username' => FluentRule::string()->required()->unique('users', null, fn($rule) => $rule->ignore($this->user()->id)->where('org_id', $this->org_id)),
        ];
    }
}

?>
