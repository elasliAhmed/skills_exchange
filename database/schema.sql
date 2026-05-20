-- Skills Exchange Platform Database Schema

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    bio TEXT,
    profile_picture VARCHAR(255) DEFAULT 'default.png',
    credits INT DEFAULT 10,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Skills table
CREATE TABLE skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User skills (teaching and learning)
CREATE TABLE user_skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    skill_id INT DEFAULT NULL,
    type ENUM('teach','learn') NOT NULL DEFAULT 'teach',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    skill_name VARCHAR(100) NOT NULL DEFAULT 'Custom Skill',
    skill_description TEXT,
    skill_level VARCHAR(50) DEFAULT NULL,
    lesson_format TEXT,
    learner_gains TEXT,
    credits INT DEFAULT 5,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_skill (user_id, skill_name, type),
    KEY skill_id (skill_id)
);

-- Lesson requests table
CREATE TABLE lesson_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requester_id INT NOT NULL,
    teacher_id INT NOT NULL,
    skill_id INT NOT NULL,
    message TEXT,
    status ENUM('pending', 'accepted', 'rejected', 'completed', 'cancelled') DEFAULT 'pending',
    scheduled_time DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
);

-- Transactions table for credit tracking
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('earned', 'spent') NOT NULL,
    amount INT NOT NULL,
    description VARCHAR(255),
    reference_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Messages table for chat
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    lesson_request_id INT,
    message TEXT NOT NULL,
    read_status BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_request_id) REFERENCES lesson_requests(id) ON DELETE SET NULL
);

-- Reviews table
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reviewer_id INT NOT NULL,
    reviewed_id INT NOT NULL,
    lesson_request_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_request_id) REFERENCES lesson_requests(id) ON DELETE CASCADE
);

-- Enrollment table for learners to join teaching offers
CREATE TABLE enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    offer_id INT NOT NULL,
    learner_id INT NOT NULL,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (offer_id) REFERENCES user_skills(id) ON DELETE CASCADE,
    FOREIGN KEY (learner_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (offer_id, learner_id)
);

-- Insert default skills
INSERT IGNORE INTO skills (name, description) VALUES
('Web Development', 'Building websites and web applications'),
('Mobile Development', 'Building mobile apps for iOS and Android'),
('Graphic Design', 'Visual design and branding'),
('Photography', 'Digital and film photography'),
('Video Editing', 'Post-production video editing'),
('Music Production', 'Audio recording and production'),
('Language Learning', 'Teaching foreign languages'),
('Math', 'Mathematics tutoring'),
('Science', 'Physics, Chemistry, Biology'),
('Fitness Training', 'Personal training and fitness coaching');