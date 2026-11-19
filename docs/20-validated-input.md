# Validated input accessors

`VALIDATED_INPUT` is **opt-in** and not part of `ALL`. It does not convert validation rules. It rewrites code that reads validated input to shorter accessors.

```php
->withSets([FluentValidationSetList::VALIDATED_INPUT])
```

The `nullableString()`, `nullableInteger()` and `file()` rules fire only when PHPStan types the receiver as the package's `SanderMuller\FluentValidation\ValidatedInput`, which `safe()` returns on a `HasFluentRules` FormRequest and `RuleSet::check()->safe()` returns elsewhere. Laravel's own `Illuminate\Support\ValidatedInput` does not have these accessors. Each of these rules also checks that the installed laravel-fluent-validation version has the accessor (`file()` from 1.40.0, `nullableString()` from 1.41.0, `nullableInteger()` from 1.43.0), and does nothing on an older version.

## `UseValidatedInputNullableStringRector`

```php
// Before
$title = $safe->filled('title') ? $safe->string('title')->value() : null;
$slug = $safe->filled('slug') ? $safe->string('slug')->toString() : 'untitled';

// After
$title = $safe->nullableString('title');
$slug = $safe->nullableString('slug', 'untitled');
```

<details>
<summary>What qualifies, what is left alone</summary>

- **Reads**: `string('k')` or `str('k')`, then `->value()`, `->toString()`, `->__toString()`, a `(string)` cast or `strval()`.
- **Conditions**: `filled('k')`, and the inverted `! filled('k')` or `isNotFilled('k')` with the branches swapped.
- **Fallback**: `null` (the call then gets no second argument), or an expression typed `string|null` that is safe to read on every call: a literal, a constant or a variable that is certainly defined. The ternary reads the fallback only when the field is blank; the call reads it every time. So a method call, a property read (it can be uninitialized or magic, for example an Eloquent attribute) or a variable that may be undefined is left alone.
- **Receiver and key**: the same expression in the condition and in the read, read once instead of twice: a literal, a constant, a variable, or a declared property without hooks. A magic property is left alone. A key must be typed `string`.
- **Left alone**: `has()`, `?:`, a second argument to `string()`, any Stringable call between `string()` and the conversion (`->trim()->value()`), a receiver that is a call (`$request->safe()->filled(...)`), and a `filled()` with several keys or an array.

</details>

**Known difference.** `filled()` accepts a bool, an array and an object, for example an uploaded file. For those, `string()->value()` gives `'1'` or `''` (bool), `'Array'` with an "Array to string conversion" warning (array), or the string form of the object (the path, for a file). `nullableString()` gives the fallback. A field validated as a string cannot hold these values, but the rule cannot see the validation rules, so review the diff for fields that are not validated as strings.

**Type note.** Before laravel-fluent-validation 1.41.1, `nullableString()` is typed `?string`, also when the fallback is a string. On those versions, code that passes the result to a `string` parameter can get a new PHPStan error after the rewrite, although the runtime value is the same. From 1.41.1, the call is typed `string` when the fallback is a string.

## `UseValidatedInputNullableIntegerRector`

```php
// Before
$rank = $safe->filled('rank') ? $safe->integer('rank') : null;
$limit = $safe->filled('limit') ? $safe->integer('limit') : 10;

// After
$rank = $safe->nullableInteger('rank');
$limit = $safe->nullableInteger('limit', 10);
```

`nullableInteger()` is this ternary, so the result is the same for every value. The conditions, receiver and key follow the same rules as for `nullableString()`. The fallback must be typed `int|null` and be a literal, a constant or a certainly defined variable. A second argument to `integer()` is left alone.

## `UseValidatedInputFileRector`

```php
// Before
$avatar = $safe->input('avatar') instanceof UploadedFile ? $safe->input('avatar') : null;

// After
$avatar = $safe->file('avatar');
```

The result is the same for every value. The inverted `! ($safe->input('avatar') instanceof UploadedFile) ? null : $safe->input('avatar')` is accepted too. A non-null fallback becomes `$safe->file('avatar') ?? $fallback`. That form gives the same result for any fallback, reads the fallback only when there is no file, and keeps the static type. `file('avatar', $fallback)` would return null for a fallback that is not a file. The class must be `Illuminate\Http\UploadedFile`; Symfony's `UploadedFile` is left alone.

## Receiver type on Laravel 12

The rules read the receiver's type from PHPStan. Laravel 12 types `FormRequest::safe()` as `ValidatedInput|array`, so `$safe = $request->safe()` is not narrowed, and the rules leave its reads alone. Laravel 13 and `HasFluentRules` from laravel-fluent-validation 1.40.0 type `safe()` without arguments as `ValidatedInput`.

## `UseValidatedInputDateRector`

```php
// Before
$publishedAt = $safe->filled('published_at') ? $safe->date('published_at') : null;

// After
$publishedAt = $safe->date('published_at');
```

`date()` returns null for a blank or absent field, so the check changes nothing. The rule also accepts the inverted `isNotFilled('k')` with the branches swapped. It leaves `has()` and `missing()` alone: they look up the key with `Arr::has()`, which reads `*` and `{first}` differently from the `data_get()` that `date()` uses. A format or timezone argument must be a string literal, `null` or a certainly defined variable, because the ternary reads it only when the field is filled. The receiver and key follow the same rules as for `nullableString()`. The receiver can be Laravel's `ValidatedInput` or a `Request`, but not a subclass that overrides `filled()`, `isNotFilled()`, `date()` or a helper they call.

## `UseValidatedInputEnumRector`

```php
// Before
$status = PostStatus::tryFrom($safe->string('status')->value());

// After
$status = $safe->enum('status', PostStatus::class);
```

The reads are the same as for `nullableString()`. The receiver can be Laravel's `ValidatedInput` or a `Request`, but not a subclass that overrides `string()`, `str()`, `enum()` or a helper they call. The enum must be string-backed, and no case value may be blank or numeric: for a blank field `enum()` returns null where `tryFrom('')` returns a `''` case, and `enum()` passes a bool, an int or a float to `tryFrom()` as it is, so `false` can match a `'0'` case and `1.5` a `'1'` case. An int-backed enum is left alone, and so are `from()`, a default argument to `string()`, and a Stringable call between `string()` and the conversion.

**Known differences.** For a float with a fraction, both forms give null, but `enum()` also makes PHP log a "float to int" deprecation. For an array, the original gives null with an "Array to string conversion" warning, and `enum()` throws a `TypeError`. `Rule::enum()`, `string` and `in` validation reject both values, so they reach the read only from unvalidated input, for example a JSON body read straight from the request, or from a field validated without a type rule.
