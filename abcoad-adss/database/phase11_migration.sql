-- Phase 11 migration: add semester description. Run once on an existing Phase 10 database.
USE abcoad_adss;

ALTER TABLE semesters
  ADD COLUMN description TEXT NULL AFTER semester_name;
