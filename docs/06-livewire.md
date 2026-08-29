# Livewire attributes

## `ConvertLivewireRuleAttributeRector`

Strips Livewire `#[Rule('...')]` and `#[Validate('...')]` property attributes and generates a `rules(): array` method. Three [config keys](13-configuration.md#convertlivewireruleattributerector) change what it does with messages, overlaps and real-time validation.

<details>
<summary>Supported shapes and bail conditions</summary>

- **Handles**:
  - String, list-array and keyed-array shapes. `#[Validate(['todos' => 'required', 'todos.*' => '...'])]` expands to one `rules()` entry per key.
  - Constructor-form rule objects (`new Password(8)`, `new Unique('users')`, `new Exists('roles')`) lower the same as their static-factory counterparts.
  - Maps `as:` / `attribute:` to `->label()`. When both appear, `attribute:` wins.
  - Keeps an empty `#[Validate]` marker on converted properties so `wire:model.live` real-time validation survives. Opt out with `PRESERVE_REALTIME_VALIDATION => false`.
- **Bails on**: hybrid `$this->validate([...])` calls (softenable via `KEY_OVERLAP_BEHAVIOR`), final parent `rules()` methods, unsupported attribute args, numeric keyed-array keys, and the `HasFluentValidation` compose conflict, where an ancestor uses the trait *and* the child carries `#[Rule]` / `#[Validate]`. There the trait's `getRules()` reads only `rules(): array`, so the attribute is already ignored at runtime, and converting would override the parent's `rules()` and drop parent-owned fields. Every bail is written to the [skip log](15-diagnostics.md).

Direct trait use on the class itself is **not** a bail. The rector merges the attribute rule into a local `rules()` array, since neither failure mode applies there.

</details>

**A converted component needs a diff review.** The rector verifies the generated `rules(): array` is syntactically correct; it cannot prove it behaves identically to the source attribute. If the component has no feature test covering validation, read the diff and watch for dropped `message:` (opt in with [`MIGRATE_MESSAGES`](13-configuration.md#convertlivewireruleattributerector)), explicit `onUpdate:`, or `translate: false`. All three are logged, and all need manual migration to Livewire's `messages(): array` hook or project config. A `messages:` arg (plural, not a Livewire argument) gets its own "likely typo for `message:`?" entry.
