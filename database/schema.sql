-- Campus Lost and Found System schema (MySQL 8.0+)
CREATE DATABASE IF NOT EXISTS campus_lost_found
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE campus_lost_found;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_number VARCHAR(20) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('student', 'staff', 'admin') NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_users_id_number UNIQUE (id_number),
    CONSTRAINT fk_users_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE RESTRICT,
    INDEX idx_users_role_active (role, is_active)
) ENGINE=InnoDB;

CREATE TABLE found_reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    category VARCHAR(40) NOT NULL,
    description TEXT NOT NULL,
    found_location VARCHAR(50) NOT NULL,
    location_detail VARCHAR(120) NULL,
    found_datetime DATETIME NOT NULL,
    status ENUM('submitted', 'received', 'closed') NOT NULL DEFAULT 'submitted',
    staff_reason VARCHAR(255) NULL,
    handled_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_found_reports_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT fk_found_reports_handled_by FOREIGN KEY (handled_by) REFERENCES users (id) ON DELETE RESTRICT,
    INDEX idx_found_reports_status_created (status, created_at),
    INDEX idx_found_reports_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE found_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_number VARCHAR(12) NULL,
    found_report_id INT UNSIGNED NULL,
    finder_user_id INT UNSIGNED NULL,
    item_name VARCHAR(100) NOT NULL,
    category VARCHAR(40) NOT NULL,
    colour VARCHAR(20) NULL,
    brand VARCHAR(50) NULL,
    public_description TEXT NOT NULL,
    private_details TEXT NOT NULL,
    found_location VARCHAR(50) NOT NULL,
    location_detail VARCHAR(120) NULL,
    found_datetime DATETIME NULL,
    received_datetime DATETIME NOT NULL,
    received_by INT UNSIGNED NOT NULL,
    photo_path VARCHAR(255) NULL,
    show_photo_publicly TINYINT(1) NOT NULL DEFAULT 0,
    storage_location VARCHAR(100) NOT NULL,
    status ENUM('in_storage', 'claimed', 'returned') NOT NULL DEFAULT 'in_storage',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_found_items_reference UNIQUE (reference_number),
    CONSTRAINT uq_found_items_report UNIQUE (found_report_id),
    CONSTRAINT fk_found_items_report FOREIGN KEY (found_report_id) REFERENCES found_reports (id) ON DELETE RESTRICT,
    CONSTRAINT fk_found_items_finder FOREIGN KEY (finder_user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT fk_found_items_received_by FOREIGN KEY (received_by) REFERENCES users (id) ON DELETE RESTRICT,
    INDEX idx_found_items_status_category (status, category),
    INDEX idx_found_items_status_location (status, found_location),
    INDEX idx_found_items_name (item_name)
) ENGINE=InnoDB;

CREATE TABLE lost_reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    category VARCHAR(40) NOT NULL,
    colour VARCHAR(20) NULL,
    brand VARCHAR(50) NULL,
    description TEXT NOT NULL,
    private_details TEXT NULL,
    lost_location VARCHAR(50) NOT NULL,
    location_detail VARCHAR(120) NULL,
    lost_datetime DATETIME NOT NULL,
    photo_path VARCHAR(255) NULL,
    possible_match_item_id INT UNSIGNED NULL,
    status ENUM('lost', 'possible_match', 'resolved') NOT NULL DEFAULT 'lost',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lost_reports_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT fk_lost_reports_match FOREIGN KEY (possible_match_item_id) REFERENCES found_items (id) ON DELETE RESTRICT,
    INDEX idx_lost_reports_user_status (user_id, status),
    INDEX idx_lost_reports_status_category (status, category)
) ENGINE=InnoDB;

CREATE TABLE claims (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    found_item_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    private_details_given TEXT NOT NULL,
    lost_location VARCHAR(50) NOT NULL,
    lost_datetime DATETIME NOT NULL,
    additional_info TEXT NULL,
    status ENUM('pending', 'approved', 'rejected', 'cancelled', 'closed', 'collected') NOT NULL DEFAULT 'pending',
    staff_reason VARCHAR(255) NULL,
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    id_checked TINYINT(1) NOT NULL DEFAULT 0,
    id_checked_by INT UNSIGNED NULL,
    id_checked_at DATETIME NULL,
    collected_at DATETIME NULL,
    released_by INT UNSIGNED NULL,
    pending_user_id INT UNSIGNED GENERATED ALWAYS AS (IF(status = 'pending', user_id, NULL)) STORED,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_claims_item FOREIGN KEY (found_item_id) REFERENCES found_items (id) ON DELETE RESTRICT,
    CONSTRAINT fk_claims_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT fk_claims_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT fk_claims_checker FOREIGN KEY (id_checked_by) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT fk_claims_releaser FOREIGN KEY (released_by) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT uq_claims_single_pending UNIQUE (found_item_id, pending_user_id),
    INDEX idx_claims_user_status (user_id, status),
    INDEX idx_claims_item_status (found_item_id, status)
) ENGINE=InnoDB;

CREATE TABLE activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(40) NOT NULL,
    entity_type VARCHAR(20) NULL,
    entity_id INT UNSIGNED NULL,
    details VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activity_logs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
    INDEX idx_activity_logs_created (created_at),
    INDEX idx_activity_logs_entity (entity_type, entity_id),
    INDEX idx_activity_logs_user_created (user_id, created_at)
) ENGINE=InnoDB;
