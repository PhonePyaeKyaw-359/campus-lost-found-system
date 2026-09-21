-- Run as a MySQL administrator after choosing a strong local password.
-- The application user intentionally cannot alter activity history.
CREATE USER 'campus_lost_found_app'@'localhost' IDENTIFIED BY 'replace-with-a-strong-password';
GRANT SELECT, INSERT, UPDATE, DELETE ON campus_lost_found.users TO 'campus_lost_found_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON campus_lost_found.lost_reports TO 'campus_lost_found_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON campus_lost_found.found_reports TO 'campus_lost_found_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON campus_lost_found.found_items TO 'campus_lost_found_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON campus_lost_found.claims TO 'campus_lost_found_app'@'localhost';
GRANT SELECT, INSERT ON campus_lost_found.activity_logs TO 'campus_lost_found_app'@'localhost';
FLUSH PRIVILEGES;
