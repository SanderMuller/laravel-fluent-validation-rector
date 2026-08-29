---
layout: home

hero:
  name: Fluent Validation Rector
  text: Migrate Laravel validation, one rector run at a time
  tagline: "Pipe strings, rule arrays, Rule:: objects and Livewire attributes become FluentRule chains. Shapes it cannot prove equivalent are left alone and logged."
  actions:
    - theme: brand
      text: Why this package?
      link: /why-this-package
    - theme: alt
      text: Quick start
      link: /quick-start
    - theme: alt
      text: GitHub
      link: https://github.com/SanderMuller/laravel-fluent-validation-rector

features:
  - title: Converts what it can prove
    details: "Strings, arrays, Rule:: objects, parent spreads and Livewire attributes. Anything it cannot prove equivalent stays as it was, with a skip-log entry saying why."
    link: /converters
  - title: Cleanup as a second pass
    details: SIMPLIFY and POLISH stay out of ALL on purpose. They run after you have read the first diff.
    link: /simplify
  - title: Checked against the runtime
    details: A parity harness validates real payloads against both rule sets and diffs the error bags, so a semantics-changing rewrite has to prove itself.
    link: /parity
---
