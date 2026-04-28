<?php declare(strict_types=1);

// Entry point for rector/extension-installer auto-registration.
//
// Registers a shutdown function in the parent Rector process that emits
// a single STDOUT line pointing at `.rector-fluent-validation-skips.log`
// when it contains entries. See `RunSummary` for details.

SanderMuller\FluentValidationRector\Internal\RunSummary::registerShutdownHandler();
