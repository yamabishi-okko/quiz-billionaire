ざっくり役割(自分用)

 ### Apache（アパッチ）
Webサーバー。HTTPリクエストを受け取って、
・静的ファイルを返す（画像/JS/CSS）
・PHPを通してAPI応答を返す（index.php を実行）
みたいな「玄関口」の役。
mod_rewrite と .htaccess を使って、/api/... などのURLを全部 index.php に転送できる。

### PHP（＋PDO）
サーバー側の処理言語。今回だと
・ルーティングもどき（index.php でパスを見て分岐）
・MySQLに接続してクエリ（db.php のPDO）
・JSONでレスポンス
を担当。

### MySQL
データの置き場。テーブル（questions / choices）を作り、初期データ投入。
db/init/*.sql はコンテナ初回起動時だけ自動で実行される。

### Docker Compose
「PHP+Apacheの箱」と「MySQLの箱」を一緒に起動/停止する台本。
docker-compose.yml が台本本体。環境変数の受け渡しもここでやる。

### 今回のファイル
・backend/Dockerfile … Apache+PHPの箱のレシピ
・backend/apache.vhost.conf … .htaccess を効かせる設定（AllowOverride All）
・backend/.htaccess … URLを index.php に集約
・backend/db.php … PDOでDB接続する便利関数
・backend/index.php … 簡易API（/health、/api/questions/random、/api/answers/check）
・db/init/001_schema.sql … テーブル作成&初期データ投入
・db/init/002_join_examples.sql … 手動練習クエリ集



1) 「.htaccess の有効化を宣言」ってなに？
.htaccess は「そのフォルダ専用の Apache 設定メモ」。
例：このフォルダに来たURLはぜんぶ index.php に回してね、など。
でも、Apacheはデフォルトでは .htaccess を読まない。
そこで仮想ホスト設定（apache.vhost.conf）の中で、
AllowOverride All を書く＝「.htaccess を有効化します」と宣言する。
これにより .htaccess の書き換えルール（Rewrite）が効くようになる。
まとめ：
AllowOverride All を入れる＝「このディレクトリの .htaccess を信じて実行してOK」の合図。


2) 「Apache の rewrite 機能をON」＆「URL 書き換えモジュール」
Apacheにmod_rewrite（URLを書き換える機能のモジュール）があり、
それを a2enmod rewrite でONにする。
何が嬉しい？
/api/questions/random みたいな物理ファイルが無いURLを
.htaccess のルールで index.php に転送できる。
その結果、index.php の中で $path を見て「/health ならこれ返す…」とPHPでルーティングできる。
たとえ：
受付（Apache）が来客（URL）を見て、全部「総合窓口」（index.php）に誘導して、
窓口で「健康診断の人はこちら（/health）」「問題ランダムはこちら（/api/questions/random）」と案内する感じ。

3) PDO の 3つの設定（怖くない版）
db.php でPDOを作るときのオプションの意味。
PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
→ SQLでエラーが起きたら「黙って失敗」じゃなく例外（エラーとして投げる）。
そのほうがバグに気づけるし、try { ... } catch { ... } で正しいエラーレスポンスを返せる。
PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
→ SELECT の結果を**連想配列（カラム名でアクセス）**で受け取る設定。
例：$row['title'] で取れる。数字の配列より読みやすい。
PDO::ATTR_EMULATE_PREPARES => false
→ “エミュレート”せず、DBの本物のプリペアドステートメントを使う。
プレースホルダ（?）に値を後から入れる方式で、
SQLインジェクション対策として超基本＆重要。
悪い例："SELECT * FROM users WHERE name = '$name'"（直接つなぐ）
良い例："SELECT * FROM users WHERE name = ?" で execute([$name])
まとめ：
ERRMODE_EXCEPTION … 失敗は例外で知らせて！
FETCH_ASSOC … ['title' => '…'] みたいに読みやすく返して！
EMULATE false … 本物の「安全な注入方法（プリペアド）」を使って！

4) CORS（簡易）と「Originを絞る」
CORS = Cross-Origin Resource Sharing（異なるオリジン間のリクエスト許可ルール）。
オリジン＝スキーム + ホスト名 + ポート の組み合わせ。
例：
フロント：http://localhost:5173（Vite）
バック：http://localhost:3001（Apache）
→ ポートが違うので別オリジン。ブラウザはデフォルトではJS経由の通信をブロックする。
だからサーバー（PHP）側で
Access-Control-Allow-Origin を返して「このフロントからのリクエストOKだよ」と宣言する。
今は学習用に *（全部許可）にしてる。
本番なら http://example.com など自分のフロントだけに絞る（セキュリティのため）。
まとめ：
別ポート＝別オリジン。ブラウザがブロックするので、サーバーが**「その相手を許可」**する必要がある。

5) CORS のプリフライト（OPTIONS）って？
ブラウザは、安全か確認するために「本番のPOSTの前にお伺いリクエスト」を送ることがある。
それが OPTIONS メソッドのプリフライト。
サーバーはそれに対して
Access-Control-Allow-Origin/Methods/Headers などを返すだけでOK（中身は不要）。
index.php の最初で
```
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { ... exit; }
```
としているのは、プリフライトには即OKを返すため。
こうしないと、フロントのfetchがCORSで止まることがある。
たとえ：
「このドア（API）通っていい？」（OPTIONS）→「OK、入って！」（ヘッダで許可）→ 本番のPOSTが届く、の順。