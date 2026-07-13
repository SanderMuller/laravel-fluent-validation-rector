<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\RuleSet;
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source\ConcreteBaseRequest;

// Only rules()/parent::rules() are in scope. A sibling messages() with a
// ...parent::messages() spread is a different method and a different parent
// call — it is left completely untouched by the conversion.
class MessagesChildRequest extends ConcreteBaseRequest
{
    public function rules(): RuleSet
    {
        return parent::rules()->merge([
            'body' => FluentRule::string()->required(),
        ]);
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'body.required' => 'The body is required.',
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidation\RuleSet;
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source\ConcreteBaseRequest;

// Only rules()/parent::rules() are in scope. A sibling messages() with a
// ...parent::messages() spread is a different method and a different parent
// call — it is left completely untouched by the conversion.
class MessagesChildRequest extends ConcreteBaseRequest
{
    public function schema(FluentSchema $rules): RuleSet
    {
        return parent::schema($rules)->merge([
            'body' => $rules->string()->required(),
        ]);
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'body.required' => 'The body is required.',
        ];
    }
}

?>
