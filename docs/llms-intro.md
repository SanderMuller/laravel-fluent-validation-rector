# Laravel Fluent Validation Rector

> Rector rules that migrate Laravel validation to `sandermuller/laravel-fluent-validation`, distributed as the Composer dev dependency `sandermuller/laravel-fluent-validation-rector`. Pipe-delimited strings, rule arrays, `Rule::` objects and Livewire `#[Rule]` attributes convert to FluentRule chains; a `SCHEMA` pass then trades the static prefix for an injected builder.

Key properties an agent should know before running it:

- **It bails rather than guesses.** A shape the rule cannot prove equivalent is left exactly as it was and written to the skip log. A partial conversion is the designed outcome, not a failure.
- **The skip log is opt-in and cached.** Set `FLUENT_VALIDATION_RECTOR_VERBOSE=actionable` and pass `--clear-cache`, or a bailed file stays silent because Rector cached the no-op result.
- **Three sets are deliberately not in `ALL`.** `SIMPLIFY`, `POLISH` and `SCHEMA` each run as their own `vendor/bin/rector process` invocation, after the previous one has been reviewed. Bundling them into one config is a documented mistake.
- **The emit is not formatter-clean by design.** Run Pint or PHP-CS-Fixer afterwards; `ordered_imports`, `no_unused_imports` and `fully_qualified_strict_types` are what finish the job.
- **Some rewrites change runtime behaviour**, and those are the ones the parity harness covers: promoting `field()` to a typed factory attaches an implicit `string` or `numeric` constraint that `FieldRule` did not have. Read the diff on `SIMPLIFY`.
- **`#[FluentRules]` is a narrow per-method opt-in.** It does not lift the cross-class parent-safety scan, the Validator-subclass fold guard, or the denylist on framework hook methods.
- **`SCHEMA` needs the whole inheritance chain in one run.** Converting a child while its parent stays on `rules()` produces `Call to undefined method parent::schema()`.
