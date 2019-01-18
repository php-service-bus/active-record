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
 * @property string $id
 * @property string $first_value
 * @property string $second_value
 */
final class TestTable extends Table
{
    /**
     * @inheritDoc
     */
    protected static function tableName(): string
    {
        return 'test_table';
    }
}
