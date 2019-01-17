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
