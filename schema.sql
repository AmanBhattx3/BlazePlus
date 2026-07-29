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
