<?php
/**
 * db.php
 * - PDOでMySQLに接続する db()
 * - JSONレスポンスを返す json_response()
 */

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $host = getenv('DB_HOST') ?: 'db';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'quizdb';
    $user = getenv('DB_USER') ?: 'quizuser';
    $pass = getenv('DB_PASS') ?: 'quizpass';

    // DSN（接続文字列）
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    // PDOオプション
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ];

    // DB起動レース対策：最大30秒（0.5秒×60回）までリトライ
    $tries = 60;
    $sleepUs = 500000; // 0.5s
    while (true) {
        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
            // 念押しでセッションの文字コードを統一
            $pdo->exec("SET character_set_client = utf8mb4");
            $pdo->exec("SET character_set_connection = utf8mb4");
            $pdo->exec("SET character_set_results = utf8mb4");
            $pdo->exec("SET collation_connection = utf8mb4_0900_ai_ci");
            return $pdo;
        } catch (Throwable $e) {
            if (--$tries <= 0) {
                throw $e;
            }
            usleep($sleepUs);
        }
    }
}

/**
 * JSONレスポンスを返す小道具
 */
function json_response($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET,POST,OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
