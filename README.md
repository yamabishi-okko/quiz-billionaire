# クイズ🪼†-ビリオネア-†
Vue 3 + TypeScript + PHP + MySQL を使った四択クイズアプリ。  
SQL の基本操作（CRUD・JOIN・制約・ランダム抽出など）を学ぶことを目的にしています。
プレイヤー側からもクイズを新規で登録可能
[ブラウザ(Vue)] --HTTP--> [Apache/PHP] --SQL--> [MySQL]
      　　↑      　　　　　　　　　　              JSON返す 　　　　         （データ保管）
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
1. リポジトリ直下で
   ```docker compose up -d```
