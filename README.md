[![Build Status](https://travis-ci.org/php-service-bus/active-record.svg?branch=master)](https://travis-ci.org/php-service-bus/active-record)
[![Code Coverage](https://scrutinizer-ci.com/g/php-service-bus/active-record/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/php-service-bus/active-record/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/php-service-bus/active-record/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/php-service-bus/active-record/?branch=master)

## What is it?

Active Record pattern implementation for use in [service-bus](https://github.com/php-service-bus/service-bus) framework.

## Examples

```php
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
```

### Load
```php
$adapter = new AmpPostgreSQLAdapter(
    new StorageConfiguration('pgsql://postgres:123456789@localhost:5432/test')
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
```

### Add\Update\Remove
```php
$adapter = new AmpPostgreSQLAdapter(
    new StorageConfiguration('pgsql://postgres:123456789@localhost:5432/test')
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

```
