# Roadmap

What's coming up. Release history lives in CHANGELOG.md; detailed designs live in `SPEC_*.md` files at the repo root.

## In progress

- **Array-form `#[Rule([...])]` / `#[Validate([...])]` attribute conversion** — see [SPEC_ARRAY_FORM_RULE_ATTRIBUTE.md](./SPEC_ARRAY_FORM_RULE_ATTRIBUTE.md). Targeted for 0.4.6. Extracts a standalone `ArrayRuleConverter` service from `ValidationArrayToFluentRuleRector` and wires it into `ConvertLivewireRuleAttributeRector` so Livewire properties with array-form rule attributes convert like their string-form counterparts.
