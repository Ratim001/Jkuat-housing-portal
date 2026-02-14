-- Migration: create ballot_control table
-- Run this SQL in your database to create the ballot control table
CREATE TABLE IF NOT EXISTS ballot_control (
    id INT PRIMARY KEY DEFAULT 1,
    is_open TINYINT(1) NOT NULL DEFAULT 0,
    start_date DATETIME DEFAULT NULL,
    end_date DATETIME DEFAULT NULL
);

-- Ensure a single row exists
INSERT INTO ballot_control (id, is_open) SELECT 1, 0 WHERE NOT EXISTS (SELECT 1 FROM ballot_control WHERE id = 1);
