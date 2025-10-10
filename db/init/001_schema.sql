-- 001_schema.sql (FKなし版：まずはDBを必ず起動する)
USE `quizdb`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `choices`;
DROP TABLE IF EXISTS `questions`;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `questions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `choices` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `question_id` INT UNSIGNED NOT NULL,
  `body` TEXT NOT NULL,
  `is_correct` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_choices_question_id` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 「正解は1つ」制約（生成列+UNIQUE）は維持してOK
ALTER TABLE `choices`
  ADD COLUMN `correct_qid` INT UNSIGNED
    GENERATED ALWAYS AS (CASE WHEN `is_correct` = 1 THEN `question_id` ELSE NULL END) STORED,
  ADD UNIQUE KEY `uq_choices_one_correct_per_question` (`correct_qid`);

-- サンプルデータ
INSERT INTO `questions` (`title`) VALUES (_utf8mb4'HTTPの既定ポート番号はどれ？');

INSERT INTO `choices` (`question_id`, `body`, `is_correct`) VALUES
(1, '21', 0),
(1, '25', 0),
(1, '80', 1),
(1, '110', 0);
