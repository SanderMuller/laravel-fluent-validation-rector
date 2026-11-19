<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Source;

use Illuminate\Database\Eloquent\Model;

/**
 * An Eloquent attribute is a magic property: reading it can throw.
 *
 * @property string|null $title
 */
final class Post extends Model {}
