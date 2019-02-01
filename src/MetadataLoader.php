<?php

/**
 * Active record implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Storage\ActiveRecord;

use function Amp\call;
use Amp\Promise;
use ServiceBus\Cache\CacheAdapter;
use ServiceBus\Cache\InMemory\InMemoryCacheAdapter;
use ServiceBus\Storage\Common\QueryExecutor;
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

    /**
     * @param QueryExecutor     $queryExecutor
     * @param CacheAdapter|null $cacheAdapter
     */
    public function __construct(QueryExecutor $queryExecutor, ?CacheAdapter $cacheAdapter = null)
    {
        $this->queryExecutor = $queryExecutor;
        $this->cacheAdapter  = $cacheAdapter ?? new InMemoryCacheAdapter();
    }

    /**
     * Load table columns
     *
     * [
     *    'id' => 'uuid'',
     *    ...
     * ]
     *
     * @noinspection PhpDocRedundantThrowsInspection
     * @psalm-return \Amp\Promise
     *
     * @param string $table
     *
     * @return Promise<array<string, string>>
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
     */
    public function columns(string $table): Promise
    {
        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            function(string $table): \Generator
            {
                $cacheKey = \sha1($table . '_metadata_columns');

                /**
                 * @psalm-suppress TooManyTemplateParams Wrong Promise template
                 * @var array|null $columns
                 */
                $columns = yield $this->cacheAdapter->get($cacheKey);

                if(null !== $columns)
                {
                    return $columns;
                }

                /**
                 * @psalm-suppress TooManyTemplateParams Wrong Promise template
                 * @var array<string, string>|null $columns
                 */
                $columns = yield from $this->loadColumns($table);

                /** @psalm-suppress TooManyTemplateParams Wrong Promise template */
                yield $this->cacheAdapter->save($cacheKey, $columns);

                return $columns;
            },
            $table
        );
    }

    /**
     * @psalm-return \Generator
     *
     * @param string $table
     *
     * @return \Generator<array<string, string>>
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
        /** @var array<string, string> $result */
        $result = [];

        $queryBuilder = selectQuery('information_schema.columns', 'column_name', 'data_type')
            ->where(equalsCriteria('table_name', $table));

        $compiledQuery = $queryBuilder->compile();

        /**
         * @psalm-suppress TooManyTemplateParams Wrong Promise template
         * @var \ServiceBus\Storage\Common\ResultSet $resultSet
         */
        $resultSet = yield $this->queryExecutor->execute($compiledQuery->sql(), $compiledQuery->params());

        /**
         * @psalm-suppress TooManyTemplateParams Wrong Promise template
         * @var array<int, array<string, string>> $columns
         */
        $columns = yield fetchAll($resultSet);

        /** @var array{column_name:string, data_type:string} $columnData */
        foreach($columns as $columnData)
        {
            $result[$columnData['column_name']] = $columnData['data_type'];
        }

        return $result;
    }
}
