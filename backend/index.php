<?php
/**
 * --------------------------------------------
 * index.php
 * 役割:
 *   - 非フレームワークの簡易ルーター
 *   - /health, /api/questions/random, /api/answers/check を実装
 * 流れ:
 *   1) .htaccess が全てのURLをここに転送
 *   2) $path（リクエストパス）で分岐
 *   3) 必要なら DB に問い合わせ、JSON を返す
 * --------------------------------------------
 */

// backend/index.php
// 本番では画面にエラーを出さない（ログにだけ出す）※開発中は必要に応じて切り替え
ini_set('display_errors', '0');
error_reporting(E_ALL);

require __DIR__ . '/db.php';

// CORSプリフライト（最初にヘッダを返して即終了）
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET,POST,OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

// ★ ここで初めて $path / $method を決める（以後どのルートでも使える）
$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($path === '/debug/title-bytes') {
    $pdo = db();
    $row = $pdo->query("SELECT title, HEX(title) AS hex FROM questions LIMIT 1")->fetch();
    json_response($row ?: []);
}

try {
    // --- 文字コード診断ルート（あとで消してOK） ---
    if ($path === '/debug/charset') {
        $pdo = db();
        $vars  = $pdo->query("SHOW VARIABLES LIKE 'character_set_%'")->fetchAll();
        $vars2 = $pdo->query("SHOW VARIABLES LIKE 'collation_%'")->fetchAll();
        json_response(['character_set' => $vars, 'collation' => $vars2]);
    }




    /**
     * GET /health
     * システム起動確認用の軽いエンドポイント
     * @return {"ok":1}
     */
    if ($path === '/health') {
        json_response(['ok' => 1]);
    }


    /**
     * GET /api/questions/random
     * ランダムで1問と、その選択肢（ランダム順）を返す。
     * レスポンス例:
     * {
     *   "question": {"id":1, "title":"HTTPの既定ポート番号はどれ？"},
     *   "choices":  [{"id":3,"body":"80"}, ...]
     * }
     */
    if ($path === '/api/questions/random' && $method === 'GET') {
        $pdo = db();

        // ランダムに1問
        $q = $pdo->query("SELECT id, title FROM questions ORDER BY RAND() LIMIT 1")->fetch();
        if (!$q) json_response(['message' => 'no questions'], 404);

        // 選択肢をランダム順で
        $st = $pdo->prepare("SELECT id, body FROM choices WHERE question_id = ? ORDER BY RAND()");
        $st->execute([$q['id']]);
        $choices = $st->fetchAll();

        json_response([
            'question' => $q,            // { id, title }
            'choices'  => $choices,      // [{ id, body }, ...]
        ]);
    }


    /**
     * POST /api/answers/check
     * 指定された question_id / choice_id の正誤を判定して返す。
     * 入力(JSON):
     *   { "question_id": number, "choice_id": number }
     * 出力(JSON):
     *   { "correct": boolean, "answer": { "id": number, "body": string } }
     */
    if ($path === '/api/answers/check' && $method === 'POST') {
        $pdo = db();
        // リクエストボディ(JSON)を取り出して配列化
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);

         // 最低限の入力チェック
        $qid = isset($input['question_id']) ? (int)$input['question_id'] : 0;
        $cid = isset($input['choice_id'])   ? (int)$input['choice_id']   : 0;

        if ($qid <= 0 || $cid <= 0) {
            json_response(['message' => 'bad request'], 400);
        }

        // 指定のchoiceがそのquestionのものか＆正解かをチェック
        $st = $pdo->prepare("SELECT is_correct FROM choices WHERE id = ? AND question_id = ?");
        $st->execute([$cid, $qid]);
        $row = $st->fetch();
        if (!$row) json_response(['message' => 'choice not found'], 404);

        $correct = (int)$row['is_correct'] === 1;

        // ついでに正解の選択肢も返す（学習しやすい）
        $st2 = $pdo->prepare("SELECT id, body FROM choices WHERE question_id = ? AND is_correct = 1 LIMIT 1");
        $st2->execute([$qid]);
        $ans = $st2->fetch();

        json_response([
            'correct' => $correct,
            'answer'  => $ans,      // { id, body } 例: 正解 "80"
        ]);
    }

    // どのルートにも一致しない場合
    // ここまでマッチしなければ404
    json_response(['message' => 'not found'], 404);

} catch (Throwable $e) {
    // 最低限のエラーハンドリング
    // 予期せぬ例外は 500 で返す（本番はログにのみ詳細を残す）
    json_response(['message' => 'server error', 'error' => $e->getMessage()], 500);
}
