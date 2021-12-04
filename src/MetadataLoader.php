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
use ServiceBus\Cache\CacheAdapter;
use ServiceBus\Cache\InMemory\InMemoryCacheAdapter;
use ServiceBus\Storage\Common\QueryExecutor;
use function Amp\call;
use function ServiceBus\Storage\Sql\equalsCriteria;
use function ServiceBus\Storage\Sql\fetchAll;
use function ServiceBus\Storage\Sql\selectQuery;

/**
 * @internal
 */
final class MetadataLoader
{
    /**
     * @var QueryExecutor
     */
    private $queryExecutor;

    /**
     * @var CacheAdapter
     */
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
     * @psalm-param non-empty-string $table
     *
     * @psalm-return Promise<array<non-empty-string, non-empty-string>>
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
     */
    public function columns(string $table): Promise
    {
        return call(
            function () use ($table): \Generator
            {
                $cacheKey = \sha1($table . '_metadata_columns');

                /**
                 * @psalm-var array<non-empty-string, non-empty-string>|null $columns
                 */
                $columns = yield $this->cacheAdapter->get($cacheKey);

                if ($columns !== null)
                {
                    return $columns;
                }

                $columns = yield $this->loadColumns($table);

                yield $this->cacheAdapter->save($cacheKey, $columns);

                return $columns;
            }
        );
    }

    /**
     * @psalm-param non-empty-string $table
     *
     * @psalm-return Promise<array<non-empty-string, non-empty-string>>
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
     * @throws \ServiceBus\Storage\Common\Exceptions\IncorrectParameterCast
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     */
    private function loadColumns(string $table): Promise
    {
        return call(
            function () use ($table): \Generator
            {
                $result = [];

                $queryBuilder = selectQuery('information_schema.columns', 'column_name', 'data_type')
                    ->where(equalsCriteria('table_name', $table));

                $compiledQuery = $queryBuilder->compile();

                /**
                 * @psalm-suppress MixedArgumentTypeCoercion Invalid params() docblock
                 *
                 * @var \ServiceBus\Storage\Common\ResultSet $resultSet
                 */
                $resultSet = yield $this->queryExecutor->execute($compiledQuery->sql(), $compiledQuery->params());

                /**
                 * @psalm-var array<array-key, array{column_name:non-empty-string, data_type:non-empty-string}> $columns
                 */
                $columns = yield fetchAll($resultSet);

                foreach ($columns as $columnData)
                {
                    $result[$columnData['column_name']] = $columnData['data_type'];
                }

                return $result;
            }
        );
    }
}
