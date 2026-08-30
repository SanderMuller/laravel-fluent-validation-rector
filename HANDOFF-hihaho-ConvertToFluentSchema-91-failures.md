# Handoff — `ConvertToFluentSchemaRector` causes test failures in hihaho

**Author:** Sander (via Claude)  **Date:** 2026-07-13 (updated 2026-07-14 for 1.10.0)
**Package versions in hihaho:** `laravel-fluent-validation-rector` **1.10.0** (was 1.9.0), `laravel-fluent-validation` **1.32.0**
**Rule:** `SanderMuller\FluentValidationRector\Rector\ConvertToFluentSchemaRector`

---

## UPDATE — 1.10.0 (91 → ~75 failures)

1.10.0 added the `parent::rules()` → `parent::schema($builder)` rewrite plus a
`parentSchemaConversionIsProvable()` gate (rule source lines ~325–374). This fixed the *provable* concrete-base
chains (e.g. `Player\SessionClosedRequest` now correctly emits `parent::schema($rules)`). Remaining failures split
into **three** buckets — one is an environment issue on hihaho's side, two are residual rule gaps:

### (i) STALE RECTOR CACHE — hihaho-side, fix first before blaming the rule
The 1.10 run was done **without clearing `./.cache/rector`** (2847 cached entries from the 1.9 run). Rector's file
cache then skipped files it thought were unchanged, producing an **inconsistent tree**: some children in an
inheritance chain were cache-skipped (left on `rules()`) while their parent still converted to `schema()`, stranding
`parent::rules()`. Proof: `app/Http/Requests/DatatablesPaginationRequest.php` — a plain convertible leaf with **no**
`parent::rules()` and **no** closure — is `diff=0` (untouched), which no rule-logic branch can explain. Same for
`Player\SessionStartedRequest` and `Player\SubtitleChangedRequest` (structurally identical to the converted
`SessionClosedRequest`, yet untouched). **Action:** `rm -rf .cache/rector && vendor/bin/rector process`, then re-test.
This should clear the cache-victim subset on its own.

### (ii) `parent::rules()` in a body the rule declines to convert — residual rule gap
When the rule **skips** a child (see below) but its parent **does** convert, the child's `parent::rules()` dangles.
1.10 only rewrites `parent::rules()` in children it actually converts. Two skip-reasons hit real hihaho code:

- **FluentRule inside a plain closure** → `methodBodyIsSafeToInjectBuilder()` bails (closure boundary), so the whole
  method is left as `rules()`. Real case: `app/Http/Requests/Api/v2/VideoContainer/GenerateVideoUploadUrlRequest.php`
  — its `rules()` puts a `FluentRule::integer()` inside `->when(callback: function (RuleSet $rules) {...})`. Parent
  (`Video\GenerateVideoUploadUrlRequest`, concrete) converts → child's `parent::rules()` strands. (~36 failures under
  1.9; still failing.)
- **No `FluentRule::` static at all in the body** → the "must contain ≥1 FluentRule static" convertibility gate makes
  the rule never fire on a pure-inheritance override. Real case: `app/Http/Requests/Player/SaveActionRequest.php` —
  `return parent::rules()->modify('history', fn (ArrayRule $rule) => $rule->required());`. Nothing to rewrite → left
  on `rules()`, parent converted → `parent::rules()` strands.

  **Fix direction:** the parent-conversion rewrite (`parent::rules()` → `parent::schema($builder)`) should be
  *independent* of whether the body also contains a convertible `FluentRule::` static. If a class uses HasFluentRules,
  its parent provably converts, and the body calls `parent::rules()`, the method should be renamed to `schema()` and
  the `parent::rules()` rewritten **even when the body has no FluentRule static of its own** (SaveActionRequest) and
  **even when the only FluentRule statics live in closures** (GenerateVideoUploadUrlRequest — the closure statics can
  stay `FluentRule::`, only the `parent::rules()` and the method signature need changing). Add fixtures for both.

### (iii) Direct `->rules()` in unit tests — unchanged, still ~28 (see Cause B below)

Everything below is the original 1.9 analysis; §3's abstract-vs-concrete framing is now handled by
`parentSchemaConversionIsProvable()`, but the closure / no-static residuals in (ii) above are not.

---
<!-- ORIGINAL 1.9 ANALYSIS BELOW -->


> ⚠️ The local checkout of this repo is at tag **1.8.0**, which predates this rule.
> `git pull` / checkout ≥ 1.9.0 before working — the rule and its fixtures below don't exist on 1.8.0.

