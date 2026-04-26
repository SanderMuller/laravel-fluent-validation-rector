<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentRulesTrait\Fixture;

use Illuminate\Support\Facades\Validator;
use SanderMuller\FluentValidation\FluentRule;
use Tests\TestCase;

// Test class using `Validator::make([...], ['x' => FluentRule::...])`
// to exercise a custom rule under unit-test conditions. The widened
// detector matched the FluentRule usage, then the trait rector added
// `HasFluentRules` to the test class itself. Hihaho dogfood
// (2026-04-26) found 2 such test files. Test classes are not on the
// FormRequest ancestor chain — silent skip.
final class DocumentValidationTest extends TestCase
{
    public function test_document_rules(): void
    {
        $validator = Validator::make([
            'doc' => 'fixture.pdf',
        ], [
            'doc' => FluentRule::file()->required()->extensions(['pdf']),
        ]);

        $this->assertFalse($validator->fails());
    }
}

?>
