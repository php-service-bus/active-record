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

use function ServiceBus\Storage\Sql\deleteQuery;
use function ServiceBus\Storage\Sql\selectQuery;
use Latitude\QueryBuilder\Query as LatitudeQuery;
use Ramsey\Uuid\Uuid;
use ServiceBus\Storage\Common\BinaryDataDecoder;
use ServiceBus\Storage\Common\QueryExecutor;

/**
 * @noinspection PhpDocMissingThrowsInspection
 *
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
 * @psalm-param array<mixed, \Latitude\QueryBuilder\CriteriaInterface> $criteria
 * @psalm-param array<string, string>                                  $orderBy
 *
 * @psalm-return \Generator
 *
 * @param QueryExecutor                              $queryExecutor
 * @param string                                     $tableName
 * @param \Latitude\QueryBuilder\CriteriaInterface[] $criteria
 * @param int|null                                   $limit
 * @param array                                      $orderBy
 *
 * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
 * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
 * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
 * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
 *
 * @return \Generator<\ServiceBus\Storage\Common\ResultSet>
 */
function find(QueryExecutor $queryExecutor, string $tableName, array $criteria = [], ?int $limit = null, array $orderBy = []): \Generator
{
    /**
     * @var string $query
     * @var array  $parameters
     * @psalm-var array<string, string|int|float|null> $parameters
     */
    [$query, $parameters] = buildQuery(selectQuery($tableName), $criteria, $orderBy, $limit);

    /**
     * @psalm-suppress TooManyTemplateParams Wrong Promise template
     * @psalm-suppress MixedTypeCoercion Invalid params() docblock
     */
    return yield $queryExecutor->execute($query, $parameters);
}

/**
 * @internal
 *
 * @psalm-param array<mixed, \Latitude\QueryBuilder\CriteriaInterface> $criteria
 *
 * @psalm-return \Generator
 *
 * @param QueryExecutor                              $queryExecutor
 * @param string                                     $tableName
 * @param \Latitude\QueryBuilder\CriteriaInterface[] $criteria
 *
 * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
 * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
 * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
 * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
 * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed
 *
 * @return \Generator<int>
 */
function remove(QueryExecutor $queryExecutor, string $tableName, array $criteria = []): \Generator
{
    /**
     * @var string $query
     * @var array  $parameters
     * @psalm-var array<string, string|int|float|null> $parameters
     */
    [$query, $parameters] = buildQuery(deleteQuery($tableName), $criteria);

    /**
     * @psalm-suppress TooManyTemplateParams Wrong Promise template
     * @psalm-suppress MixedTypeCoercion Invalid params() docblock
     *
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
 * @psalm-param array<mixed, \Latitude\QueryBuilder\CriteriaInterface> $criteria
 * @psalm-param array<string, string>                                  $orderBy
 *
 * @param LatitudeQuery\AbstractQuery                $queryBuilder
 * @param \Latitude\QueryBuilder\CriteriaInterface[] $criteria
 * @param array                                      $orderBy
 * @param int|null                                   $limit
 *
 * @return array 0 - SQL query; 1 - query parameters
 */
function buildQuery(
    LatitudeQuery\AbstractQuery $queryBuilder,
    array $criteria = [],
    array $orderBy = [],
    ?int $limit = null
): array {
    /** @var LatitudeQuery\DeleteQuery|LatitudeQuery\SelectQuery|LatitudeQuery\UpdateQuery $queryBuilder */
    $isFirstCondition = true;

    /** @var \Latitude\QueryBuilder\CriteriaInterface $criteriaItem */
    foreach ($criteria as $criteriaItem)
    {
        $methodName = true === $isFirstCondition ? 'where' : 'andWhere';
        $queryBuilder->{$methodName}($criteriaItem);
        $isFirstCondition = false;
    }

    if ($queryBuilder instanceof LatitudeQuery\SelectQuery)
    {
        foreach ($orderBy as $column => $direction)
        {
            $queryBuilder->orderBy($column, $direction);
        }

        if (null !== $limit)
        {
            $queryBuilder->limit($limit);
        }
    }

    $compiledQuery = $queryBuilder->compile();

    return [
        $compiledQuery->sql(),
        $compiledQuery->params(),
    ];
}

/**
 * @internal
 *
 * @psalm-param  array<string, string|int|null|float> $set
 * @psalm-return array<string, string|int|null|float>
 *
 * Unescape binary data
 *
 * @param QueryExecutor $queryExecutor
 * @param array         $set
 *
 * @return array
 */
function unescapeBinary(QueryExecutor $queryExecutor, array $set): array
{
    if ($queryExecutor instanceof BinaryDataDecoder)
    {
        foreach ($set as $key => $value)
        {
            if (false === empty($value) && true === \is_string($value))
            {
                $set[$key] = $queryExecutor->unescapeBinary($value);
            }
        }
    }

    return $set;
}
