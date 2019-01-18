<?php

/**
 * PHP Service Bus (publish-subscribe pattern) active record implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

use ServiceBus\ActiveRecord\Table;

/**
 * @property string   $title
 * @property string   $description
 * @property-read int $pk
 */
final class QwertyTable extends Table
{
    /**
     * @inheritDoc
     */
    protected static function tableName(): string
    {
        return 'qwerty';
    }
}
