<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use App\Http\Requests\ExternalBaseRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\FluentRules;
use SanderMuller\FluentValidation\HasFluentRules;
use SanderMuller\FluentValidation\RuleSet;

// #[FluentRules] on rules() asserts the whole chain converts, so parent::rules()
// is rewritten to parent::schema($rules) even though the parent — an external,
// non-autoloadable base here — can't be introspected by reflection. Without the
// attribute this would bail as an unprovable parent.
class OverrideChildRequest extends ExternalBaseRequest
{
    use HasFluentRules;

    #[FluentRules]
    public function rules(): RuleSet
    {
        return parent::rules()->merge([
            'body' => FluentRule::string()->required(),
        ]);
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use App\Http\Requests\ExternalBaseRequest;
use SanderMuller\FluentValidation\FluentRules;
use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidation\HasFluentRules;
use SanderMuller\FluentValidation\RuleSet;

// #[FluentRules] on rules() asserts the whole chain converts, so parent::rules()
// is rewritten to parent::schema($rules) even though the parent — an external,
// non-autoloadable base here — can't be introspected by reflection. Without the
// attribute this would bail as an unprovable parent.
class OverrideChildRequest extends ExternalBaseRequest
{
    use HasFluentRules;

    #[FluentRules]
    public function schema(FluentSchema $rules): RuleSet
    {
        return parent::schema($rules)->merge([
            'body' => $rules->string()->required(),
        ]);
    }
}

?>
