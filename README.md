# クイズ🪼†-ビリオネア-†
Vue 3 + TypeScript + PHP + MySQL を使った四択クイズアプリ。  
SQL の基本操作（CRUD・JOIN・制約・ランダム抽出など）を学ぶことを目的にしています。
プレイヤー側からもクイズを新規で登録可能<br>
[ブラウザ(Vue)] --HTTP--> [Apache/PHP] --SQL--> [MySQL]<br>
      　　↑      　　　　　　　　　　              JSON返す 　　　　         （データ保管）<br>
     　　 ╰──── 画面に表示 ───────╯

## 🎯 目的
- データベースに問題・選択肢・答えを保存し、ランダムに出題できるようにする
- フロントから回答を送信 → バックエンドで正誤判定 → 結果を返す
- 新しい問題や選択肢を登録できる管理機能を作る
- Docker を使って環境を統一する

## 🏗 技術スタック
- フロントエンド: Vue 3 + TypeScript + Vite
- バックエンド: PHP 8 (PDO で MySQL 接続)
- データベース: MySQL 8
- コンテナ: Docker Compose

## 📚 学習したいこと
- SQL の基本（SELECT / INSERT / UPDATE / DELETE）
- 外部キーや JOIN の使い方
- ランダム出題の書き方 (`ORDER BY RAND()`)
- 制約（「1問につき正解は1つ」など）とトランザクション
- フロントとバックエンドの API 通信の流れ

## ✅ 最初のゴール
1. Docker で MySQL + PHP + Apache を立ち上げる  
2. `/health` API が `{ "ok": 1 }` を返す  
3. `/api/questions/random` でランダムな1問を返せる  
4. `/api/answers/check` で正誤判定できる  

## 📂 ディレクトリ構成（予定）
- quiz-sql/
- backend/ # PHP(API)
- frontend/ # Vue(フロント)
- db/init/ # 初期SQL
- docker-compose.yml


## 起動方法　（適宜更新予定）
#### バックエンド（PHP+MySQL）を起動
初回・更新時は --build を付ける
```docker compose up -d --build```
起動確認
```curl http://localhost:3001/health```
=> {"ok":1}


#### フロントエンド（Vue）を起動
```cd frontend```
```npm install```
```npm run dev```

#### 停止
```docker compose down```

### ターミナルからDB操作
##### ランダム1問
```curl http://localhost:3001/api/questions/random```

##### 回答判定
```curl -X POST http://localhost:3001/api/answers/check \
  -H 'Content-Type: application/json' \
  -d '{"question_id":1,"choice_id":3}'
```

##### 作問
```curl -X POST http://localhost:3001/api/questions/create \
  -H 'Content-Type: application/json' \
  -d '{"title":"HTTPはどの階層？","choices":[
    {"body":"物理層","is_correct":false},
    {"body":"データリンク層","is_correct":false},
    {"body":"トランスポート層","is_correct":false},
    {"body":"アプリケーション層","is_correct":true}
  ]}'
```

##### 一覧
```curl 'http://localhost:3001/api/questions?limit=5&offset=0'```

##### 削除
```curl -X DELETE http://localhost:3001/api/questions/1```


## SQLコマンド
##### テーブル一覧・件数確認
```
# 例: テーブル一覧・件数確認
docker compose exec db \
  mysql -uquizuser -pquizpass quizdb \
  -e "SHOW TABLES; SELECT COUNT(*) AS questions FROM questions; SELECT COUNT(*) AS choices FROM choices;"
```

##### 問題一覧（最新5件）＋選択肢を横並びで1行にまとめる
```
docker compose exec db \
  mysql -uquizuser -pquizpass quizdb \
  -e "SELECT
        q.id,
        q.title,
        GROUP_CONCAT(CONCAT(c.id, ':', c.body,
                     CASE WHEN c.is_correct=1 THEN ' (✓)' ELSE '' END)
                     ORDER BY c.id SEPARATOR ' | ') AS choices
      FROM questions q
      JOIN choices c ON c.question_id = q.id
      GROUP BY q.id, q.title
      ORDER BY q.id DESC
      LIMIT 5;"
```

##### 問題と正解だけを一覧表示
```
docker compose exec db \
  mysql -uquizuser -pquizpass quizdb \
  -e "SELECT q.id, q.title, c.body AS correct
      FROM questions q
      JOIN choices c ON c.question_id=q.id
      WHERE c.is_correct=1
      ORDER BY q.id DESC
      LIMIT 10;"
```

##### 全てを表示（問題×選択肢を縦に並べる）
```
docker compose exec db \
  mysql -uquizuser -pquizpass quizdb \
  -e "SELECT
        q.id   AS question_id,
        q.title,
        c.id   AS choice_id,
        c.body AS choice,
        c.is_correct
      FROM questions q
      JOIN choices c ON c.question_id=q.id
      ORDER BY q.id DESC, c.id;"
```

##### 問題文を検索して表示する　（例：タイトルに「HTTP」を含む）
```
docker compose exec db \
  mysql -uquizuser -pquizpass quizdb \
  -e "SELECT id, title, created_at
      FROM questions
      WHERE title LIKE '%HTTP%'
      ORDER BY id DESC;"
```

