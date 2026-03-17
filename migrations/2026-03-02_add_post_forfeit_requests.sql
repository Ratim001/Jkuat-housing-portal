-- Migration: add table to store post-closed forfeit requests
CREATE TABLE IF NOT EXISTS post_forfeit_requests (
  id INT NOT NULL AUTO_INCREMENT,
  request_id VARCHAR(40) NOT NULL UNIQUE,
  applicant_id VARCHAR(32) NOT NULL,
  ballot_id VARCHAR(64) DEFAULT NULL,
  application_id VARCHAR(64) DEFAULT NULL,
  reason TEXT NOT NULL,
  attachment VARCHAR(255) DEFAULT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_id VARCHAR(32) DEFAULT NULL,
  decision_notes TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  decided_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  INDEX idx_applicant (applicant_id),
  INDEX idx_status (status)
);
