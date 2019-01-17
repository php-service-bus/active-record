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

namespace Desperado\ServiceBus\ActiveRecord\Tests\Stubs;

use Desperado\ServiceBus\ActiveRecord\Table;

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
