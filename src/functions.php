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

use Latitude\QueryBuilder\Query as LatitudeQuery;
use Ramsey\Uuid\Uuid;
use ServiceBus\Storage\Common\BinaryDataDecoder;
use ServiceBus\Storage\Common\QueryExecutor;
use function ServiceBus\Storage\Sql\deleteQuery;
use function ServiceBus\Storage\Sql\selectQuery;

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
 * @return \Generator<\ServiceBus\Storage\Common\ResultSet>
 *
 * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
 * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
 * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
 * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
 */
function find(QueryExecutor $queryExecutor, string $tableName, array $criteria = [], ?int $limit = null, array $orderBy = []): \Generator
{
    /**
     * @var string                               $query
     * @var array<string, string|int|float|null> $parameters
     */
    [$query, $parameters] = buildQuery(selectQuery($tableName), $criteria, $orderBy, $limit);

    /** @psalm-suppress TooManyTemplateParams Wrong Promise template */
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
 * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
 * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
 * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
 * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
 * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed
 */
function remove(QueryExecutor $queryExecutor, string $tableName, array $criteria = []): \Generator
{
    /**
     * @var string                               $query
     * @var array<string, string|int|float|null> $parameters
     */
    [$query, $parameters] = buildQuery(deleteQuery($tableName), $criteria);

    /**
     * @psalm-suppress TooManyTemplateParams Wrong Promise template
     * @var \ServiceBus\Storage\Common\ResultSet $resultSet
     */
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