---

## 1. Summary

Adding `ConvertToFluentSchemaRector::class` to hihaho's `rector.php` `->withRules([...])` and running rector
rewrote **111 FormRequests** (`rules(): RuleSet` → `schema(FluentSchema $rules): RuleSet`, `FluentRule::` → `$rules->`).
The full backend suite then reports **91 failed / 6217 passed** (PhpStorm runner XML `count name="failed" value="91"`).

**Every** failure is the same exception:

```
BadMethodCallException: Method App\Http\Requests\...\<Request>::rules does not exist
  at vendor/laravel/framework/.../Macroable.php:117  (FormRequest __call fallback)
```

i.e. something still calls `->rules()` on a request whose `rules()` the rule renamed to `schema()`.

The 91 split into **two distinct causes**. Cause A is a genuine **rule defect** (breaks production runtime, not just tests).
Cause B is **test-side coupling** (a policy decision, not necessarily a rule bug).

---

## 2. Exact breakdown (all 91 accounted for)

| # | Failing request class | Cause | Why |
|---:|---|---|---|
| 36 | `Api\v2\VideoContainer\GenerateVideoUploadUrlRequest` | **A** | its `schema()` body calls `parent::rules()` |
| 5 | `Player\SaveActionRequest` | **A** | `parent::rules()` |
| 5 | `Player\SessionStartedRequest` | **A** | `parent::rules()` |
| 5 | `Player\InteractionClickedRequest` | **A** | `parent::rules()` |
| 5 | `Player\ChapterItemClickedRequest` | **A** | `parent::rules()` |
| 2 | `Player\SaveDetailsRequest` | **A** | `parent::rules()` |
| 2 | `Player\MenuItemClickedRequest` | **A** | `parent::rules()` |
| 1 | `Player\SessionClosedRequest` | **A** | `parent::rules()` |
| 1 | `Player\SubtitleChangedRequest` | **A** | `parent::rules()` |
| 1 | `Player\SaveOptionalVariableRequest` | **A** | `parent::rules()` |
| 14 | `Platform\CheckPanoptoConnectionRequest` | **B** | unit test calls `new …()->rules()` |
| 11 | `Enrich\StoreInteractionTtsSettingsRequest` | **B** | test calls `$request->rules()` |
| 2 | `VideoContainer\StoreSubscriptionRequest` | **B** | test calls `$request->rules()->all()` |
| 1 | `Player\OpenIphoneAppRequest` | **B** | unit test calls `new …()->rules()` |

**Cause A = 63 failures. Cause B = 28 failures.**

---

## 3. Cause A — the rule defect (63 failures, production breakage)

### What happens

hihaho has request inheritance chains where a **concrete** base request holds shared rules and subclasses
extend them via `parent::rules()`:

```php
// app/Http/Requests/Player/SessionClosedRequest.php  (BEFORE)
final class SessionClosedRequest extends SessionHistoryRequest
{
    public function rules(): RuleSet
    {
        return parent::rules()->merge([
            self::VIDEO_TIME_ON_CLOSE => FluentRule::integer()->bail()->required(),
        ]);
    }
}
```

The rule converts **both** the base and the child, but only rewrites the `FluentRule::` static — it leaves the
`parent::rules()` call untouched:

```php
// AFTER rector — BROKEN
final class SessionClosedRequest extends SessionHistoryRequest
{
    public function schema(FluentSchema $rules): RuleSet
    {
        return parent::rules()->merge([                     // ← parent::rules() no longer exists
            self::VIDEO_TIME_ON_CLOSE => $rules->integer()->bail()->required(),
        ]);
    }
}
```

At runtime `HasFluentRules::createDefaultValidator()` calls the child's `schema()`, which runs `parent::rules()`.
The base (`Player\SessionHistoryRequest`, a **concrete** `class`) was itself renamed to `schema()`, so no ancestor
defines `rules()` → `FormRequest::__call` throws `BadMethodCallException` (late static binding names the child class,
hence "SessionClosedRequest::rules does not exist"). **This is a live player endpoint — it 500s in production, not
only under test.**

### Why the existing guard misses it

The rule already documents this exact hazard (`convertClass()`, the `$class->isAbstract()` block) and guards it —
**but only for `abstract` base classes**, requiring `#[FluentRules]` to opt in. Every base class that breaks here is
a **concrete** `class`, so the guard never fires:

