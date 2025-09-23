/* -----------------------------
  questions テーブル
  ・クイズの「問題」本体を保存するテーブル
  ・1つの問題に対して、choicesテーブルに複数の選択肢が紐づく
----------------------------- */
CREATE TABLE IF NOT EXISTS questions ( -- questions」というテーブル（問題リスト）を作る命令
  id INT AUTO_INCREMENT PRIMARY KEY,
  /*
    id = 問題のID（ユニークな番号）
    INT = 整数
    AUTO_INCREMENT = 新しい行を追加するたびに自動で1ずつ増える
    PRIMARY KEY = この列が「その行を一意に識別するカギ」
  */

  title TEXT NOT NULL,
  /*
    title = 問題文
    TEXT = 長めの文字列を入れられる型
    NOT NULL = 空欄は禁止
  */
  
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  /*
    created_at = 作成日時（デフォルトで現在時刻が入る）
    updated_at = 更新日時（更新するたびに自動で現在時刻に変わる）
  */

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 
    /*
      ENGINE=InnoDB = 外部キーなどが使えるモード
      utf8mb4 = 絵文字も扱える文字コード
    */


CREATE TABLE IF NOT EXISTS choices ( --「choices」というテーブル（選択肢リスト）を作る命令
  id INT AUTO_INCREMENT PRIMARY KEY,
  question_id INT NOT NULL, --どの問題に属する選択肢かを示すID（questions.id とつながる）
  body TEXT NOT NULL, --選択肢の文章（例: "80"）
  is_correct TINYINT(1) NOT NULL DEFAULT 0, --正解かどうかを 0/1 で表す.TINYINT(1) = 小さな整数（1桁）。MySQLでは慣習的に 0=false, 1=true として使う
  CONSTRAINT fk_choices_question
    FOREIGN KEY (question_id) REFERENCES questions(id)
    ON DELETE CASCADE
    /*
      外部キー制約: question_id は questions.id のどれかに対応してないといけない
      ON DELETE CASCADE = 親の問題が削除されたら、その問題に紐づく選択肢も全部自動で削除される
    */
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


/* -----------------------------
  サンプルデータの投入
  ・まず questions に1つ問題を登録
  ・その後、その問題に紐づく4つの選択肢を登録
----------------------------- */
INSERT INTO questions (title) VALUES
('HTTPの既定ポート番号はどれ？');
  /*
    questions テーブルに新しい問題を1つ追加
    id は自動採番される（1になる）
  */

-- 選択肢の登録（id=1の問題に4つ追加）
INSERT INTO choices (question_id, body, is_correct) VALUES
(1, '21', 0),   -- FTPのポート番号（誤り）
(1, '25', 0),   -- メール(SMTP)のポート番号（誤り）
(1, '80', 1),   -- HTTPの標準ポート番号（正解）
(1, '110', 0);  -- POP3のポート番号（誤り）

/*
choices テーブルに4つの選択肢をまとめて追加
question_id = 1 → さっき追加した問題（id=1）に紐づける
body = 選択肢の文章
is_correct = 正解なら 1、不正解なら 0
→ この例では "80" が正解 (is_correct=1)
*/


/* -----------------------------
  choicesテーブルに「1問につき正解は1つだけ」という制約を追加
  ・question_id と is_correct=1 の組み合わせが重複しないように UNIQUE制約を設定
  ・つまり、同じ question_id に「is_correct=1」が2つ以上入るとエラーになる
----------------------------- */
ALTER TABLE choices
ADD CONSTRAINT uq_question_correct UNIQUE (question_id, is_correct);