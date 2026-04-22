<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests;

use PHPUnit\Framework\TestCase;
use SanderMuller\FluentValidation\Rules\DateRule;
use SanderMuller\FluentValidation\Rules\EmailRule;
use SanderMuller\FluentValidation\Rules\ImageRule;
use SanderMuller\FluentValidation\Rules\NumericRule;
use SanderMuller\FluentValidation\Rules\PasswordRule;
use SanderMuller\FluentValidationRector\Rector\InlineMessageParamRector;
use SanderMuller\FluentValidationRector\Rector\InlineMessageSurface;

/**
 * Pins the verbatim skip-log template strings that consumers see when the
 * rector refuses to rewrite a `message()`/`messageFor()` chain. The rector
 * emits six distinct category templates; this test guards the long-form
 * Password L11/L12 template against regression + verifies the taxonomy's
 * short-form reason strings match spec §1.4 wording.
 *
 * The file-write mechanism (RunSummary, Diagnostics) is covered by
 * RunSummaryTest — this test covers content, not transport.
 */
final class InlineMessageParamSkipLogTemplateTest extends TestCase
{
    public function testPasswordL11L12SkipTemplateContainsKeyBreadcrumbs(): void
    {
        $template = InlineMessageParamRector::PASSWORD_L11_L12_SKIP_TEMPLATE;

        // Key breadcrumbs for consumers googling the silent-no-op symptom.
        $this->assertStringContainsString('getFromLocalArray', $template, 'Must name the actual L11/L12-divergent lookup code path.');
        $this->assertStringContainsString('password.password', $template, 'Must explain the key that fails L11 resolution.');
        $this->assertStringContainsString('L11', $template);
        $this->assertStringContainsString('L12', $template);
        $this->assertStringContainsString('3-key lookup', $template, 'Must explain why L11 misses.');

        // Actionable suggestions for L11 consumers.
        $this->assertStringContainsString('password.letters', $template);
        $this->assertStringContainsString('password.mixed', $template);
        $this->assertStringContainsString('messages(): array', $template);
    }

    public function testPasswordTemplateIsSingleLineFormat(): void
    {
        // Skip-log entries are single-line in the log file (writeSkipEntry
        // in LogsSkipReasons appends one \n at the end). The template
        // itself must not contain newlines or entries would span multiple
        // lines and break consumer-side grep tooling.
        $template = InlineMessageParamRector::PASSWORD_L11_L12_SKIP_TEMPLATE;
        $this->assertStringNotContainsString("\n", $template, 'Template must be single-line.');
        $this->assertStringNotContainsString("\r", $template);
    }

    public function testCompositeMethodsExclusionTable(): void
    {
        $composites = InlineMessageSurface::COMPOSITE_METHODS;

        // Spec §1.3 taxonomy — peer handoff 2026-04-22.
        $this->assertContains('digits', $composites[NumericRule::class]);
        $this->assertContains('digitsBetween', $composites[NumericRule::class]);
        $this->assertContains('exactly', $composites[NumericRule::class]);

        $this->assertContains('between', $composites[DateRule::class]);
        $this->assertContains('betweenOrEqual', $composites[DateRule::class]);

        $imageComposites = $composites[ImageRule::class];
        foreach (['width', 'height', 'minWidth', 'maxWidth', 'minHeight', 'maxHeight', 'ratio', 'dimensions'] as $method) {
            $this->assertContains($method, $imageComposites);
        }
    }

    public function testModeModifierExclusionTable(): void
    {
        $modes = InlineMessageSurface::MODE_MODIFIERS;

        $emailModes = $modes[EmailRule::class];
        foreach (['rfcCompliant', 'strict', 'validateMxRecord', 'preventSpoofing', 'withNativeValidation'] as $method) {
            $this->assertContains($method, $emailModes);
        }

        $passwordModes = $modes[PasswordRule::class];
        foreach (['min', 'max', 'letters', 'mixedCase', 'numbers', 'symbols', 'uncompromised'] as $method) {
            $this->assertContains($method, $passwordModes);
        }
    }

    public function testFactoriesWithoutMessageParam(): void
    {
        $noMessage = InlineMessageSurface::FACTORIES_WITHOUT_MESSAGE_PARAM;

        // Spec §1.3 skip categories 4/5/6.
        $this->assertContains('date', $noMessage);
        $this->assertContains('dateTime', $noMessage);
        $this->assertContains('password', $noMessage);
        $this->assertContains('field', $noMessage);
        $this->assertContains('anyOf', $noMessage);
    }

    public function testRuleObjectKeyOverridesMirrorAddRuleMatch(): void
    {
        $overrides = InlineMessageParamRector::RULE_OBJECT_KEY_OVERRIDES;

        // Ten explicit match cases from HasFieldModifiers::addRule —
        // rector must follow vendor source, not user-facing docs.
        $this->assertSame('required', $overrides['RequiredIf']);
        $this->assertSame('required', $overrides['RequiredUnless']);
        $this->assertSame('prohibited', $overrides['ProhibitedIf']);
        $this->assertSame('prohibited', $overrides['ProhibitedUnless']);
        $this->assertSame('exclude', $overrides['ExcludeIf']);
        $this->assertSame('exclude', $overrides['ExcludeUnless']);
        $this->assertSame('in', $overrides['In']);
        $this->assertSame('not_in', $overrides['NotIn']);
        $this->assertSame('unique', $overrides['Unique']);
        $this->assertSame('exists', $overrides['Exists']);
    }
}
