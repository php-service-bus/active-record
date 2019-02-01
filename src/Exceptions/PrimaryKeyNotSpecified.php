<?php

/**
 * Active record implementation
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
final class PrimaryKeyNotSpecified extends \InvalidArgumentException
{
    /**
     * @param string $expectedKey
     */
    public function __construct(string $expectedKey)
    {
        parent::__construct(
            \sprintf(
                'In the parameters of the entity must be specified element with the index "%s" (primary key)',
                $expectedKey
            )
        );
    }
}
