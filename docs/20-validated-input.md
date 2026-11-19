# Validated input accessors

`VALIDATED_INPUT` is **opt-in** and not part of `ALL`. It does not convert validation rules. It rewrites code that reads validated input to the accessors on the package's `ValidatedInput`, which `safe()` returns on a `HasFluentRules` FormRequest and `RuleSet::check()->safe()` returns elsewhere.

```php
->withSets([FluentValidationSetList::VALIDATED_INPUT])
```

Both rules fire only when PHPStan types the receiver as `SanderMuller\FluentValidation\ValidatedInput`. Laravel's own `Illuminate\Support\ValidatedInput` does not have these accessors. Each rule also checks that the installed laravel-fluent-validation version has the accessor (`file()` from 1.40.0, `nullableString()` from 1.41.0), and does nothing on an older version.

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

**Type note.** `nullableString()` returns `?string`, also when the fallback is a string. Code that passes the result to a `string` parameter can get a new PHPStan error after the rewrite, although the runtime value is the same.

## `UseValidatedInputFileRector`

```php
// Before
$avatar = $safe->input('avatar') instanceof UploadedFile ? $safe->input('avatar') : null;

// After
$avatar = $safe->file('avatar');
```

The result is the same for every value. The inverted `! ($safe->input('avatar') instanceof UploadedFile) ? null : $safe->input('avatar')` is accepted too. A non-null fallback becomes `$safe->file('avatar') ?? $fallback`. That form gives the same result for any fallback, reads the fallback only when there is no file, and keeps the static type. `file('avatar', $fallback)` would return null for a fallback that is not a file. The class must be `Illuminate\Http\UploadedFile`; Symfony's `UploadedFile` is left alone.
