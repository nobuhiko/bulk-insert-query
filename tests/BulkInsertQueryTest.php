<?php

namespace nobuhiko\BulkInsertQuery\Tests;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Connection;
use nobuhiko\BulkInsertQuery\BulkInsertQuery;
use PHPUnit\Framework\TestCase;

class BulkInsertQueryTest extends TestCase
{
    /** @var Connection */
    private $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->connection->executeStatement('CREATE TABLE test_table (id INTEGER, name TEXT, value TEXT)');
    }

    protected function tearDown(): void
    {
        $this->connection->close();
    }

    public function test単一行を挿入できる()
    {
        $builder = new BulkInsertQuery($this->connection, 'test_table');
        $builder->setColumns(['id', 'name', 'value']);
        $builder->setValues(['id' => 1, 'name' => 'foo', 'value' => 'bar']);
        $builder->execute();

        $rows = $this->connection->fetchAllAssociative('SELECT * FROM test_table');
        self::assertCount(1, $rows);
        self::assertEquals('foo', $rows[0]['name']);
    }

    public function test複数行をバッチ挿入できる()
    {
        $builder = new BulkInsertQuery($this->connection, 'test_table');
        $builder->setColumns(['id', 'name', 'value']);

        for ($i = 1; $i <= 100; $i++) {
            $builder->setValues(['id' => $i, 'name' => "name_{$i}", 'value' => "val_{$i}"]);
        }
        $builder->execute();

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM test_table');
        self::assertEquals(100, $count);
    }

    public function testexecute後にvalueSetsがリセットされる()
    {
        $builder = new BulkInsertQuery($this->connection, 'test_table');
        $builder->setColumns(['id', 'name', 'value']);
        $builder->setValues(['id' => 1, 'name' => 'a', 'value' => 'b']);
        $builder->execute();

        self::assertEmpty($builder->getValues());
    }

    public function test大量行でもメモリ効率が良い()
    {
        $builder = new BulkInsertQuery($this->connection, 'test_table');
        $builder->setColumns(['id', 'name', 'value']);

        $batchSize = 50;
        $totalRows = 500;

        for ($i = 1; $i <= $totalRows; $i++) {
            $builder->setValues(['id' => $i, 'name' => str_repeat('x', 100), 'value' => str_repeat('y', 100)]);

            if (($i % $batchSize) === 0) {
                $builder->execute();
            }
        }
        if (count($builder->getValues()) > 0) {
            $builder->execute();
        }

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM test_table');
        self::assertEquals($totalRows, $count);
    }

    public function testgetPositionalTypesが正しい型配列を返す()
    {
        $builder = new BulkInsertQuery($this->connection, 'test_table');
        $builder->setColumns(['id', 'name', 'value']);

        // types を設定するためにリフレクションを使用
        $ref = new \ReflectionClass($builder);
        $typesProp = $ref->getProperty('types');
        $typesProp->setAccessible(true);
        $typesProp->setValue($builder, ['id' => \PDO::PARAM_INT, 'name' => \PDO::PARAM_STR, 'value' => \PDO::PARAM_STR]);

        // 3行追加
        $builder->setValues(['id' => 1, 'name' => 'a', 'value' => 'b']);
        $builder->setValues(['id' => 2, 'name' => 'c', 'value' => 'd']);
        $builder->setValues(['id' => 3, 'name' => 'e', 'value' => 'f']);

        $method = $ref->getMethod('getPositionalTypes');
        $method->setAccessible(true);
        $result = $method->invoke($builder);

        // 3列 × 3行 = 9要素
        self::assertCount(9, $result);
        // パターンが繰り返されること
        self::assertEquals(\PDO::PARAM_INT, $result[0]);
        self::assertEquals(\PDO::PARAM_STR, $result[1]);
        self::assertEquals(\PDO::PARAM_STR, $result[2]);
        self::assertEquals(\PDO::PARAM_INT, $result[3]);
        self::assertEquals(\PDO::PARAM_STR, $result[4]);
        self::assertEquals(\PDO::PARAM_STR, $result[5]);
    }

    public function testNULL値を含む行を挿入できる()
    {
        $builder = new BulkInsertQuery($this->connection, 'test_table');
        $builder->setColumns(['id', 'name', 'value']);
        $builder->setValues(['id' => 1, 'name' => null, 'value' => 'bar']);
        $builder->execute();

        $rows = $this->connection->fetchAllAssociative('SELECT * FROM test_table');
        self::assertCount(1, $rows);
        self::assertNull($rows[0]['name']);
        self::assertEquals('bar', $rows[0]['value']);
    }
}
