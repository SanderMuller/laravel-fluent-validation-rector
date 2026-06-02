## Performance benchmarks

This package's rules run per-AST-node during Rector traversal, so the hot path is
the rule pipeline in `src/Rector/` — and the fast-path rule-string cache in
`src/Rector/Concerns/ConvertsValidationRuleStrings.php`.

Performance work goes through the **autoresearch** loop, not a Pest group. The
benchmark scripts live in `autoresearch/` and run this package's rules against a
synthetic consumer corpus, measuring wall-clock via `hrtime()`:

```bash
php autoresearch/rule-pipeline-bench.php   # whole-pipeline wall-clock
php autoresearch/per-rule-profile.php      # per-rule breakdown
```

Benchmark when touching the rule pipeline or its caches: capture a baseline,
make one change, re-run, and keep it only if the metric improves. The
`autoresearch` skill drives the full measure → change → keep-or-revert loop —
activate it for any sustained optimization work.
