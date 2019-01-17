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

use Desperado\ServiceBus\ActiveRecord\Table;

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
