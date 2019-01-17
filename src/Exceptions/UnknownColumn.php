<?php

/**
 * PHP Service Bus (publish-subscribe pattern implementation) active record component
 * The simplest implementation of the "ActiveRecord" pattern
 *
 * @author  Maksim Masiukevich <desperado@minsk-info.ru>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace Desperado\ServiceBus\ActiveRecord\Exceptions;

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
