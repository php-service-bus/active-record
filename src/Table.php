<?php

/**
 * Active record implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Storage\ActiveRecord;

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
use Amp\Promise;
use Amp\Success;
use ServiceBus\Storage\ActiveRecord\Exceptions\PrimaryKeyNotSpecified;
use ServiceBus\Storage\ActiveRecord\Exceptions\UnknownColumn;
use ServiceBus\Storage\ActiveRecord\Exceptions\UpdateRemovedEntry;
use ServiceBus\Storage\Common\QueryExecutor;
use ServiceBus\Storage\Sql\AmpPosgreSQL\AmpPostgreSQLAdapter;

/**
 * @api
 * @todo: pk generation strategy
 */
abstract class Table
{
    /**
     * Stored entry identifier.
     *
     * @var string|null
     */
    private $insertId = null;

    /** @var QueryExecutor */
    private $queryExecutor;

    /**
     * Data collection.
     *
     * @psalm-var array<string, string|int|float|null>
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
     * @psalm-var array<string, string|int|float|null>
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
     * @psalm-var array<string, string>
     *
     * @var array
     */
    private $columns = [];

    /**
     * Receive associated table name.
     */
    abstract protected static function tableName(): string;

    /**
     * Receive primary key name.
     */
    protected static function primaryKey(): string
    {
        return 'id';
    }

    /**
     * Create and persist entry.
     *
     * @psalm-param array<string, float|int|string|null> $data
     *
     * @return Promise<\ServiceBus\Storage\ActiveRecord\Table>
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
                /** @var Table $entity */
                $entity = yield from static::create($queryExecutor, $data, true);

                /** @var int|string $result */
                $result = yield $entity->save();

                $entity->insertId = (string) $result;

