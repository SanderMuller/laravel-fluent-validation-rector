# Laravel Fluent Validation Rector

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sandermuller/laravel-fluent-validation-rector.svg?style=flat-square)](https://packagist.org/packages/sandermuller/laravel-fluent-validation-rector)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/sandermuller/laravel-fluent-validation-rector/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/sandermuller/laravel-fluent-validation-rector/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub PHPStan Action Status](https://img.shields.io/github/actions/workflow/status/sandermuller/laravel-fluent-validation-rector/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/sandermuller/laravel-fluent-validation-rector/actions?query=workflow%3Aphpstan+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/sandermuller/laravel-fluent-validation-rector.svg?style=flat-square)](https://packagist.org/packages/sandermuller/laravel-fluent-validation-rector)
[![License](https://img.shields.io/packagist/l/sandermuller/laravel-fluent-validation-rector.svg?style=flat-square)](LICENSE.md)

Rector rules for migrating Laravel validation to [sandermuller/laravel-fluent-validation](https://github.com/sandermuller/laravel-fluent-validation). Pipe-delimited strings, array-based rules, `Rule::` objects, and Livewire `#[Rule]` attributes all convert to FluentRule method chains.

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

## Installation

```bash
composer require --dev sandermuller/laravel-fluent-validation-rector
```

Requires PHP 8.3+, Rector 2.5+, and `sandermuller/laravel-fluent-validation` ^1.32.0. On an older fluent-validation, see the [pin table](https://sandermuller.github.io/laravel-fluent-validation-rector/installation).

## Getting started

```php
// rector.php
use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Set\FluentValidationSetList;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/app'])
    ->withSets([FluentValidationSetList::ALL]);
```

```bash
vendor/bin/rector process --dry-run   # preview
vendor/bin/rector process             # apply
vendor/bin/pint                       # format
```

`ALL` runs converters, grouping and trait insertion. Three sets stay out of it on purpose and run as later passes: `SIMPLIFY`, `POLISH`, and `SCHEMA`.

## Documentation

Full documentation lives at **https://sandermuller.github.io/laravel-fluent-validation-rector/**.

- [Why this package?](https://sandermuller.github.io/laravel-fluent-validation-rector/why-this-package) · [Installation](https://sandermuller.github.io/laravel-fluent-validation-rector/installation) · [Getting started](https://sandermuller.github.io/laravel-fluent-validation-rector/getting-started) · [Sets](https://sandermuller.github.io/laravel-fluent-validation-rector/sets)
- [String and array converters](https://sandermuller.github.io/laravel-fluent-validation-rector/converters) · [Livewire attributes](https://sandermuller.github.io/laravel-fluent-validation-rector/livewire) · [Wildcard grouping](https://sandermuller.github.io/laravel-fluent-validation-rector/grouping) · [Trait insertion](https://sandermuller.github.io/laravel-fluent-validation-rector/traits)
- [Simplify](https://sandermuller.github.io/laravel-fluent-validation-rector/simplify) · [Docblock polish](https://sandermuller.github.io/laravel-fluent-validation-rector/polish) · [Adopting FluentSchema](https://sandermuller.github.io/laravel-fluent-validation-rector/schema)
- [The `#[FluentRules]` attribute](https://sandermuller.github.io/laravel-fluent-validation-rector/fluent-rules-attribute) · [Configuration](https://sandermuller.github.io/laravel-fluent-validation-rector/configuration)
- [Formatter integration](https://sandermuller.github.io/laravel-fluent-validation-rector/formatter) · [Diagnostics](https://sandermuller.github.io/laravel-fluent-validation-rector/diagnostics) · [Parity harness](https://sandermuller.github.io/laravel-fluent-validation-rector/parity) · [AI assistant integration](https://sandermuller.github.io/laravel-fluent-validation-rector/ai-assistant)
- [Rule reference](https://sandermuller.github.io/laravel-fluent-validation-rector/rules-reference) · [Detection and limitations](https://sandermuller.github.io/laravel-fluent-validation-rector/limitations)

The sources are in [`docs/`](docs/README.md); the frozen public surface is in [`PUBLIC_API.md`](PUBLIC_API.md).

## Testing

```bash
composer test          # vendor/bin/pest
composer qa            # format → rector → phpstan → test
```

The suite runs against [Orchestra Testbench](https://github.com/orchestral/testbench), so no host Laravel app is required.

## Changelog

Release notes live in [CHANGELOG.md](CHANGELOG.md) and on the [releases page](https://github.com/sandermuller/laravel-fluent-validation-rector/releases).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for the dev setup, test workflow, and PR conventions.

## Security

Disclose vulnerabilities privately. See [SECURITY.md](SECURITY.md).

## Credits

- [Sander Muller](https://github.com/sandermuller)
- [All contributors](https://github.com/sandermuller/laravel-fluent-validation-rector/contributors)

## License

MIT. See [LICENSE.md](LICENSE.md).
