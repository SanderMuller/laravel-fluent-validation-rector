# Parity harness

A few rectors change which Laravel rule object handles validation at runtime. The functional suite proves the source-to-source AST shape; the parity harness under `tests/Parity/` proves the resulting rule sets produce equivalent error bags when Laravel runs them.

**In scope**, because semantics may change:

- `SimplifyRuleWrappersRector` promotes `field()->rule('accepted')` to typed chains.
- `GroupWildcardRulesToEachRector` folds wildcard siblings into `each(...)`.
- `PromoteFieldFactoryRector` rewrites `field()->required()->rule('string')` to `string()->required()`.

The pure-refactor rectors ship structural coverage only; their transformations do not change which rule class handles validation.

## Writing a fixture

Each lives at `tests/Parity/Fixture/<RectorName>/<case>.php` and returns:

```php
return [
    'rules_before' => ['field' => 'pre-rector-rule-shape'],
    'rules_after'  => ['field' => FluentRule::typed()->...],
    'payloads' => [
        'descriptive name' => ['field' => 'value-to-test'],
    ],
    // only when the divergence is intentional:
    'allowed_divergences' => [
        'descriptive name' => [
            'category'  => DivergenceCategory::ImplicitTypeConstraint,
            'rationale' => 'why this one is acceptable',
        ],
    ],
];
```

The harness validates each payload against both rule sets and diffs the error bags. Outcomes: `MATCH`, `BEFORE_REJECTS_AFTER_PASSES`, `AFTER_REJECTS_BEFORE_PASSES`, `BOTH_REJECT_DIFFERENT_MESSAGES`, `BOTH_REJECT_DIFFERENT_ORDER`, or `SKIPPED` for the database and closure denylist.

## Allowed divergences

Some transformations legitimately change behaviour. `boolean()->accepted()` rejects the `'yes'` and `'on'` strings that bare `accepted` allows, because of boolean's implicit pre-check. Categorize with `DivergenceCategory`:

| Category | Meaning |
|---|---|
| `ImplicitTypeConstraint` | the typed rule attaches a constraint the pre-rector form lacked |
| `MessageKeyDrift` | same outcome, different message-key path |
| `AttributeLabelDrift` | same outcome, `:attribute` renders differently |
| `OrderDependentPipeline` | same messages, different per-field order |

The category constrains the allowed runtime outcome, so a mismatched one fails the test, and the rationale lives beside the divergence.

`tests/Parity/CoverageTest.php` asserts every in-scope rector has at least one fixture. A new semantics-changing rector has to extend that list and ship a fixture before it merges.
