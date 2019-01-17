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

use Amp\Loop;
use Desperado\ServiceBus\Storage\AmpPosgreSQL\AmpPostgreSQLAdapter;
use Desperado\ServiceBus\Storage\StorageConfiguration;

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
