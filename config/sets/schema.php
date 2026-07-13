<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Rector\ConvertToFluentSchemaRector;

return static function (RectorConfig $rectorConfig): void {
    // Opt-in, never bundled into ALL: adopting the schema(FluentSchema $rules)
    // builder is a stylistic choice. Run it as a separate pass after the
    // converters + traits have produced FluentRule chains on a HasFluentRules
    // class.
    $rectorConfig->rule(ConvertToFluentSchemaRector::class);
};
