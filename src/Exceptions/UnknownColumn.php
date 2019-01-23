<?php

/**
 * PHP Service Bus active record implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Storage\Sql\ActiveRecord\Exceptions;

/**
 *
 */
final class UnknownColumn extends \InvalidArgumentException
{
    /**
     * @param string $column
     * @param string $table
     */
    public function __construct(string $column, string $table)
    {
        parent::__construct(
            \sprintf(
                'Column "%s" does not exist in table "%s"',
                $column, $table
            )
        );
    }
}
