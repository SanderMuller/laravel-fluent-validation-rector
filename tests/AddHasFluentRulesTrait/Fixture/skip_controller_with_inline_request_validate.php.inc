<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentRulesTrait\Fixture;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SanderMuller\FluentValidation\FluentRule;

// Controller with inline `$request->validate([...])` call holding
// FluentRule entries. The widened `usesFluentRule()` (0.14.0) walked
// every method and matched the FluentRule usage, then incorrectly
// added the FormRequest-internal `HasFluentRules` trait. Controllers
// never invoke `createDefaultValidator()`, so the trait is dead code
// — but worse, hihaho dogfood (2026-04-26) found 14 such Controllers
// got the trait inserted. The class qualification gate must require
// FormRequest ancestry before the trait is added.
final class StoreBugReportController extends Controller
{
    public function __invoke(Request $request): void
    {
        $request->validate([
            'title' => FluentRule::string()->required()->max(255),
            'body' => FluentRule::string()->required(),
        ]);
    }
}

?>
