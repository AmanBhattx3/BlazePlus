-- BlazePlus Database Schema
CREATE DATABASE IF NOT EXISTS blazeplus;
USE blazeplus;

-- Users who signed up but haven't completed verify.php OR are waiting on admin
CREATE TABLE unverified (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    emp_no VARCHAR(50) DEFAULT NULL,
    dob DATE DEFAULT NULL,
    department VARCHAR(50) DEFAULT NULL,
    role VARCHAR(20) DEFAULT NULL,
    verify_submitted_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Approved, active users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    emp_no VARCHAR(50) NOT NULL,
    dob DATE NOT NULL,
    department VARCHAR(50) NOT NULL,
    role ENUM('employee','manager','senior') NOT NULL DEFAULT 'employee',
    hide_contact TINYINT(1) NOT NULL DEFAULT 0,
    verified_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Permanent log of every approval (verified = verify + users, per Blaze's spec)
CREATE TABLE verify (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    emp_no VARCHAR(50) NOT NULL,
    dob DATE NOT NULL,
    department VARCHAR(50) NOT NULL,
    role VARCHAR(20) NOT NULL,
    verified_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Rejected users
CREATE TABLE banned (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    emp_no VARCHAR(50) DEFAULT NULL,
    dob DATE DEFAULT NULL,
    department VARCHAR(50) DEFAULT NULL,
    role VARCHAR(20) DEFAULT NULL,
    reason VARCHAR(255) NOT NULL DEFAULT 'unknown identity',
    banned_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Admins (gods - no restrictions)
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

-- Seed admin: Aman / admin / Admin@123
-- Password below is bcrypt hash of "Admin@123" (verify with PHP password_verify)
INSERT INTO admins (name, username, password) VALUES
('Aman', 'admin', '$2b$12$ONu5B5flyQBmKBuobeO.3uza0JR3x/zw2ohVdbTTYv.mGOi1zGdfS');
select * from verify;

-- Transfer requests (department/role change requests from users)
CREATE TABLE transfer_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    current_department VARCHAR(50) NOT NULL,
    current_role VARCHAR(20) NOT NULL,
    requested_department VARCHAR(50) NOT NULL,
    requested_role VARCHAR(20) NOT NULL,
    reason VARCHAR(500) NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    decided_at DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
ALTER TABLE users ADD COLUMN hide_email TINYINT(1) NOT NULL DEFAULT 0 AFTER hide_contact;
ALTER TABLE users ADD COLUMN last_phone_change DATETIME DEFAULT NULL AFTER phone;
ALTER TABLE users ADD COLUMN last_email_change DATETIME DEFAULT NULL AFTER email;

-- Records every profile change a user makes to their own contact info/visibility
CREATE TABLE logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    field_changed VARCHAR(50) NOT NULL,
    old_value VARCHAR(255),
    new_value VARCHAR(255),
    changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE contact_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requester_id INT NOT NULL,
    target_id INT NOT NULL,
    reason VARCHAR(500) NOT NULL,
    status ENUM('pending','approved','declined') NOT NULL DEFAULT 'pending',
    shared_field ENUM('phone','email','both') DEFAULT NULL,
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    decided_at DATETIME DEFAULT NULL,
    FOREIGN KEY (requester_id) REFERENCES users(id),
    FOREIGN KEY (target_id) REFERENCES users(id)
);
select * from contact_requests;
CREATE TABLE chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_key VARCHAR(50) NOT NULL,       -- 'it', 'hr', 'finance', 'sales', 'operations', 'marketing', 'general'
    user_id INT NOT NULL,
    message VARCHAR(1000) DEFAULT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE announcement_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_key VARCHAR(50) NOT NULL,       -- 'it_announcements', 'hr_announcements', ..., 'announcement' (all-depts)
    user_id INT NOT NULL,
    message VARCHAR(1000) DEFAULT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    pdf_path VARCHAR(255) DEFAULT NULL,   -- only ever populated when sender's role = 'senior'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
	CREATE TABLE message_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    message_type ENUM('chat','announcement') NOT NULL,
    room_key VARCHAR(50) NOT NULL,
    reporter_id INT NOT NULL,
    reported_user_id INT NOT NULL,
    message_text VARCHAR(1000) DEFAULT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    pdf_path VARCHAR(255) DEFAULT NULL,
    reason VARCHAR(500) NOT NULL,
    status ENUM('open','resolved','dismissed') NOT NULL DEFAULT 'open',
    reported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    decided_at DATETIME DEFAULT NULL,
    FOREIGN KEY (reporter_id) REFERENCES users(id),
    FOREIGN KEY (reported_user_id) REFERENCES users(id)
);
use blazeplus;
