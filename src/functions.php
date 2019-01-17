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

namespace Desperado\ServiceBus\ActiveRecord;

use Desperado\ServiceBus\Storage\BinaryDataDecoder;
use function Desperado\ServiceBus\Storage\deleteQuery;
use Desperado\ServiceBus\Storage\QueryExecutor;
use function Desperado\ServiceBus\Storage\selectQuery;
use Latitude\QueryBuilder\Query as LatitudeQuery;
use Ramsey\Uuid\Uuid;

/**
 * @noinspection PhpDocMissingThrowsInspection
 * @internal
 *
 * Generate a version 4 (random) UUID.
 *
 * @return string
 */
function uuid(): string
{
    /** @noinspection PhpUnhandledExceptionInspection */
    return Uuid::uuid4()->toString();
}

/**
 * @internal
 *
 * @psalm-return \Generator
 *
 * @param QueryExecutor                                          $queryExecutor
 * @param string                                                 $tableName
 * @param array<mixed, \Latitude\QueryBuilder\CriteriaInterface> $criteria
 * @param int|null                                               $limit
 * @param array<string, string>                                  $orderBy
 *
 * @return \Generator<\Desperado\ServiceBus\Storage\ResultSet>
 *
 * @throws \Desperado\ServiceBus\Storage\Exceptions\StorageInteractingFailed Basic type of interaction errors
 * @throws \Desperado\ServiceBus\Storage\Exceptions\ConnectionFailed Could not connect to database
 */
function find(QueryExecutor $queryExecutor, string $tableName, array $criteria = [], ?int $limit = null, array $orderBy = []): \Generator
{
    /**
     * @var string                               $query
     * @var array<string, string|int|float|null> $parameters
     */
    [$query, $parameters] = buildQuery(selectQuery($tableName), $criteria, $orderBy, $limit);

    return yield $queryExecutor->execute($query, $parameters);
}

/**
 * @internal
 *
 * @psalm-return \Generator
 *
 * @param QueryExecutor                                          $queryExecutor
 * @param string                                                 $tableName
 * @param array<mixed, \Latitude\QueryBuilder\CriteriaInterface> $criteria
 *
 * @return \Generator<int>
 *
 * @throws \Desperado\ServiceBus\Storage\Exceptions\StorageInteractingFailed Basic type of interaction errors
 * @throws \Desperado\ServiceBus\Storage\Exceptions\ConnectionFailed Could not connect to database
 */
function remove(QueryExecutor $queryExecutor, string $tableName, array $criteria = []): \Generator
{
    /**
     * @var string                               $query
     * @var array<string, string|int|float|null> $parameters
     */
    [$query, $parameters] = buildQuery(deleteQuery($tableName), $criteria);

    /** @var \Desperado\ServiceBus\Storage\ResultSet $resultSet */
    $resultSet = yield $queryExecutor->execute($query, $parameters);

    $affectedRows = $resultSet->affectedRows();

    unset($resultSet);

    return $affectedRows;
}

/**
 * @internal
 *
 * @param LatitudeQuery\AbstractQuery                            $queryBuilder
 * @param array<mixed, \Latitude\QueryBuilder\CriteriaInterface> $criteria
 * @param array<string, string>                                  $orderBy
 * @param int|null                                               $limit
 *
 * @return array 0 - SQL query; 1 - query parameters
 */
function buildQuery(
    LatitudeQuery\AbstractQuery $queryBuilder,
    array $criteria = [],
    array $orderBy = [],
    ?int $limit = null
): array
{
    /** @var LatitudeQuery\SelectQuery|LatitudeQuery\UpdateQuery|LatitudeQuery\DeleteQuery $queryBuilder */

    $isFirstCondition = true;

    /** @var \Latitude\QueryBuilder\CriteriaInterface $criteriaItem */
    foreach($criteria as $criteriaItem)
    {
        $methodName = true === $isFirstCondition ? 'where' : 'andWhere';
        $queryBuilder->{$methodName}($criteriaItem);
        $isFirstCondition = false;
    }

    if($queryBuilder instanceof LatitudeQuery\SelectQuery)
    {
        foreach($orderBy as $column => $direction)
        {
            $queryBuilder->orderBy($column, $direction);
        }

        if(null !== $limit)
        {
            $queryBuilder->limit($limit);
        }
    }

    $compiledQuery = $queryBuilder->compile();

    return [
        $compiledQuery->sql(),
        $compiledQuery->params()
    ];
}

/**
 * @internal
 *
 * Unescape binary data
 *
 * @param QueryExecutor                        $queryExecutor
 * @param array<string, string|int|null|float> $set
 *
 * @return array<string, string|int|null|float>
 */
function unescapeBinary(QueryExecutor $queryExecutor, array $set): array
{
    if($queryExecutor instanceof BinaryDataDecoder)
    {
        foreach($set as $key => $value)
        {
            if(false === empty($value) && true === \is_string($value))
            {
                $set[$key] = $queryExecutor->unescapeBinary($value);
            }
        }
    }

    return $set;
}
