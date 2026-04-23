<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

enum SubtitleSource: string
{
    case Paste = 'paste';

    case Upload = 'upload';
}

class RequiredIfArrayFormCategoryA
{
    public function rules(): array
    {
        $expected = 'admin';
        $suffix = 'tail';

        return [
            'subtitle' => FluentRule::string()->rule(['required_if', 'subtitleSource', SubtitleSource::Paste->value]),
            'password' => FluentRule::string()->rule(['required_if', 'type', 'admin', 'editor']),
            'note' => FluentRule::string()->rule(['exclude_unless', 'type', 'chapter']),
            'source' => FluentRule::string()->rule(['required_if', 'type', SubtitleSource::Upload]),
            'generic' => FluentRule::field()->rule(['required_if', 'type', 'custom']),
            'intValue' => FluentRule::string()->rule(['required_if', 'version', 1]),
            'boolValue' => FluentRule::string()->rule(['required_if', 'enabled', true]),
            'variable' => FluentRule::string()->rule(['required_if', 'type', $expected]),
            'concat' => FluentRule::string()->rule(['required_if', 'field', 'prefix-' . $suffix]),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

enum SubtitleSource: string
{
    case Paste = 'paste';

    case Upload = 'upload';
}

class RequiredIfArrayFormCategoryA
{
    public function rules(): array
    {
        $expected = 'admin';
        $suffix = 'tail';

        return [
            'subtitle' => FluentRule::string()->requiredIf('subtitleSource', SubtitleSource::Paste->value),
            'password' => FluentRule::string()->requiredIf('type', 'admin', 'editor'),
            'note' => FluentRule::string()->excludeUnless('type', 'chapter'),
            'source' => FluentRule::string()->requiredIf('type', SubtitleSource::Upload->value),
            'generic' => FluentRule::field()->requiredIf('type', 'custom'),
            'intValue' => FluentRule::string()->requiredIf('version', 1),
            'boolValue' => FluentRule::string()->requiredIf('enabled', true),
            'variable' => FluentRule::string()->requiredIf('type', $expected),
            'concat' => FluentRule::string()->requiredIf('field', 'prefix-' . $suffix),
        ];
    }
}

?>
