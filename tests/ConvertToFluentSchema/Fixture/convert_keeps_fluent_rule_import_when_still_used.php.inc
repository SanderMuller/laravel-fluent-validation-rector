<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

// Only rules() converts; the baseRules() helper still uses FluentRule::, so
// the FluentRule import must be kept alongside the new FluentSchema import.
class ConvertKeepsFluentRuleImportWhenStillUsed extends FormRequest
{
    use HasFluentRules;

    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
        ];
    }

    public function baseRules(): array
    {
        return [
            'id' => FluentRule::integer()->required(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidation\HasFluentRules;

// Only rules() converts; the baseRules() helper still uses FluentRule::, so
// the FluentRule import must be kept alongside the new FluentSchema import.
class ConvertKeepsFluentRuleImportWhenStillUsed extends FormRequest
{
    use HasFluentRules;

    public function schema(FluentSchema $rules): array
    {
        return [
            'name' => $rules->string()->required(),
        ];
    }

    public function baseRules(): array
    {
        return [
            'id' => FluentRule::integer()->required(),
        ];
    }
}

?>
