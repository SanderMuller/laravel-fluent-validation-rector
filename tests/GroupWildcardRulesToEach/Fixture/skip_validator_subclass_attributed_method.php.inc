<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use SanderMuller\FluentValidation\FluentRules;
use SanderMuller\FluentValidation\FluentValidator;

// Regression fixture for the 0.17.1 fix. `JsonAdaptiveSubject`-shaped
// Validator subclass: qualifies for rule conversion via `#[FluentRules]`
// on `rulesWithoutPrefix()`, but its parent class
// (`JsonImportValidator::rulesWithPrefix()` in real codebases) walks
// the rule array and prepends a prefix to each top-level key. The flat
// `'*.title'` / `'*.sort_order'` shape round-trips through that walk;
// the GroupWildcard-folded `'*' => array()->children([...])` shape
// does NOT — nested children's keys get walked as if they were
// top-level fields with int indices, hitting
// `RuleSet::extractObjectMetadata()` with the wrong type.
//
// Hihaho 0.17.0 dogfood, 2026-04-27, surfaced this on
// `JsonAdaptiveSubjectImportValidator`: 5/5 of its tests failed
// post-fold. The fix gates GroupWildcard behind the
// `qualifiesForShapeChange()` predicate, which excludes
// attribute-only Validator subclasses. The wildcard sibling rules
// stay flat and the runtime contract holds.
class JsonAdaptiveSubjectImportValidator extends FluentValidator
{
    #[FluentRules]
    protected function rulesWithoutPrefix(): array
    {
        return [
            '*.title' => 'required|string|max:255',
            '*.sort_order' => 'required|integer|between:1,3',
        ];
    }
}

?>
