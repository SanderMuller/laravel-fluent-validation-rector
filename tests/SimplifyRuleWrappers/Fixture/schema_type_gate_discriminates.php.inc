<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentSchema;

// A look-alike builder whose chain shape mirrors a FluentSchema chain exactly.
// The receiver TYPE — resolved via isObjectType(..., FluentSchema) — is the
// only discriminator: the real `$rules` seed lowers, the look-alike `$other`
// is left untouched even though its `->string()->rule()` shape is identical.
class LookAlikeBuilder
{
    public function string(): self
    {
        return $this;
    }

    public function rule(string $rule): self
    {
        return $this;
    }
}

class SchemaTypeGateDiscriminates
{
    public function schema(FluentSchema $rules): array
    {
        $other = new LookAlikeBuilder();

        return [
            'real' => $rules->string()->rule('max:5'),
            'fake' => $other->string()->rule('max:5'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentSchema;

// A look-alike builder whose chain shape mirrors a FluentSchema chain exactly.
// The receiver TYPE — resolved via isObjectType(..., FluentSchema) — is the
// only discriminator: the real `$rules` seed lowers, the look-alike `$other`
// is left untouched even though its `->string()->rule()` shape is identical.
class LookAlikeBuilder
{
    public function string(): self
    {
        return $this;
    }

    public function rule(string $rule): self
    {
        return $this;
    }
}

class SchemaTypeGateDiscriminates
{
    public function schema(FluentSchema $rules): array
    {
        $other = new LookAlikeBuilder();

        return [
            'real' => $rules->string()
                ->max(5),
            'fake' => $other->string()->rule('max:5'),
        ];
    }
}

?>
