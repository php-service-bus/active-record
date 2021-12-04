<?php

/**
 * Active record implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Storage\ActiveRecord;

use Amp\Promise;
use Amp\Success;
use ServiceBus\Storage\ActiveRecord\Exceptions\PrimaryKeyNotSpecified;
use ServiceBus\Storage\ActiveRecord\Exceptions\UnknownColumn;
use ServiceBus\Storage\ActiveRecord\Exceptions\UpdateRemovedEntry;
use ServiceBus\Storage\Common\QueryExecutor;
use ServiceBus\Storage\Sql\AmpPosgreSQL\AmpPostgreSQLAdapter;
use function Amp\call;
use function ServiceBus\Common\uuid;
use function ServiceBus\Storage\Sql\buildQuery;
use function ServiceBus\Storage\Sql\equalsCriteria;
use function ServiceBus\Storage\Sql\fetchAll;
use function ServiceBus\Storage\Sql\fetchOne;
use function ServiceBus\Storage\Sql\find;
use function ServiceBus\Storage\Sql\insertQuery;
use function ServiceBus\Storage\Sql\remove;
use function ServiceBus\Storage\Sql\unescapeBinary;
use function ServiceBus\Storage\Sql\updateQuery;

/**
 * @api
 * @todo     : pk generation strategy
 *
 * @template T
 */
abstract class Table
{
    /**
     * Stored entry identifier.
     *
     * @psalm-var non-empty-string|null
     *
     * @var string|null
     */
    private $insertId;

    /**
     * @var QueryExecutor
     */
    private $queryExecutor;

    /**
     * Data collection.
     *
     * @psalm-var array<non-empty-string, mixed>
     *
     * @var array
     */
    private $data = [];

    /**
     * New record flag.
     *
     * @var bool
     */
    private $isNew = true;

    /**
     * Data change list.
     *
     * @psalm-var array<non-empty-string, mixed>
     *
     * @var array
     */
    private $changes = [];

    /**
     * Columns info.
     *
     * [
     *   'id'    => 'uuid',
     *   'title' => 'varchar'
     * ]
     *
     * @psalm-var array<non-empty-string, non-empty-string>
     *
     * @var array
     */
    private $columns = [];

    /**
     * Receive associated table name.
     *
     * @psalm-return non-empty-string
     */
    abstract protected static function tableName(): string;

    /**
     * Receive primary key name.
     *
     * @psalm-return non-empty-string
     */
    protected static function primaryKey(): string
    {
        return 'id';
    }

    /**
     * Create and persist entry.
     *
     * @psalm-param array<non-empty-string, float|int|string|null> $data
     *
     * @psalm-return Promise<T>
     *
     * @throws \ServiceBus\Storage\ActiveRecord\Exceptions\UnknownColumn
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
     */
    final public static function new(QueryExecutor $queryExecutor, array $data): Promise
    {
        return call(
            static function () use ($data, $queryExecutor): \Generator
            {
                /**
                 * @var Table $entity
                 */
                $entity = yield self::create($queryExecutor, $data, true);

                /** @psalm-var non-empty-string $result */
                $result = yield $entity->save();

                $entity->insertId = $result;

                /** @psalm-var T $entity */

                return $entity;
            }
        );
    }

    /**
     * Find entry by primary key.
     *
     * @psalm-return Promise<T|null>
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
     * @throws \ServiceBus\Storage\Common\Exceptions\IncorrectParameterCast
     * @throws \ServiceBus\Storage\Common\Exceptions\OneResultExpected
     * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
     */
    final public static function find(QueryExecutor $queryExecutor, int|string $id): Promise
    {
        return self::findOneBy($queryExecutor, [equalsCriteria(static::primaryKey(), $id)]);
    }

    /**
     * Find one entry by specified criteria.
     *
     * @psalm-param array<array-key, \Latitude\QueryBuilder\CriteriaInterface> $criteria
     *
     * @psalm-return Promise<T|null>
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
     * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed Error getting operation  result
     * @throws \ServiceBus\Storage\Common\Exceptions\OneResultExpected The result must contain only 1 row
     */
    final public static function findOneBy(QueryExecutor $queryExecutor, array $criteria): Promise
    {
        return call(
            static function () use ($queryExecutor, $criteria): \Generator
            {
                /** @var \ServiceBus\Storage\Common\ResultSet $resultSet */
                $resultSet = yield find($queryExecutor, static::tableName(), $criteria);

                /** @psalm-var array<string, string|int|float|null>|null $data */
                $data = yield fetchOne($resultSet);

                unset($resultSet);

                if (\is_array($data))
                {
                    return yield self::create($queryExecutor, $data, false);
                }

                return null;
            }
        );
    }