- `App\Http\Requests\Video\GenerateVideoUploadUrlRequest` — `class` (concrete), converted → 36 child failures
- `App\Http\Requests\Player\SessionHistoryRequest` — `class` (concrete), converted → player child failures
- `App\Http\Requests\Player\SessionRequest` — `class` (concrete), converted

(Contrast: `SaveWorkshopRequest` and `SaveQuestionRequest` **are** `abstract` and were correctly held back by the
guard — their subclasses' `parent::rules()` still works. So the abstract guard is doing its job; the gap is that the
concrete-base case is unguarded.)

### The per-file constraint (why this is the hard part)

Rector processes one file at a time and cannot see, from `SessionHistoryRequest.php`, that other files subclass it
and call `parent::rules()`. Two honest options:

- **Option A1 — rewrite the call, not just refuse (preferred).** In `convertClass()`, when rewriting a body, also
  rewrite `parent::rules()` → `parent::schema($builder)` and `$this->rules()` → `$this->schema($builder)` (same
  receiver-swap you already do for `FluentRule::`). For the hihaho chains this is *correct*: the concrete base
  **does** convert to `schema(FluentSchema $rules)`, so `parent::schema($rules)` resolves and returns the base
  `RuleSet`. **Caveat:** only safe when the parent actually converts. If the parent is an abstract-not-opted-in base
  (stays `rules()`), rewriting the child's `parent::rules()` → `parent::schema()` would break instead. Rector can't
  prove the parent's fate per-file — so pair this with a bail (below) for the unprovable case, or gate the rewrite
  behind the same `#[FluentRules]` audit opt-in already used for abstract classes.

- **Option A2 — bail on ambiguity (safe, less complete).** In `rulesMethodIsConvertible()` / a new guard, **refuse to
  convert** any `rules()` whose body contains `parent::rules()` or `$this->rules()`, and log an actionable skip. This
  stops the *child* converting — but note it is **not sufficient alone**, because the *base* still converts and the
  child's untouched `parent::rules()` still dangles. To make A2 correct you must **also** not convert a class that is
  a base of such a child; per-file that effectively means a two-pass/collector approach or an explicit opt-in.

**Recommendation:** implement A1's call-rewrite (`parent::rules()`/`$this->rules()` → `…schema($builder)`) **and** add
the A2 bail as the fallback when the rule can't establish the parent converts (mirror the existing `#[FluentRules]`
opt-in the abstract path already uses — reuse that as the "I assert this whole chain converts" signal). Extend the
current `$class->isAbstract()` guard so it also triggers for a concrete class whose `rules()` body calls
`parent::rules()`/`$this->rules()` without the opt-in.

---

## 4. Cause B — direct `->rules()` calls in unit tests (28 failures)

hihaho has request-level unit tests that assert on the rule set by calling `rules()` directly, e.g.:

```php
// tests/Feature/Http/Requests/Platform/CheckPanoptoConnectionRequestTest.php
FluentRulesTester::for(new CheckPanoptoConnectionRequest()->rules()) …

// tests/Feature/Http/Requests/Enrich/StoreInteractionTtsSettingsRequestTest.php
$validator = validator($data, $request->rules());
```

These are **not** a rule bug — the rule can't rewrite test call-sites, and these tests reach into the request's
rule-building method directly. This is a **decision for the team**, not the rector author:

- **B-fix option 1:** update the ~28 call-sites to the new API (call `schema(new FluentSchema)` / go through the
  validation pipeline). Straightforward but churny.
- **B-fix option 2:** the base package (`laravel-fluent-validation`) exposes a stable test accessor
  (e.g. `resolveRules()` that runs whichever of `schema()`/`rules()` exists) so tests don't couple to the method
  name. This is arguably the right long-term fix and lives in the **base package**, not the rector.

**Recommendation:** raise B separately with Sander. It does not block fixing A. If the SCHEMA set is meant to be a
drop-in, a base-package test accessor (B option 2) is the cleaner path.

### hihaho test files hitting Cause B (fixture references, real-world)

