<?php

/**
 * Active record implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Storage\ActiveRecord\Tests\Stubs;

use ServiceBus\Storage\ActiveRecord\Table;

/**
 * @property string   $title
 * @property-read int $pk
 */
final class SecondTestTable extends Table
{
    /**
     * {@inheritdoc}
     */
    protected static function tableName(): string
    {
        return 'second_test_table';
    }

    /**
     * {@inheritdoc}
     */
    protected static function primaryKey(): string
    {
        return 'pk';
    }
}
