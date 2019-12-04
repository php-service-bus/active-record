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
use function ServiceBus\Storage\Sql\equalsCriteria;
use function ServiceBus\Storage\Sql\fetchAll;
use function ServiceBus\Storage\Sql\selectQuery;
use Amp\Promise;
use ServiceBus\Cache\CacheAdapter;
use ServiceBus\Cache\InMemory\InMemoryCacheAdapter;
use ServiceBus\Storage\Common\QueryExecutor;

/**
 * @internal
 */
final class MetadataLoader
{
    /** @var QueryExecutor */
    private $queryExecutor;

    /** @var CacheAdapter */
    private $cacheAdapter;

    public function __construct(QueryExecutor $queryExecutor, ?CacheAdapter $cacheAdapter = null)
    {
        $this->queryExecutor = $queryExecutor;
        $this->cacheAdapter  = $cacheAdapter ?? new InMemoryCacheAdapter();
    }

    /**
     * Load table columns.
     *
     * [
     *    'id' => 'uuid'',
     *    ...
     * ]
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
     */
    public function columns(string $table): Promise
    {
        return call(
            function (string $table): \Generator
            {
                $cacheKey = \sha1($table . '_metadata_columns');

                /** @var array|null $columns */
                $columns = yield $this->cacheAdapter->get($cacheKey);

                if ($columns !== null)
                {
                    return $columns;
                }

                /**
                 * @psalm-var      array<string, string>|null $columns
                 *
                 * @var array|null $columns
                 */
                $columns = yield from $this->loadColumns($table);

                yield $this->cacheAdapter->save($cacheKey, $columns);

                return $columns;
            },
            $table
        );
    }

    /**
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
     * @throws \ServiceBus\Storage\Common\Exceptions\IncorrectParameterCast
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     */
    private function loadColumns(string $table): \Generator
    {
        /** @psalm-var array<string, string> $result */
        $result = [];

        $queryBuilder = selectQuery('information_schema.columns', 'column_name', 'data_type')
            ->where(equalsCriteria('table_name', $table));

        $compiledQuery = $queryBuilder->compile();

        /**
         * @psalm-suppress MixedTypeCoercion Invalid params() docblock
         *
         * @var \ServiceBus\Storage\Common\ResultSet $resultSet
         */
        $resultSet = yield $this->queryExecutor->execute($compiledQuery->sql(), $compiledQuery->params());

        /**
         * @psalm-var      array<int, array<string, string>> $columns
         *
         * @var array $columns
         */
        $columns = yield fetchAll($resultSet);

        /** @psalm-var array{column_name:string, data_type:string} $columnData */
        foreach ($columns as $columnData)
        {
            $result[$columnData['column_name']] = $columnData['data_type'];
        }

        return $result;
    }
}
