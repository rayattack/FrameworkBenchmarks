<?php
declare(strict_types=1);

use Swoole\Database\PDOConfig;
use Swoole\Database\PDOPool;
use Swoole\Database\PDOProxy;
use Swoole\Database\PDOStatementProxy;

class Operation
{
    public const WORLD_SELECT_SQL = 'SELECT id,randomNumber FROM World WHERE id = ?';
    public const FORTUNE_SQL = 'SELECT id, message FROM Fortune';
    public const WORLD_UPDATE_SQL = 'UPDATE World SET randomNumber = ? WHERE id = ?';

    public static function db(PDOStatement|PDOStatementProxy $db): string
    {
        $db->execute([mt_rand(1, 10000)]);
        return json_encode($db->fetch(PDO::FETCH_ASSOC), JSON_NUMERIC_CHECK);
    }

    public static function fortunes(PDOStatement|PDOStatementProxy $fortune): string
    {
        $fortune->execute();
        $results = $fortune->fetchAll(PDO::FETCH_KEY_PAIR);
        $results[0] = 'Additional fortune added at request time.';
        asort($results);

        $html = '';
        foreach ($results as $id => $message) {
            $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
            $html .= "<tr><td>$id</td><td>$message</td></tr>";
        }

        return "<!DOCTYPE html><html><head><title>Fortunes</title></head><body><table><tr><th>id</th><th>message</th></tr>$html</table></body></html>";
    }

    public static function query(PDOStatement|PDOStatementProxy $query, int $queries): string
    {
        $query_count = 1;
        if ($queries > 1) {
            $query_count = min($queries, 500);
        }

        $results = [];
        while ($query_count--) {
            $query->execute([mt_rand(1, 10000)]);
            $results[] = $query->fetch(PDO::FETCH_ASSOC);
        }

        return json_encode($results, JSON_NUMERIC_CHECK);
    }

    public static function updates(PDOStatement|PDOStatementProxy $random, PDOStatement|PDOStatementProxy $update, int $queries): string
    {
        $query_count = 1;
        if ($queries > 1) {
            $query_count = min($queries, 500);
        }

        $results = [];
        while ($query_count--) {
            $id = mt_rand(1, 10000);
            $random->execute([$id]);
            $item = $random->fetch(PDO::FETCH_ASSOC);
            $update->execute([$item['randomNumber'] = mt_rand(1, 10000), $id]);

            $results[] = $item;
        }

        return json_encode($results, JSON_NUMERIC_CHECK);
    }
}

class Connection
{
    private static PDOStatement $db;
    private static PDOStatement $fortune;
    private static PDOStatement $random;
    private static PDOStatement $update;
    private static PDOStatement $query;

    public static function init(string $driver): void
    {
        $pdo = new PDO(
            "$driver:host=tfb-database;dbname=hello_world",
            "benchmarkdbuser",
            "benchmarkdbpass",
            [
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );

        self::$db = self::$random = self::$query = $pdo->prepare(Operation::WORLD_SELECT_SQL);
        self::$fortune = $pdo->prepare(Operation::FORTUNE_SQL);
        self::$update = $pdo->prepare(Operation::WORLD_UPDATE_SQL);

    }

    public static function db(): string
    {
        return Operation::db(self::$db);
    }

    public static function fortunes(): string
    {
        return Operation::fortunes(self::$fortune);
    }

    public static function query(int $queries): string
    {
        return Operation::query(self::$query, $queries);
    }

    public static function updates(int $queries): string
    {
        return Operation::updates(self::$random, self::$update, $queries);
    }
}

class Connections
{
    private static PDOPool $pool;

    public static function init(string $driver): void
    {
        $config = (new PDOConfig())
            ->withDriver($driver)
            ->withHost('tfb-database')
            ->withPort($driver == 'mysql' ? 3306 : 5432)
            ->withDbName('hello_world')
            ->withUsername('benchmarkdbuser')
            ->withPassword('benchmarkdbpass');

        self::$pool = new PDOPool($config, 20);
    }

    public static function db(): string
    {
        $pdo = self::get();
        $result = Operation::db($pdo->prepare(Operation::WORLD_SELECT_SQL));
        self::put($pdo);

        return $result;
    }

    public static function fortunes(): string
    {
        $pdo = self::get();
        $result = Operation::fortunes($pdo->prepare(Operation::FORTUNE_SQL));
        self::put($pdo);

        return $result;
    }

    public static function query(int $queries): string
    {
        $pdo = self::get();
        $result = Operation::query($pdo->prepare(Operation::WORLD_SELECT_SQL), $queries);
        self::put($pdo);

        return $result;
    }

    public static function updates(int $queries): string
    {
        $pdo = self::get();
        $result = Operation::updates($pdo->prepare(Operation::WORLD_SELECT_SQL), $pdo->prepare(Operation::WORLD_UPDATE_SQL), $queries);
        self::put($pdo);

        return $result;
    }

    private static function get(): PDO|PDOProxy
    {
        return self::$pool->get();
    }

    private static function put(PDO|PDOProxy $db): void
    {
        self::$pool->put($db);
    }
}
