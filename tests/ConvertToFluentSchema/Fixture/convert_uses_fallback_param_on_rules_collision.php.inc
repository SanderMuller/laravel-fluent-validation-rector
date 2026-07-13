<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

// The conditional-assembly pattern uses a local $rules array. Rather than skip,
// inject the builder under a free fallback name ($schema) so the common shape
// still converts.
class ConditionalRulesRequest extends FormRequest
{
    use HasFluentRules;

    public function rules(): array
    {
        $rules = [
            'name' => FluentRule::string()->required(),
        ];

        if ($this->boolean('with_role')) {
            $rules['role'] = FluentRule::string()->required();
        }

        return $rules;
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidation\HasFluentRules;

// The conditional-assembly pattern uses a local $rules array. Rather than skip,
// inject the builder under a free fallback name ($schema) so the common shape
// still converts.
class ConditionalRulesRequest extends FormRequest
{
    use HasFluentRules;

    public function schema(FluentSchema $schema): array
    {
        $rules = [
            'name' => $schema->string()->required(),
        ];

        if ($this->boolean('with_role')) {
            $rules['role'] = $schema->string()->required();
        }

        return $rules;
    }
}

?>
