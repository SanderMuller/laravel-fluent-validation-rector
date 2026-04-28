<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector;

use SanderMuller\FluentValidationRector\Internal\Diagnostics as InternalDiagnostics;

/**
 * Backwards-compatibility shim for `SanderMuller\FluentValidationRector\Diagnostics`.
 *
 * The class was always `@internal` (per the PHPDoc tag on the
 * canonical `Internal\Diagnostics` class). 0.20.0 moved it into the
 * `Internal\` sub-namespace so the namespace structure agrees with
 * the documentation. This file keeps any (in-violation) downstream
 * import of the old name resolving to the canonical class for one
 * minor cycle. Will be deleted in 1.0.
 *
 * @deprecated since 0.20.0 — use
 *   `SanderMuller\FluentValidationRector\Internal\Diagnostics`. The
 *   class is `@internal`; downstream importers should not be
 *   depending on it at all. Removal slated for 1.0.
 *
 * @internal
 */
// Use string literals rather than `::class` here — a `Diagnostics::class`
// expression in this file would require an actual class declaration in
// scope, which is exactly what we're avoiding (the canonical class lives
// at Internal\Diagnostics; we just want the old name to resolve).
class_alias(InternalDiagnostics::class, 'SanderMuller\\FluentValidationRector\\Diagnostics');