                return $entity;
            }
        );
    }

    /**
     * Find entry by primary key.
     *
     * @param int|string $id
     *
     * @return Promise<static|null>
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
     * @throws \ServiceBus\Storage\Common\Exceptions\IncorrectParameterCast
     * @throws \ServiceBus\Storage\Common\Exceptions\OneResultExpected
     * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
     */
    final public static function find(QueryExecutor $queryExecutor, $id): Promise
    {
        return self::findOneBy($queryExecutor, [equalsCriteria(static::primaryKey(), $id)]);
    }

    /**
     * Find one entry by specified criteria.
     *
     * @psalm-param array<mixed, \Latitude\QueryBuilder\CriteriaInterface> $criteria
     *
     * @return Promise<static|null>
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

                if (\is_array($data) === true)
                {
                    /** @var static $entry */
                    $entry = yield from self::create($queryExecutor, $data, false);

                    return $entry;
                }
            }
        );
    }

    /**
     * Find entries by specified criteria.
     *
     * @psalm-param  array<mixed, \Latitude\QueryBuilder\CriteriaInterface> $criteria
     * @psalm-param  array<string, string>                      $orderBy
     *
     * @param \Latitude\QueryBuilder\CriteriaInterface[] $criteria
     *
     * @return Promise<list<static>>
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
     * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed Error getting operation  result
     */
    final public static function findBy(
        QueryExecutor $queryExecutor,
        array $criteria = [],
        ?int $limit = null,
        array $orderBy = []
    ): Promise {
        return call(
            static function () use ($queryExecutor, $criteria, $limit, $orderBy): \Generator
            {
                /** @var \ServiceBus\Storage\Common\ResultSet $resultSet */
                $resultSet = yield find($queryExecutor, static::tableName(), $criteria, $limit, $orderBy);

                /** @var array<string, array<string, string|int|float|null>>|null $rows */
                $rows = yield fetchAll($resultSet);

                unset($resultSet);

                $result = [];

                if ($rows !== null)
                {
                    foreach ($rows as $row)
                    {
                        /** @var Table $entry */
                        $entry    = yield from self::create($queryExecutor, $row, false);
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
     * @return Promise<int|string>
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

                    /** @var int|string $lastInsertId */
                    $lastInsertId = yield from $this->storeNewEntry($this->data);
                    $this->isNew  = false;

                    return $lastInsertId;
                }

                $changeSet = $this->changes;

                if (\count($changeSet) === 0)
                {
                    return 0;
                }

                /** @var int $affectedRows */
                $affectedRows  = yield from $this->updateExistsEntry($changeSet);
                $this->changes = [];

                return $affectedRows;
            }
        );
    }

    /**
     * Refresh entry data.
     *
     * @return Promise<void>
     *
     * @throws \ServiceBus\Storage\ActiveRecord\Exceptions\UpdateRemovedEntry Unable to find an entry (possibly RC
     *                                                                        occured)
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

                /**
                 * @psalm-var array<string, string|int|float|null>|null $row
                 *
                 * @var array $row
                 */
                $row = yield fetchOne($resultSet);

                unset($resultSet);

                if (\is_array($row) === true)
                {
                    $this->changes = [];

                    /** @psalm-var array<string, string|int|null|float> $parameters */
                    $parameters = unescapeBinary($this->queryExecutor, $row);

                    $this->data = $parameters;

                    return;
                }

                throw new UpdateRemovedEntry('Failed to update entity: data has been deleted');
            }
        );
    }

    /**
     * Delete entry.
     *
     * @return Promise<int>
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
     */
    final public function lastInsertId(): ?string
    {
        return $this->insertId;
    }

    /**
     * @codeCoverageIgnore
     *
     * @psalm-return array<string, mixed>
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
     * @param float|int|string|null $value
     *
     * @throws \ServiceBus\Storage\ActiveRecord\Exceptions\UnknownColumn
     *
     * @return void
     */
    final public function __set(string $name, $value): void
    {
        if (isset($this->columns[$name]) === true)
        {
            $this->data[$name]    = $value;
            $this->changes[$name] = $value;

            return;
        }

        throw new UnknownColumn($name, static::tableName());
    }

    final public function __isset(string $name): bool
    {
        return isset($this->columns[$name]);
    }

    /**
     * @return float|int|string|null
     */
    final public function __get(string $name)
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
     * @psalm-suppress InvalidReturnType
     *
     * @psalm-param    array<string, string|int|float|null> $changeSet
     *
     * @return \Generator<string>
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\IncorrectParameterCast
     * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed
     */
    private function storeNewEntry(array $changeSet): \Generator
    {
        $primaryKey = static::primaryKey();

        if (\array_key_exists($primaryKey, $changeSet) === false && \strtolower($this->columns[$primaryKey]) === 'uuid')
        {
            $changeSet[$primaryKey] = uuid();
        }

        $queryBuilder = insertQuery(static::tableName(), $changeSet);

        /** @todo: fix me */
        if ($this->queryExecutor instanceof AmpPostgreSQLAdapter)
        {
            /**
             * @psalm-suppress UndefinedMethod Cannot find method in traits
             *
             * @var \Latitude\QueryBuilder\Query\Postgres\InsertQuery $queryBuilder
             */
            $queryBuilder->returning($primaryKey);
        }

        $compiledQuery = $queryBuilder->compile();

        /**
         * @psalm-suppress MixedTypeCoercion Invalid params() docblock
         *
         * @var \ServiceBus\Storage\Common\ResultSet $resultSet
         */
        $resultSet = yield $this->queryExecutor->execute($compiledQuery->sql(), $compiledQuery->params());

        /** @var int|string|null $insertedEntryId */
        $insertedEntryId = yield $resultSet->lastInsertId();

        unset($queryBuilder, $compiledQuery, $resultSet);

        if (isset($this->data[$primaryKey]) === false)
        {
            $this->data[$primaryKey] = (string) $insertedEntryId; // it cant be null
        }

        return $insertedEntryId;
    }

    /**
     * Update exists entry.
     *
     * @psalm-suppress InvalidReturnType
     *
     * @psalm-param    array<string, string|int|float|null> $changeSet
     *
     * @return \Generator<int>
     *
     * @throws \ServiceBus\Storage\ActiveRecord\Exceptions\PrimaryKeyNotSpecified
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\IncorrectParameterCast
     * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed
     */
    private function updateExistsEntry(array $changeSet): \Generator
    {
        /**
         * @var string $query
         * @var array  $parameters
         * @psalm-var array<string, string|int|float|null> $parameters
         */
        [$query, $parameters] = buildQuery(
            updateQuery(static::tableName(), $changeSet),
            [equalsCriteria(static::primaryKey(), $this->searchPrimaryKeyValue())]
        );

        /**
         * @psalm-suppress MixedTypeCoercion Invalid params() docblock
         *
         * @var \ServiceBus\Storage\Common\ResultSet $resultSet
         */
        $resultSet = yield $this->queryExecutor->execute($query, $parameters);

        $this->changes = [];
        $affectedRows  = $resultSet->affectedRows();

        unset($query, $parameters, $resultSet);

        return $affectedRows;
    }

    /**
     * @throws \ServiceBus\Storage\ActiveRecord\Exceptions\PrimaryKeyNotSpecified Unable to find primary key value
     */
    private function searchPrimaryKeyValue(): string
    {
        $primaryKey = static::primaryKey();

        if (isset($this->data[$primaryKey]) === true && (string) $this->data[$primaryKey] !== '')
        {
            return (string) $this->data[$primaryKey];
        }

        throw new PrimaryKeyNotSpecified($primaryKey);
    }

    /**
     * Create entry.
     *
     * @psalm-suppress MoreSpecificReturnType
     *
     * @psalm-param    array<string, string|int|float|null> $data
     *
     * @return \Generator<static>
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
     */
    private static function create(QueryExecutor $queryExecutor, array $data, bool $isNew): \Generator
    {
        $metadataExtractor = new MetadataLoader($queryExecutor);

        $self = new static($queryExecutor);

        /**
         * @psalm-var array<string, string> $columns
         *
         * @var array $columns
         */
        $columns = yield $metadataExtractor->columns(static::tableName());

        $self->columns = $columns;

        if ($isNew === false)
        {
            /**
             * @noinspection CallableParameterUseCaseInTypeContextInspection
             *
             * @psalm-var    array<string, string|int|float|null> $data
             *
             * @var array $data
             */
            $data = unescapeBinary($queryExecutor, $data);
        }

        foreach ($data as $key => $value)
        {
            $self->{$key} = $value;
        }

        $self->isNew = $isNew;

        return $self;
    }

    /**
     * @param QueryExecutor $queryExecutor
     */
    private function __construct(QueryExecutor $queryExecutor)
    {
        $this->queryExecutor = $queryExecutor;
    }
}
