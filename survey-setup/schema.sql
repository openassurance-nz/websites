-- OpenAssurance survey: run once in phpMyAdmin against the database you created.
-- Answers are stored as coded values in JSON, so the question set can change
-- without a change to this table.

CREATE TABLE IF NOT EXISTS survey_responses (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_at     DATETIME     NOT NULL,            -- UTC
  survey_version VARCHAR(16)  NOT NULL,
  role           VARCHAR(32)  NOT NULL,
  answers        TEXT         NOT NULL,            -- JSON of question id to option code
  comment        TEXT         NULL,
  contact        VARCHAR(200) NULL,                -- only ever filled in when the respondent chose to
  removal_hash   CHAR(64)     NOT NULL,            -- SHA-256 of the respondent's removal code
  PRIMARY KEY (id),
  KEY idx_created_at (created_at),
  KEY idx_removal_hash (removal_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
