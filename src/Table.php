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
    private $insertId;

    /**
     * @var QueryExecutor
     */
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
     *
     * @return string
     */
    abstract protected static function tableName(): string;

    /**
     * Receive primary key name.
     *
     * @return string
     */
    protected static function primaryKey(): string
    {
        return 'id';
    }

    /**
     * Create and persist entry.
     *
     * @noinspection PhpDocRedundantThrowsInspection
     * @psalm-return \Amp\Promise
     *
     * @param QueryExecutor                        $queryExecutor
     * @param array<string, float|int|string|null> $data
     *
     * @throws \ServiceBus\Storage\ActiveRecord\Exceptions\UnknownColumn
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
     *
     * @return Promise<\ServiceBus\Storage\ActiveRecord\Table>
     */
    final public static function new(QueryExecutor $queryExecutor, array $data): Promise
    {
        /** @psalm-suppress InvalidArgument */
        return call(
            static function(array $data) use ($queryExecutor): \Generator
            {
                /**
                 * @psalm-var array<string, string|int|float|null> $data
                 *
                 * @var static $self
                 */
                $self = yield from static::create($queryExecutor, $data, true);

                /** @var int|string $result */
                $result = yield $self->save();

                $self->insertId = (string) $result;

                return $self;
            },
            $data
        );
    }

    /**
     * Find entry by primary key.
     *
     * @psalm-return \Amp\Promise
     *
     * @param QueryExecutor $queryExecutor
     * @param int|string    $id
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
     * @throws \ServiceBus\Storage\Common\Exceptions\IncorrectParameterCast
     * @throws \ServiceBus\Storage\Common\Exceptions\OneResultExpected
     * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
     *
     * @return Promise<static|null>
     */
    final public static function find(QueryExecutor $queryExecutor, $id): Promise
    {
        return self::findOneBy($queryExecutor, [equalsCriteria(static::primaryKey(), $id)]);
    }

    /**
     * Find one entry by specified criteria.
     *
     * @noinspection PhpDocRedundantThrowsInspection
     * @psalm-return \Amp\Promise
     *
     * @param QueryExecutor                                          $queryExecutor
     * @param array<mixed, \Latitude\QueryBuilder\CriteriaInterface> $criteria
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
     * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed Error getting operation  result
     * @throws \ServiceBus\Storage\Common\Exceptions\OneResultExpected The result must contain only 1 row
     *
     * @return Promise<static|null>
     */
    final public static function findOneBy(QueryExecutor $queryExecutor, array $criteria): Promise
    {
        /** @psalm-suppress InvalidArgument */
        return call(
            static function(QueryExecutor $queryExecutor, array $criteria): \Generator
            {
                /**
                 * @psalm-var array<mixed, \Latitude\QueryBuilder\CriteriaInterface> $criteria
                 *
                 * @var \Latitude\QueryBuilder\CriteriaInterface[] $criteria
                 * @var \ServiceBus\Storage\Common\ResultSet       $resultSet
                 */
                $resultSet = yield find($queryExecutor, static::tableName(), $criteria);

                /**
                 * @psalm-var array<string, string|int|float|null>|null $data
                 *
                 * @var array $data
                 */
                $data = yield fetchOne($resultSet);

                unset($resultSet);

                if (true === \is_array($data))
                {
                    return yield from self::create($queryExecutor, $data, false);
                }
            },
            $queryExecutor,
            $criteria
        );
    }

    /**
     * Find entries by specified criteria.
     *
     * @noinspection PhpDocRedundantThrowsInspection
     *
     * @psalm-param  array<mixed, \Latitude\QueryBuilder\CriteriaInterface> $criteria
     * @psalm-param  array<string, string>                      $orderBy
     * @psalm-return \Amp\Promise
     *
     * @param QueryExecutor                              $queryExecutor
     * @param \Latitude\QueryBuilder\CriteriaInterface[] $criteria
     * @param int|null                                   $limit
     * @param array                                      $orderBy
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
     * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed Error getting operation  result
     *
     * @return Promise<array<int, static>>
     */
    final public static function findBy(
        QueryExecutor $queryExecutor,
        array $criteria = [],
        ?int $limit = null,
        array $orderBy = []
    ): Promise {
        /** @psalm-suppress InvalidArgument */
        return call(
            static function(QueryExecutor $queryExecutor, array $criteria, ?int $limit, array $orderBy): \Generator
            {
                /**
                 * @psalm-var array<mixed, \Latitude\QueryBuilder\CriteriaInterface> $criteria
                 *
                 * @var \Latitude\QueryBuilder\CriteriaInterface[] $criteria
                 * @var \ServiceBus\Storage\Common\ResultSet       $resultSet
                 * @var array<string, string>                      $orderBy
                 */
                $resultSet = yield find($queryExecutor, static::tableName(), $criteria, $limit, $orderBy);

                /** @var array<string, float|int|string|null>|null $rows */
                $rows = yield fetchAll($resultSet);

                unset($resultSet);

                /** @var array<int, static> $result */
                $result = [];

                if (null !== $rows)
                {
                    /** @psalm-var array<string, string|int|float|null> $row */
                    foreach ($rows as $row)
                    {
                        /** @var static $entry */
                        $entry    = yield from self::create($queryExecutor, $row, false);
                        $result[] = $entry;

                        unset($entry);
                    }
                }

                return $result;
            },
            $queryExecutor,
            $criteria,
            $limit,
            $orderBy
        );
    }

    /**
     * Save entry changes.
     *
     * @noinspection PhpDocRedundantThrowsInspection
     * @psalm-return \Amp\Promise
     *
     * @throws \ServiceBus\Storage\ActiveRecord\Exceptions\PrimaryKeyNotSpecified
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed Duplicate entry
     *
     * @return Promise<int|string> Returns the ID of the saved entry, or the number of affected rows (in the case of an
     *                             update)
     */
    final public function save(): Promise
    {
        /** @psalm-suppress InvalidArgument */
        return call(
            function(bool $isNew): \Generator
            {
                /** Store new entry */
                if (true === $isNew)
                {
                    $this->changes = [];

                    /** @var int|string $lastInsertId */
                    $lastInsertId = yield from $this->storeNewEntry($this->data);
                    $this->isNew  = false;

                    return $lastInsertId;
                }

                $changeSet = $this->changes;

                if (0 === \count($changeSet))
                {
                    return 0;
                }

                /** @var int $affectedRows */
                $affectedRows  = yield from $this->updateExistsEntry($changeSet);
                $this->changes = [];

                return $affectedRows;
            },
            $this->isNew
        );
    }

    /**
     * Refresh entry data.
     *
     * @noinspection PhpDocRedundantThrowsInspection
     * @psalm-return \Amp\Promise
     *
     * @throws \ServiceBus\Storage\ActiveRecord\Exceptions\UpdateRemovedEntry Unable to find an entry (possibly RC
     *                                                                        occured)
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
     *
     * @return Promise
     */
    public function refresh(): Promise
    {
        /**
         * @psalm-suppress MixedTypeCoercion
         * @psalm-suppress InvalidArgument
         */
        return call(
            function(): \Generator
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

                if (true === \is_array($row))
                {
                    $this->changes = [];

                    /** @psalm-suppress PossiblyInvalidPropertyAssignmentValue */
                    $this->data = unescapeBinary($this->queryExecutor, $row);

                    return;
                }

                throw new UpdateRemovedEntry('Failed to update entity: data has been deleted');
            }
        );
    }

    /**
     * Delete entry.
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\IncorrectParameterCast
     * @throws \ServiceBus\Storage\ActiveRecord\Exceptions\PrimaryKeyNotSpecified Unable to find primary key value
     *
     * @return Promise Does not return result
     */
    final public function remove(): Promise
    {
        if (true === $this->isNew)
        {
            return new Success();
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
     * @return string|null
     */
    final public function lastInsertId(): ?string
    {
        return $this->insertId;
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, mixed>
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
     * @param string                $name
     * @param float|int|string|null $value
     *
     * @throws \ServiceBus\Storage\ActiveRecord\Exceptions\UnknownColumn
     *
     * @return void
     */
    final public function __set(string $name, $value): void
    {
        if (true === isset($this->columns[$name]))
        {
            $this->data[$name]    = $value;
            $this->changes[$name] = $value;

            return;
        }

        throw new UnknownColumn($name, static::tableName());
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    final public function __isset(string $name): bool
    {
        return isset($this->columns[$name]);
    }

    /**
     * @param string $name
     *
     * @return float|int|string|null
     */
    final public function __get(string $name)
    {
        return $this->data[$name];
    }

    /**
     * Receive query execution handler.
     *
     * @return QueryExecutor
     */
    final protected function queryExecutor(): QueryExecutor
    {
        return $this->queryExecutor;
    }

    /**
     * Store new entry.
     *
     * @psalm-param array<string, string|int|float|null> $changeSet
     * @psalm-return \Generator
     *
     * @param array $changeSet
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\IncorrectParameterCast
     * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed
     *
     * @return \Generator<int|string>
     */
    private function storeNewEntry(array $changeSet): \Generator
    {
        $primaryKey = static::primaryKey();

        if (false === \array_key_exists($primaryKey, $changeSet) && 'uuid' === \strtolower($this->columns[$primaryKey]))
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
         * @psalm-suppress TooManyTemplateParams Wrong Promise template
         * @psalm-suppress MixedTypeCoercion Invalid params() docblock
         *
         * @var \ServiceBus\Storage\Common\ResultSet $resultSet
         */
        $resultSet = yield $this->queryExecutor->execute($compiledQuery->sql(), $compiledQuery->params());

        /**
         * @psalm-suppress TooManyTemplateParams Wrong Promise template
         *
         * @var string $insertedEntryId
         */
        $insertedEntryId = yield $resultSet->lastInsertId();

        unset($queryBuilder, $compiledQuery, $resultSet);

        if (false === isset($this->data[$primaryKey]))
        {
            $this->data[$primaryKey] = $insertedEntryId;
        }

        return $insertedEntryId;
    }

    /**
     * Update exists entry.
     *
     * @psalm-param array<string, string|int|float|null> $changeSet
     * @psalm-return \Generator
     *
     * @param array $changeSet
     *
     * @throws \ServiceBus\Storage\ActiveRecord\Exceptions\PrimaryKeyNotSpecified
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\IncorrectParameterCast
     * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed
     *
     * @return \Generator<int>
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
         * @psalm-suppress TooManyTemplateParams Wrong Promise template
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
     *
     * @return string
     *
     */
    private function searchPrimaryKeyValue(): string
    {
        $primaryKey = static::primaryKey();

        if (true === isset($this->data[$primaryKey]) && '' !== (string) $this->data[$primaryKey])
        {
            return (string) $this->data[$primaryKey];
        }

        throw new PrimaryKeyNotSpecified($primaryKey);
    }

    /**
     * Create entry.
     *
     * @psalm-param array<string, string|int|float|null> $data
     * @psalm-return \Generator
     *
     * @param QueryExecutor $queryExecutor
     * @param array         $data
     * @param bool          $isNew
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
     *
     * @return \Generator<static>
     */
    private static function create(QueryExecutor $queryExecutor, array $data, bool $isNew): \Generator
    {
        $metadataExtractor = new MetadataLoader($queryExecutor);

        $self = new static($queryExecutor);

        /** @psalm-var array<string, string> $columns */
        $columns = yield $metadataExtractor->columns(static::tableName());

        $self->columns = $columns;

        if (false === $isNew)
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
