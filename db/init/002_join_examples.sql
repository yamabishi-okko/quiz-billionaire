/* ------------------------------------
  JOIN の練習用クエリ集
  ※このファイルは自動では流れません
  コンテナに入って mysql から手動で実行すること
------------------------------------- */

-- Q1. 問題と選択肢を JOIN して全件取得
SELECT q.id AS question_id,
       q.title AS question,
       c.id AS choice_id,
       c.body AS choice_text,
       c.is_correct
FROM questions q
JOIN choices c ON q.id = c.question_id
ORDER BY q.id, c.id;

-- Q2. 問題と正解の選択肢だけを取得
SELECT q.id AS question_id,
       q.title AS question,
       c.body AS correct_answer
FROM questions q
JOIN choices c ON q.id = c.question_id
WHERE c.is_correct = 1;

-- Q3. 特定の問題 (id=1) の選択肢をランダム順で取得
SELECT c.id, c.body
FROM choices c
WHERE c.question_id = 1
ORDER BY RAND();


/*
# DBコンテナに入る
docker exec -it quizsql-db mysql -uquizuser -p quizdb
# パスワード入力 → quizpass

# MySQLシェルで実行
mysql> source /docker-entrypoint-initdb.d/../queries/002_join_examples.sql;
*/