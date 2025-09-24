<?php
/**
 * --------------------------------------------
 * db.php
 * 役割:
 *   - PDO を使って MySQL に接続する関数 db()
 *   - JSON を返すユーティリティ json_response()
 * ポイント:
 *   - 環境変数(DB_HOST など)は docker-compose.yml から流れてくる
 *   - PDO は例外モードで安全に（SQLインジェクション対策は prepare/execute）
 * --------------------------------------------
 */

/**
 * DB接続オブジェクト(PDO)を返す。
 * 初回に生成して以降はシングルトン的に再利用。
 *
 * @return PDO MySQL への接続
 * @throws PDOException 接続やクエリで失敗した場合
 */


// backend/db.php

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $host = getenv('DB_HOST') ?: 'db';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'quizdb';
    $user = getenv('DB_USER') ?: 'quizuser';
    $pass = getenv('DB_PASS') ?: 'quizpass';
    // DSN: Data Source Name（接続文字列）
    $dsn  = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    // ATTR_ERRMODE: 例外で通知 / FETCH_ASSOC: 連想配列 / EMULATE false: ネイティブプリペアド
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // 例外で扱う
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}


/**
 * JSONでHTTPレスポンスを返して終了する小道具。
 *
 * @param mixed $data 返したい配列/オブジェクト
 * @param int   $status HTTP ステータスコード（デフォルト200）
 * @return void
 */
function json_response($data, int $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    // 簡易CORS（あとでOriginを絞る）　
    // CORS: 開発中は * で許可（本番はドメインを絞る）
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET,POST,OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
