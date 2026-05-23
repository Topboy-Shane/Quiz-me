CREATE DATABASE Quiz_me;
USE Quiz_me;
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id VARCHAR(10) UNIQUE,
  role ENUM('student','facilitator') NOT NULL,
  first_name VARCHAR(50),
  last_name VARCHAR(50),
  email VARCHAR(100) UNIQUE,
  password VARCHAR(255),
  profile_photo VARCHAR(255),
  email_verified TINYINT DEFAULT 0,
  verification_token VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE courses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_name VARCHAR(100) NOT NULL
);
CREATE TABLE student_courses (
  student_id INT,
  course_id INT,
  PRIMARY KEY (student_id, course_id),
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);
CREATE TABLE assessments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT,
  title VARCHAR(100),
  pass_mark INT DEFAULT 85,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);
CREATE TABLE questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  assessment_id INT,
  question TEXT,
  FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE
);
CREATE TABLE results (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT,
  assessment_id INT,
  score DECIMAL(5,2),
  passed TINYINT,
  completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE
);
CREATE TABLE course_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT,
  user_id INT,
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    option_text TEXT NOT NULL,
    is_correct TINYINT(1) DEFAULT 0,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

ALTER TABLE questions DROP COLUMN correct_answer;
ALTER TABLE assessments ADD max_attempts INT DEFAULT 1;
ALTER TABLE results ADD attempt_no INT DEFAULT 1;
CREATE TABLE assessment_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    assessment_id INT NOT NULL,
    attempts_used INT DEFAULT 0,
    extra_attempts INT DEFAULT 0,
    UNIQUE(student_id, assessment_id)
);
ALTER TABLE assessment_attempts
ADD start_time DATETIME NULL,
ADD deadline DATETIME NULL;
ALTER TABLE student_courses
ADD assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE assessment_attempts
ADD UNIQUE KEY uniq_student_assessment (student_id, assessment_id);
ALTER TABLE users 
ADD failed_attempts INT DEFAULT 0,
ADD account_locked_until DATETIME NULL;
ALTER TABLE users 
ADD COLUMN last_login DATETIME NULL;
ALTER TABLE users 
ADD password_reset DATETIME;
ALTER TABLE course_assignments 
ADD status ENUM('assigned','in_progress','completed') DEFAULT 'assigned',
ADD completed_at TIMESTAMP NULL;
ALTER TABLE course_assignments 
ADD progress INT DEFAULT 0;
ALTER TABLE courses 
ADD content_type ENUM('video', 'text', 'mixed') DEFAULT 'text';
ALTER TABLE courses 
ADD content LONGTEXT,
ADD video_url VARCHAR(255);
ALTER TABLE course_assignments
ADD UNIQUE KEY unique_user_course (course_id, user_id);