<?php

/**
 * PHP Service Bus (publish-subscribe pattern) active record implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

use Amp\Loop;
use ServiceBus\Storage\Common\StorageConfiguration;
use ServiceBus\Storage\Sql\AmpPosgreSQL\AmpPostgreSQLAdapter;
use function ServiceBus\Storage\Sql\equalsCriteria;

include __DIR__ . '/../vendor/autoload.php';

$adapter = new AmpPostgreSQLAdapter(
    StorageConfiguration::fromDSN('pgsql://postgres:123456789@localhost:5432/test')
);

Loop::run(
    static function() use ($adapter): \Generator
    {
        /** @var array<int, \QwertyTable> $entries */
        $entries = yield \QwertyTable::findBy($adapter, [equalsCriteria('id', 'someId')], 100);

        /** @var \QwertyTable|null $entry */
        $entry = yield \QwertyTable::find($adapter, 'someId');

        /** @var \QwertyTable|null $entry */
        $entry = yield \QwertyTable::findOneBy($adapter, [equalsCriteria('title', 'expected title')]);
    }
);
