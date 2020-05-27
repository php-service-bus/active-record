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
     * @return Promise<array<string, string>>
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

                /**
                 * @psalm-var array<string, string>|null $columns
                 *
                 * @var array|null $columns
                 */
                $columns = yield $this->cacheAdapter->get($cacheKey);

                if ($columns !== null)
                {
                    return $columns;
                }

                /**
                 * @psalm-var array<string, string> $columns
                 *
                 * @var array $columns
                 */
                $columns = yield from $this->loadColumns($table);

                yield $this->cacheAdapter->save($cacheKey, $columns);

                return $columns;
            },
            $table
        );
    }

    /**
     * @return \Generator<array<string, string>>
     *
     * @psalm-suppress InvalidReturnType
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
     * @throws \ServiceBus\Storage\Common\Exceptions\IncorrectParameterCast
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     */
    private function loadColumns(string $table): \Generator
    {
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
         * @psalm-var array<array-key, array{column_name:string, data_type:string}> $columns
         *
         * @var array $columns
         */
        $columns = yield fetchAll($resultSet);

        foreach ($columns as $columnData)
        {
            $result[(string) $columnData['column_name']] = (string) $columnData['data_type'];
        }

        return $result;
    }
}
