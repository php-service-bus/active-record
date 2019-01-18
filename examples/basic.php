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

include __DIR__ . '/../vendor/autoload.php';

$adapter = new AmpPostgreSQLAdapter(
    StorageConfiguration::fromDSN('pgsql://postgres:123456789@localhost:5432/test')
);

Loop::run(
    static function() use ($adapter): \Generator
    {
        /** @var \QwertyTable $entry */
        $entry = yield \QwertyTable::new($adapter, ['title' => 'some title', ['description' => 'some description']]);

        $entry->title = 'new title';

        yield $entry->save();
        yield $entry->remove();
    }
);
