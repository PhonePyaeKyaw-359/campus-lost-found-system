-- Run as a MySQL administrator after choosing a strong local password.
-- The application user intentionally cannot alter activity history.
CREATE USER IF NOT EXISTS 'campus_lost_found_app'@'localhost' IDENTIFIED BY 'replace-with-a-strong-password';
CREATE USER IF NOT EXISTS 'campus_lost_found_app'@'127.0.0.1' IDENTIFIED BY 'replace-with-a-strong-password';
GRANT SELECT, INSERT, UPDATE, DELETE ON campus_lost_found.users TO 'campus_lost_found_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON campus_lost_found.users TO 'campus_lost_found_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON campus_lost_found.lost_reports TO 'campus_lost_found_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON campus_lost_found.lost_reports TO 'campus_lost_found_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON campus_lost_found.found_reports TO 'campus_lost_found_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON campus_lost_found.found_reports TO 'campus_lost_found_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON campus_lost_found.found_items TO 'campus_lost_found_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON campus_lost_found.found_items TO 'campus_lost_found_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON campus_lost_found.claims TO 'campus_lost_found_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON campus_lost_found.claims TO 'campus_lost_found_app'@'127.0.0.1';
GRANT SELECT, INSERT ON campus_lost_found.activity_logs TO 'campus_lost_found_app'@'localhost';
GRANT SELECT, INSERT ON campus_lost_found.activity_logs TO 'campus_lost_found_app'@'127.0.0.1';
FLUSH PRIVILEGES;
