# Quick start

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

`ALL` runs converters, grouping and trait insertion over everything under `app/`. For most codebases that is the whole migration, and the output is ready to commit once Pint has run, because the emit is [deliberately not formatter-clean](14-formatter.md).

For finer control, pick [subsets](04-sets.md) or register [individual rules](17-rules-reference.md).

Two sets stay out of `ALL` on purpose. Run each as its own invocation, after the previous one has settled:

```bash
vendor/bin/rector process   # ALL, then review the diff
# …then, separately:
vendor/bin/rector process   # SIMPLIFY
vendor/bin/rector process   # POLISH
```
