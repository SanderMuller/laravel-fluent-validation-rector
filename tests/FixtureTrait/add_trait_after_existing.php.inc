<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureTrait;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class RequestWithExistingTrait extends FormRequest
{
    use \App\Concerns\HasAuditLog;

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
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class RequestWithExistingTrait extends FormRequest
{
    use \App\Concerns\HasAuditLog;
    use HasFluentRules;


    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
        ];
    }
}

?>
