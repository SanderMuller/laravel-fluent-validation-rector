---
name: Bug report
about: Report incorrect or unexpected rector behavior
title: ''
labels: bug
assignees: ''
---

## Environment

- **Package version**: <!-- e.g. 0.14.1 -->
- **PHP version**: <!-- e.g. 8.3.10 -->
- **Laravel version**: <!-- e.g. 12.0 -->
- **Rector version**: <!-- output of `vendor/bin/rector --version` -->
- **OS**: <!-- e.g. macOS 14.5 / Ubuntu 24.04 -->

## Rector configuration

```php
// rector.php — relevant excerpt
```

## Expected behavior

<!-- What did you expect the rector to produce? -->

## Actual behavior

<!-- What did the rector actually produce? Paste the diff or final output. -->

## Minimal reproduction

<!--
Smallest possible input that reproduces the bug. Prefer:
1. A single PHP file showing the input fixture
2. The exact rector command invoked
3. The diff or error
-->

```php
// input.php

```

```text
// command + output

```

## Skip-log entry (if the rector skipped)

<!--
If the rector skipped the file with a diagnostic, paste the matching
[fluent-validation:skip] line from the skip log:
- verbose tier on:  <cwd>/.cache/rector-fluent-validation-skips.log
- verbose tier off: sys_get_temp_dir()/rector-fluent-validation-skips-<hash>.log
-->

```text

```

## Additional context

<!-- Anything else helpful: stack traces, related upstream changes, etc. -->