    /**
     * Find entries by specified criteria.                                $orderBy
     *
     * @psalm-param array<array-key, \Latitude\QueryBuilder\CriteriaInterface> $criteria
     * @psalm-param positive-int|null                                          $limit
     * @psalm-param array<non-empty-string, non-empty-string>|null             $orderBy
     *
     * @@psalm-return Promise<list<static>>
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
     * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed Error getting operation  result
     */
    final public static function findBy(
        QueryExecutor $queryExecutor,
        array         $criteria = [],
        ?int          $limit = null,
        ?int          $offset = null,
        ?array        $orderBy = null
    ): Promise {
        return call(
            static function () use ($queryExecutor, $criteria, $limit, $offset, $orderBy): \Generator
            {
                /** @var \ServiceBus\Storage\Common\ResultSet $resultSet */
                $resultSet = yield find(
                    queryExecutor: $queryExecutor,
                    tableName: static::tableName(),
                    criteria: $criteria,
                    limit: $limit,
                    offset: $offset,
                    orderBy: $orderBy
                );

                /** @var array<string, array<string, string|int|float|null>>|null $rows */
                $rows = yield fetchAll($resultSet);

                unset($resultSet);

                $result = [];

                if ($rows !== null)
                {
                    foreach ($rows as $row)
                    {
                        /** @var Table $entry */
                        $entry    = yield self::create($queryExecutor, $row, false);
                        $result[] = $entry;

                        unset($entry);
                    }
                }

                return $result;
            }
        );
    }

    /**
     * Save entry changes.
     *
     * Returns the ID of the saved entry, or the number of affected rows (in the case of an update)
     *
     * @psalm-return Promise<int|non-empty-string>
     *
     * @throws \ServiceBus\Storage\ActiveRecord\Exceptions\PrimaryKeyNotSpecified
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed Duplicate entry
     */
    final public function save(): Promise
    {
        return call(
            function (): \Generator
            {
                /** Store new entry */
                if ($this->isNew === true)
                {
                    $this->changes = [];

                    /** @psalm-var non-empty-string $lastInsertId */
                    $lastInsertId = yield $this->storeNewEntry($this->data);
                    $this->isNew  = false;

                    return $lastInsertId;
                }

                $changeSet = $this->changes;

                if (\count($changeSet) === 0)
                {
                    return 0;
                }

                /** @var int $affectedRows */
                $affectedRows  = yield $this->updateExistsEntry($changeSet);
                $this->changes = [];

                return $affectedRows;
            }
        );
    }

    /**
     * Refresh entry data.
     *
     * @psalm-return Promise<void>
     *
     * @throws \ServiceBus\Storage\ActiveRecord\Exceptions\UpdateRemovedEntry Unable to find an entry (possibly RC
     *                                                                        occurred)
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
     */
    public function refresh(): Promise
    {
        return call(
            function (): \Generator
            {
                /** @var \ServiceBus\Storage\Common\ResultSet $resultSet */
                $resultSet = yield find(
                    $this->queryExecutor,
                    static::tableName(),
                    [equalsCriteria(static::primaryKey(), $this->searchPrimaryKeyValue())]
                );

                $row = yield fetchOne($resultSet);

                unset($resultSet);

                if ($row === null)
                {
                    throw new UpdateRemovedEntry('Failed to update entity: data has been deleted');
                }

                $this->changes = [];

                /**
                 * @psalm-suppress MixedArgumentTypeCoercion
                 * @psalm-var array<non-empty-string, mixed> $parameters
                 */
                $parameters = unescapeBinary($this->queryExecutor, $row);

                $this->data = $parameters;
            }
        );
    }

    /**
     * Delete entry.
     *
     * @psalm-return Promise<int>
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\IncorrectParameterCast
     * @throws \ServiceBus\Storage\ActiveRecord\Exceptions\PrimaryKeyNotSpecified Unable to find primary key value
     */
    final public function remove(): Promise
    {
        if ($this->isNew === true)
        {
            return new Success(0);
        }

        return remove(
            $this->queryExecutor,
            static::tableName(),
            [equalsCriteria(static::primaryKey(), $this->searchPrimaryKeyValue())]
        );
    }

    /**
     * Receive the ID of the last entry added.
     *
     * @psalm-return non-empty-string|null
     */
    final public function lastInsertId(): ?string
    {
        return $this->insertId;
    }

    /**
     * @codeCoverageIgnore
     *
     * @psalm-return array<non-empty-string, mixed>
     */
    final public function __debugInfo(): array
    {
        return [
            'data'    => $this->data,
            'isNew'   => $this->isNew,
            'changes' => $this->changes,
            'columns' => $this->columns,
        ];
    }

    /**
     * @psalm-param non-empty-string $name
     *
     * @throws \ServiceBus\Storage\ActiveRecord\Exceptions\UnknownColumn
     */
    final public function __set(string $name, float|int|string|null $value): void
    {
        if (isset($this->columns[$name]))
        {
            $this->data[$name]    = $value;
            $this->changes[$name] = $value;

            return;
        }

        throw new UnknownColumn($name, static::tableName());
    }

