# Why this package?

Rector rules that migrate Laravel validation to [`sandermuller/laravel-fluent-validation`](https://github.com/sandermuller/laravel-fluent-validation). Pipe-delimited strings, array-based rules, `Rule::` objects and Livewire `#[Rule]` attributes all become FluentRule chains.

```php
// Before
return [
    'email'  => 'required|email|max:255',
    'tags'   => ['nullable', 'array'],
    'tags.*' => 'string|max:50',
];

// After
return [
    'email' => FluentRule::email()->required()->max(255),
    'tags'  => FluentRule::array()->nullable()->each(
        FluentRule::string()->max(50),
    ),
];
```

Tested on a production codebase: **448 files converted, 3,469 tests still passing.**

## What it will not do

The rectors bail rather than guess. A shape they cannot prove equivalent is left exactly as it was and written to the [skip log](15-diagnostics.md), so a migration is never silently lossy. [Detection and limitations](18-limitations.md) lists what stays untouched and why.

## What it costs you

One `rector.php`, a formatter pass after it, and a diff review. Two of the sets, [`SIMPLIFY`](09-simplify.md) and [`POLISH`](10-polish.md), are deliberately not in `ALL`: they run after you have verified the first conversion.
