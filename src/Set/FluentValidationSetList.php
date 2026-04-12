<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Set;

final class FluentValidationSetList
{
    private const string SETS_DIR = __DIR__ . '/../../config/sets/';

    public const string ALL = self::SETS_DIR . 'all.php';

    public const string CONVERT = self::SETS_DIR . 'convert.php';

    public const string GROUP = self::SETS_DIR . 'group.php';

    public const string TRAITS = self::SETS_DIR . 'traits.php';

    public const string SIMPLIFY = self::SETS_DIR . 'simplify.php';
}
