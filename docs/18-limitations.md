# Detection and limitations

## Detected without configuration

The converters find rules-shaped methods by content signature: a string-keyed `return [...]` whose values include a recognized rule string, a `Rule::*()` call, a FluentRule chain, or a constructor-form rule object. No config needed for any of these:

- `Validator::validate(...)` and the global `validator(...)` helper, when prefixed with `\` or in the global namespace.
- Custom-named rules methods (`editorRules()`, `rulesWithoutPrefix()`) on classes that qualify: FormRequest descendants, trait users, Livewire components, and [`#[FluentRules]`](12-fluent-rules-attribute.md)-marked methods.
- Dynamic args inside non-conditional tuples: `['max', $cond ? 15 : 20]`, `['between', config('a'), config('b')]`, `['max', $this->limit ?? 10]`.
- `#[Validate]` args: the rule string, `as:` / `attribute:` (→ `->label()`), and `onUpdate: false` as a real-time opt-out marker.

## Left untouched

- **A `SCHEMA` inheritance chain split across runs.** [`ConvertToFluentSchemaRector`](11-schema.md) rewrites a child's `parent::rules()` when the parent would convert if processed, and Rector gives a rule no way to confirm the parent's file is in this run. Process the chain together.

- **Namespace-less files.** Classes at file root with no `namespace` are skipped by the grouping and trait rectors. Laravel projects normally namespace, so this rarely comes up.
- **Rules built inside `withValidator()`.** That is a post-validation hook for adding errors via `$validator->after(...)`, not a rules definition. Imperative code stays.
- **`Collection::put()->merge()->all()` pipelines.** Runtime-resolved, so not statically determinable.
- **Multi-statement helper bodies.** Detection needs a single `return [...];`. A helper that assigns then returns stays untouched. Inline the return, or convert by hand.
- **A ternary picking the rule NAME.** `['nullable', $flag ? 'email' : 'url']` is left alone. A `->when(cond, thenFn, elseFn)` conversion is tractable, but three codebase audits found near-zero usage (single digits across 100+ FormRequests), and the closure form loses the terseness people reach for ternaries to get. Use `Rule::when(...)`, or branch the array outside the ternary. Ternaries, calls, match and nullsafe fetches *as a rule's argument* convert fine.
- **`#[Validate(..., onUpdate: true)]` and `translate: false`.** No FluentRule equivalent and no migration path; both land in the [skip log](15-diagnostics.md) for manual migration to Livewire's hooks or project config. `message:` is opt-in through [`MIGRATE_MESSAGES`](13-configuration.md#convertlivewireruleattributerector); with it off, those args are logged too.
