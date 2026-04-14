<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentRulesTrait\Fixture;

use App\Concerns\HasAuditLog;
use App\Concerns\HasRateLimit;
use App\Concerns\Sanitizes;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class MultilineChainedTraitsRequest extends FormRequest
{
    use HasAuditLog,
        HasRateLimit,
        Sanitizes;

    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentRulesTrait\Fixture;

use App\Concerns\HasAuditLog;
use App\Concerns\HasRateLimit;
use App\Concerns\Sanitizes;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class MultilineChainedTraitsRequest extends FormRequest
{
    use HasAuditLog,
        HasRateLimit,
        Sanitizes;
    use HasFluentRules;

    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
        ];
    }
}

?>
