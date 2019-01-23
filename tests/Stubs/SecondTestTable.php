<?php

/**
 * PHP Service Bus active record implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Storage\Sql\ActiveRecord\Tests\Stubs;

use ServiceBus\Storage\Sql\ActiveRecord\Table;

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
