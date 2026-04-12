<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureTrait;

use App\Concerns\HasAuditLog;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class SingleExistingTraitRequest extends FormRequest
{
    use HasAuditLog;

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

namespace SanderMuller\FluentValidationRector\Tests\FixtureTrait;

use SanderMuller\FluentValidation\HasFluentRules;
use App\Concerns\HasAuditLog;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class SingleExistingTraitRequest extends FormRequest
{
    use HasAuditLog;
    use HasFluentRules;

    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
        ];
    }
}

?>
