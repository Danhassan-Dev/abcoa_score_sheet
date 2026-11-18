-- Run once on an existing Phase 9 database. Fresh installs already include this column.
ALTER TABLE semesters
  ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER semester_name;
