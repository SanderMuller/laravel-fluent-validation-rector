# Trait insertion

The `TRAITS` set adds the fluent-validation trait to classes that now use `FluentRule`.

## `AddHasFluentRulesTraitRector`

Adds `use HasFluentRules;` to FormRequests using FluentRule, or declaring a [`schema(FluentSchema $rules)`](11-schema.md) builder, which needs the trait to dispatch. Configurable with a [`BASE_CLASSES`](13-configuration.md#addhasfluentrulestraitrector) allowlist.

**Abstract bases are skipped by default**, since a subclass may array-manipulate `parent::rules()` and a base-level trait would then be wrong. Marking the base's `rules()` with [`#[FluentRules]`](12-fluent-rules-attribute.md) asserts subclass-safety and adds the trait anyway, so the base flows through the full `ALL` + `SCHEMA` pipeline. Listing the base in `base_classes` works too.

## `AddHasFluentValidationTraitRector`

Adds the trait to Livewire components using FluentRule, picking the plain or Filament variant from direct trait usage.

<details>
<summary>Variant selection and bail conditions</summary>

- Plain Livewire component → `HasFluentValidation`.
- Filament's `InteractsWithForms` (v3/v4) or `InteractsWithSchemas` (v5) used **directly** on the class → `HasFluentValidationForFilament` plus a four-method `insteadof` block.
- The wrong variant already on a class → swapped, and the orphaned import dropped.
- **Bails on ancestor-only Filament usage.** PHP method resolution through inheritance is fragile here, so the trait has to go on the concrete subclass by hand. Skip-logged.

</details>

**If you have a shared base**, declare `use HasFluentRules;` (or `HasFluentValidation`) on it once, and every subclass inherits it. Both rectors walk the ancestor chain by reflection and will not re-add a trait a parent already has, so no configuration is needed for that case.
