<?php

/** @noinspection PhpUnhandledExceptionInspection */

/**
 * Active record implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace ServiceBus\Storage\ActiveRecord\Tests;

use Amp\Loop;
use Amp\Promise;
use PHPUnit\Framework\TestCase;
use ServiceBus\Cache\InMemory\InMemoryStorage;
use ServiceBus\Storage\ActiveRecord\Tests\Stubs\SecondTestTable;
use ServiceBus\Storage\ActiveRecord\Tests\Stubs\TestTable;
use ServiceBus\Storage\Sql\AmpPosgreSQL\AmpPostgreSQLAdapter;
use function Amp\Promise\wait;
use function ServiceBus\Common\uuid;
use function ServiceBus\Storage\Sql\DoctrineDBAL\inMemoryAdapter;

final class TableTest extends TestCase
{
    /**
     * @var AmpPostgreSQLAdapter
     */
    private $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        InMemoryStorage::instance()->reset();

        $this->adapter = inMemoryAdapter();

        $promise = $this->adapter->execute(
            <<<EOT
CREATE TABLE IF NOT EXISTS test_table 
(
    id uuid PRIMARY KEY,
    first_value varchar NOT NULL,
    second_value varchar NOT NULL
)
EOT
        );

        wait($promise);

        $promise = $this->adapter->execute(
            <<<EOT
        CREATE TABLE IF NOT EXISTS second_test_table
(
	pk serial constraint second_test_table_pk PRIMARY KEY,
	title bytea NOT NULL
);
EOT
        );

        wait($promise);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        wait($this->adapter->execute('DROP TABLE test_table'));
        wait($this->adapter->execute('DROP TABLE second_test_table'));
        unset($this->adapter);

        InMemoryStorage::instance()->reset();
    }

    /**
     * @test
     */
    public function findNonExistent(): void
    {
        $testTable = wait(TestTable::find($this->adapter, uuid()));

        self::assertNull($testTable);
    }

    /**
     * @test
     */
    public function storeNew(): void
    {
        Loop::run(
            function (): \Generator
            {
                $expectedId = uuid();

                /** @var TestTable $testTable */
                $testTable = yield TestTable::new(
                    $this->adapter,
                    ['id' => $expectedId, 'first_value' => 'first', 'second_value' => 'second']
                );

                $id = $testTable->lastInsertId();

                self::assertSame($expectedId, $id);

                /** @var TestTable $testTable */
                $testTable = yield TestTable::find($this->adapter, $id);

                self::assertNotNull($testTable);
                self::assertSame('first', $testTable->first_value);
                self::assertSame('second', $testTable->second_value);

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function updateStored(): void
    {
        Loop::run(
            function (): \Generator
            {
                $id = uuid();

                /** @var TestTable $testTable */
                $testTable = yield TestTable::new(
                    $this->adapter,
                    ['id' => $id, 'first_value' => 'first', 'second_value' => 'second']
                );

                yield $testTable->save();

                $testTable->first_value = 'qwerty';

                yield $testTable->save();

                unset($testTable);

                $testTable = yield TestTable::find($this->adapter, $id);

                self::assertSame('qwerty', $testTable->first_value);

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function deleteUnStored(): void
    {
        Loop::run(
            function (): \Generator
            {
                /** @var TestTable $testTable */
                $testTable = yield TestTable::new(
                    $this->adapter,
                    ['id' => uuid(), 'first_value' => 'first', 'second_value' => 'second']
                );

                yield $testTable->remove();

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function updateWithNoChanges(): void
    {
        Loop::run(
            function (): \Generator
            {
                $id = uuid();

                /** @var TestTable $testTable */
                $testTable = yield TestTable::new(
                    $this->adapter,
                    ['id' => $id, 'first_value' => 'first', 'second_value' => 'second']
                );

                yield $testTable->save();

                self::assertSame($id, $testTable->lastInsertId());
                self::assertSame(0, yield $testTable->save());

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function findCollection(): void
    {
        Loop::run(
            function (): \Generator
            {
                $collection = [
                    TestTable::new($this->adapter, ['id' => uuid(), 'first_value' => '1', 'second_value' => '7']),
                    TestTable::new($this->adapter, ['id' => uuid(), 'first_value' => '2', 'second_value' => '6']),
                    TestTable::new($this->adapter, ['id' => uuid(), 'first_value' => '3', 'second_value' => '5']),
                    TestTable::new($this->adapter, ['id' => uuid(), 'first_value' => '4', 'second_value' => '4']),
                    TestTable::new($this->adapter, ['id' => uuid(), 'first_value' => '5', 'second_value' => '3']),
                    TestTable::new($this->adapter, ['id' => uuid(), 'first_value' => '6', 'second_value' => '2']),
                    TestTable::new($this->adapter, ['id' => uuid(), 'first_value' => '7', 'second_value' => '1']),
                ];

                /** @var Promise $promise */
                foreach ($collection as $promise)
                {
                    /** @var TestTable $entity */
                    $entity = yield $promise;

                    yield $entity->save();
                }

                /** @var TestTable[] $result */
                $result = yield TestTable::findBy($this->adapter, [], 3);

                self::assertCount(3, $result);

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function successRemove(): void
    {
        Loop::run(
            function (): \Generator
            {
                /** @var TestTable $testTable */
                $testTable = yield TestTable::new($this->adapter, ['id' => uuid(), 'first_value' => 'first', 'second_value' => 'second']);

                yield $testTable->save();
                yield $testTable->remove();

                /** @var TestTable[] $result */
                $result = yield TestTable::findBy($this->adapter);

                self::assertCount(0, $result);

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function saveWithNoPrimaryKey(): void
    {
        Loop::run(
            function (): \Generator
            {
                /** @var TestTable $testTable */
                $testTable = yield TestTable::new($this->adapter, ['first_value' => 'first', 'second_value' => 'second']);

                yield $testTable->save();

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function unExistsProperty(): void
    {
        Loop::run(
            function (): \Generator
            {
                $this->expectException(\LogicException::class);
                $this->expectExceptionMessage('Column "qqqq" does not exist in table "test_table"');

                yield TestTable::new($this->adapter, ['qqqq' => '111']);

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function saveWithSerialPrimaryKey(): void
    {
        Loop::run(
            function (): \Generator
            {
                /** @var SecondTestTable $table */
                $table = yield SecondTestTable::new($this->adapter, ['title' => 'root']);

                unset($table);

                /** @var SecondTestTable[] $tables */
                $tables = yield SecondTestTable::findBy($this->adapter);

                self::assertCount(1, $tables);

                $table = \reset($tables);

                self::assertSame('root', $table->title);

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function refresh(): void
    {
        Loop::run(
            function (): \Generator
            {
                /** @var SecondTestTable $table */
                $table = yield SecondTestTable::new($this->adapter, ['title' => 'root']);

                self::assertTrue(isset($table->pk));

                $table->title = 'qwerty';

                yield $table->refresh();

                self::assertSame('root', $table->title);

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function selectWithOrder(): void
    {
        Loop::run(
            function (): \Generator
            {
                yield TestTable::new($this->adapter, ['id' => uuid(), 'first_value' => '1', 'second_value' => '3']);
                yield TestTable::new($this->adapter, ['id' => uuid(), 'first_value' => '2', 'second_value' => '2']);
                yield TestTable::new($this->adapter, ['id' => uuid(), 'first_value' => '3', 'second_value' => '1']);

                /** @var TestTable[] $collection */
                $collection = yield TestTable::findBy(
                    queryExecutor: $this->adapter,
                    criteria: [],
                    limit: 50,
                    orderBy: ['first_value' => 'desc']
                );

                self::assertCount(3, $collection);

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function refreshWithDeletedEntry(): void
    {
        Loop::run(
            function (): \Generator
            {
                $this->expectException(\RuntimeException::class);
                $this->expectExceptionMessage('Failed to update entity: data has been deleted');

                /** @var TestTable $table */
                $table = yield TestTable::new(
                    $this->adapter,
                    ['id' => uuid(), 'first_value' => '1', 'second_value' => '3']
                );

                yield $table->remove();
                yield $table->refresh();

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function updateWithoutPrimaryKey(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            'In the parameters of the entity must be specified element with the index "id" (primary key)'
        )
        ;

        Loop::run(
            function (): \Generator
            {
                /** @var TestTable $table */
                $table = yield TestTable::new($this->adapter, ['id' => uuid(), 'first_value' => '1', 'second_value' => '3']);

                $table->id = null;

                yield $table->save();

                Loop::stop();
            }
        );
    }
}
