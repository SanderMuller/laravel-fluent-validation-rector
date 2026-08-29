# Formatter integration

**The emit is not formatter-clean by design.** Run a formatter after the rector:

```bash
vendor/bin/rector process && vendor/bin/pint --dirty
```

Three cosmetic seams a formatter closes. The names are PHP-CS-Fixer's; Pint ships the same fixers under the same names in its default Laravel preset, so most Laravel projects already have them.

1. Imports are inserted at prepend position, not alphabetically. Use `ordered_imports`.
2. Unused imports may remain, such as a `Livewire\Attributes\Rule` import after the attribute is stripped. Use `no_unused_imports`.
3. Generated `@return` docblocks emit `Illuminate\Contracts\Validation\ValidationRule` fully qualified. `fully_qualified_strict_types` hoists it to a `use`.

PHP-CS-Fixer users on a custom ruleset should check all three are enabled. Without any formatter the output is rougher than the examples here, but it is valid PHP.

For the cleanest pre-formatter output:

```php
return RectorConfig::configure()
    ->withImportNames()
    ->withRemovingUnusedImports()
    ->withSets([FluentValidationSetList::ALL]);
```

## Line breaks

Each generated call goes on its own line:

```php
FluentRule::string()
    ->required()
    ->max(255);
```

The breaks are stamped only on calls the rule creates, so calls already inline in your source stay inline. Run a chain-collapsing formatter after Rector if you prefer single-line chains.