- `tests/Feature/Http/Requests/Platform/CheckPanoptoConnectionRequestTest.php` (14)
- `tests/Feature/Http/Requests/Enrich/StoreInteractionTtsSettingsRequestTest.php` (11)
- `tests/Feature/Http/Requests/VideoContainer/StoreSubscriptionRequestTest.php:122` (2)
- `tests/Feature/Http/Requests/BooleanToggleRequestRulesTest.php:34` → `OpenIphoneAppRequest` (1)
- (also `tests/Feature/Http/Requests/Video/Search/SearchVideosRequestRulesTest.php` uses `->rules()` — passed only
  because those two search requests weren't in the failing set; verify after any change)

---

## 5. Fixtures to add (Cause A)

Fixture convention in this repo: `tests/<RuleName>/Fixture/*.php.inc` (`-----` separator: before / after),
driver `tests/<RuleName>/<RuleName>RectorTest.php`, config under `tests/<RuleName>/config/`.
See the existing `tests/ValidationArrayToFluentRule/` for the exact shape.

Add these under **`tests/ConvertToFluentSchema/Fixture/`**:

1. **`concrete_base_with_parent_rules_child.php.inc`** — the core regression. A concrete base with a
   `FluentRule::`-based `rules()` and a child whose `rules()` does `return parent::rules()->merge([...])`.
   Expected AFTER (per Option A1): base → `schema(FluentSchema $rules)`; child → `schema(FluentSchema $rules)` with
   `parent::rules()` rewritten to `parent::schema($rules)`. Mirrors hihaho `Player\SessionHistoryRequest` +
   `Player\SessionClosedRequest`.

2. **`this_rules_self_call.php.inc`** — a single class whose `rules()` calls `$this->rules()` indirectly (or another
   method calls `$this->rules()`); assert the self-reference is rewritten to `$this->schema($rules)` or the class is
   skipped with a logged reason.

3. **`skip_concrete_base_without_opt_in.php.inc`** (if you go with the A2 bail): a concrete class whose `rules()`
   calls `parent::rules()` and has **no** `#[FluentRules]` → assert **no change** + skip log. Then a paired
   `..._with_opt_in.php.inc` with `#[FluentRules]` → asserts conversion happens.

4. Regression-guard the existing **abstract** behaviour still holds (there should already be a fixture; if not, add
   `abstract_base_without_attribute_is_skipped.php.inc`).

**Do not** over-fit to hihaho names in fixtures — use generic `StorePostRequest` / `BaseRequest` shapes; the hihaho
classes below are only the real-world reference.

---

## 6. Reproduction

```bash
# in hihaho, on the branch with the rule added to rector.php ->withRules([...])
git stash list         # working tree already has the 111-file conversion applied
DB_DATABASE="hihaho_phpunit_hihaho" php artisan test --compact
# → 91 failed, all "Method ...::rules does not exist"

# minimal repro of one Cause-A class + one Cause-B class:
php artisan test tests/Feature/Http/Requests/BooleanToggleRequestRulesTest.php --compact
```

The applied conversion is currently sitting in the hihaho working tree (`git diff` = 112 files incl. `rector.php`).
`git checkout .` reverts it. Re-run rector after fixing the rule to regenerate a clean conversion.

## 7. hihaho reference files (real-world "fixtures")

**Cause A — concrete converted bases (the defect source):**
- `app/Http/Requests/Video/GenerateVideoUploadUrlRequest.php` — `class` (concrete), converted → schema()
- `app/Http/Requests/Player/SessionHistoryRequest.php` — `class` (concrete), converted
- `app/Http/Requests/Player/SessionRequest.php` — `class` (concrete), converted

**Cause A — children that break (`parent::rules()` in body):**
- `app/Http/Requests/Api/v2/VideoContainer/GenerateVideoUploadUrlRequest.php` (36)
- `app/Http/Requests/Player/{SessionClosed,SessionStarted,SaveAction,SaveOptionalVariable,MenuItemClicked,`
  `ChapterItemClicked,InteractionClicked,SaveDetails,SubtitleChanged}Request.php`

**Correctly-skipped abstract bases (guard working — keep it working):**
- `app/Http/Requests/Admin/Workshop/SaveWorkshopRequest.php` (`abstract`, not converted)
- `app/Http/Requests/Enrich/Question/SaveQuestionRequest.php` (`abstract`, not converted)

---

## 8. TL;DR for the fixer

1. `git pull` this repo to ≥ 1.9.0 (local checkout is stale at 1.8.0).
2. Fix **Cause A** in `ConvertToFluentSchemaRector`: rewrite `parent::rules()`/`$this->rules()` → `…schema($builder)`
   when converting, and extend the abstract-only guard to cover **concrete** bases whose `rules()` calls
   `parent::rules()`/`$this->rules()` (bail + log unless `#[FluentRules]` opts in). Add fixtures in §5.
3. Flag **Cause B** (28 test-coupling failures) to Sander — likely a base-package test accessor, out of rector scope.
