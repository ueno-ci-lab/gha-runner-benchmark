<?php

namespace Tests;

use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // テストでは DB 接続をモックする方針のため、実際にクエリが発行されたら失敗させる
        DB::beforeExecuting(function (string $query, array $bindings, Connection $connection): void {
            throw new RuntimeException("テスト中に DB へのクエリが発行されました: {$query}");
        });
    }
}
