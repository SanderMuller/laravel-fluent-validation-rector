<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureTrait;

use App\Concerns\HasAuditLog;
use App\Concerns\HasRateLimit;
use App\Concerns\Sanitizes;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class MultipleTraitsRequest extends FormRequest
{
    use HasAuditLog;
    use HasRateLimit;
    use Sanitizes;

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
use App\Concerns\HasRateLimit;
use App\Concerns\Sanitizes;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class MultipleTraitsRequest extends FormRequest
{
    use HasAuditLog;
    use HasRateLimit;
    use Sanitizes;
    use HasFluentRules;

    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
        ];
    }
}

?>
