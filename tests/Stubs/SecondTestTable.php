<?php

/**
 * PHP Service Bus (publish-subscribe pattern) active record implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\ActiveRecord\Tests\Stubs;

use ServiceBus\ActiveRecord\Table;

/**
 * @property string   $title
 * @property-read int $pk
 */
final class SecondTestTable extends Table
{
    /**
     * @inheritDoc
     */
    protected static function tableName(): string
    {
        return 'second_test_table';
    }

    /**
     * @inheritDoc
     */
    protected static function primaryKey(): string
    {
        return 'pk';
    }
}
