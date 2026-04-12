<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureTrait;

use App\Concerns\HasAuditLog;
use App\Concerns\HasRateLimit;
use App\Concerns\Sanitizes;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class ChainedTraitsRequest extends FormRequest
{
    use HasAuditLog, HasRateLimit, Sanitizes;

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

use App\Concerns\HasAuditLog;
use App\Concerns\HasRateLimit;
use App\Concerns\Sanitizes;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class ChainedTraitsRequest extends FormRequest
{
    use HasAuditLog, HasRateLimit, Sanitizes;
    use HasFluentRules;

    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
        ];
    }
}

?>