    /**
     * @psalm-param non-empty-string $name
     */
    final public function __isset(string $name): bool
    {
        return isset($this->columns[$name]);
    }

    /**
     * @psalm-param non-empty-string $name
     */
    final public function __get(string $name): mixed
    {
        return $this->data[$name];
    }

    /**
     * Receive query execution handler.
     */
    final protected function queryExecutor(): QueryExecutor
    {
        return $this->queryExecutor;
    }

    /**
     * Store new entry.
     *
     * @psalm-param array<non-empty-string, mixed> $changeSet
     *
     * @psalm-return Promise<non-empty-string>
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\IncorrectParameterCast
     * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed
     */
    private function storeNewEntry(array $changeSet): Promise
    {
        return call(
            function () use ($changeSet): \Generator
            {
                $primaryKey = static::primaryKey();

                if (
                    \array_key_exists($primaryKey, $changeSet) === false
                    && \strtolower($this->columns[$primaryKey]) === 'uuid'
                ) {
                    $changeSet[$primaryKey] = uuid();
                }

                $queryBuilder = insertQuery(static::tableName(), $changeSet);

                /** @todo: fix me */
                if ($this->queryExecutor instanceof AmpPostgreSQLAdapter)
                {
                    /**
                     * @var \Latitude\QueryBuilder\Query\Postgres\InsertQuery $queryBuilder
                     */
                    $queryBuilder->returning($primaryKey);
                }

                $compiledQuery = $queryBuilder->compile();

                /**
                 * @psalm-suppress MixedArgumentTypeCoercion Invalid params() docblock
                 *
                 * @var \ServiceBus\Storage\Common\ResultSet $resultSet
                 */
                $resultSet = yield $this->queryExecutor->execute($compiledQuery->sql(), $compiledQuery->params());

                $insertedEntryId = (string) yield $resultSet->lastInsertId();

                if ($insertedEntryId === '')
                {
                    throw new \LogicException('Created entity id cant be empty');
                }

                if (isset($this->data[$primaryKey]) === false)
                {
                    $this->data[$primaryKey] = $insertedEntryId;
                }

                return $insertedEntryId;
            }
        );
    }

    /**
     * Update exists entry.
     *
     * @psalm-param array<non-empty-string, mixed> $changeSet
     *
     * @psalm-return Promise<int>
     *
     * @throws \ServiceBus\Storage\ActiveRecord\Exceptions\PrimaryKeyNotSpecified
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\IncorrectParameterCast
     * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed
     */
    private function updateExistsEntry(array $changeSet): Promise
    {
        return call(
            function () use ($changeSet): \Generator
            {
                $queryData = buildQuery(
                    updateQuery(static::tableName(), $changeSet),
                    [equalsCriteria(static::primaryKey(), $this->searchPrimaryKeyValue())]
                );

                /**
                 * @var \ServiceBus\Storage\Common\ResultSet $resultSet
                 */
                $resultSet = yield $this->queryExecutor->execute($queryData['query'], $queryData['parameters']);

                $this->changes = [];

                return $resultSet->affectedRows();
            }
        );
    }

    /**
     * @psalm-return non-empty-string
     *
     * @throws \ServiceBus\Storage\ActiveRecord\Exceptions\PrimaryKeyNotSpecified Unable to find primary key value
     */
    private function searchPrimaryKeyValue(): string
    {
        $primaryKey = static::primaryKey();

        if (isset($this->data[$primaryKey]) && \is_string($this->data[$primaryKey]) && $this->data[$primaryKey] !== '')
        {
            return $this->data[$primaryKey];
        }

        throw new PrimaryKeyNotSpecified($primaryKey);
    }

    /**
     * Create entry.
     *
     * @psalm-param array<string, string|int|float|null> $data
     *
     * @psalm-return Promise<T>
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
     */
    private static function create(QueryExecutor $queryExecutor, array $data, bool $isNew): Promise
    {
        return call(
            function () use ($queryExecutor, $data, $isNew): \Generator
            {
                $metadataExtractor = new MetadataLoader($queryExecutor);
                $entity = new static($queryExecutor);

                $entity->columns = yield $metadataExtractor->columns(static::tableName());

                if ($isNew === false)
                {
                    /**
                     * @psalm-var array<string, string|int|float|null> $data
                     */
                    $data = unescapeBinary($queryExecutor, $data);
                }

                foreach ($data as $key => $value)
                {
                    $entity->{$key} = $value;
                }

                $entity->isNew = $isNew;

                /** @psalm-var T $entity */
                return $entity;
            }
        );
    }

    final private function __construct(QueryExecutor $queryExecutor)
    {
        $this->queryExecutor = $queryExecutor;
    }
}
