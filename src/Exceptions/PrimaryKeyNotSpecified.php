<?php

/**
 * Active record implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 0);

namespace ServiceBus\Storage\ActiveRecord\Exceptions;

/**
 *
 */
final class PrimaryKeyNotSpecified extends \InvalidArgumentException
{
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
