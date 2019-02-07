[![Build Status](https://travis-ci.org/php-service-bus/active-record.svg?branch=v3.0)](https://travis-ci.org/php-service-bus/active-record)
[![Code Coverage](https://scrutinizer-ci.com/g/php-service-bus/active-record/badges/coverage.png?b=v3.0)](https://scrutinizer-ci.com/g/php-service-bus/active-record/?branch=v3.0)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/php-service-bus/active-record/badges/quality-score.png?b=v3.0)](https://scrutinizer-ci.com/g/php-service-bus/active-record/?branch=v3.0)

## What is it?

Simple Active Record pattern implementation

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

##### Load
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

##### Add\Update\Remove
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
