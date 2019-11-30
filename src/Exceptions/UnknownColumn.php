<?php

/**
 * Active record implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Storage\ActiveRecord\Exceptions;

/**
 *
 */
final class UnknownColumn extends \InvalidArgumentException
{
    public function __construct(string $column, string $table)
    {
        parent::__construct(
            \sprintf(
                'Column "%s" does not exist in table "%s"',
                $column,
                $table
            )
        );
    }
}
