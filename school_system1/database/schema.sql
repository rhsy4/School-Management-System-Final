-- ============================================================
-- Нэгдсэн Цахим Сургуулийн Удирдлагын Систем — MySQL Schema
-- ============================================================

DROP DATABASE IF EXISTS school_db;
CREATE DATABASE IF NOT EXISTS school_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE school_db;

-- 1. Хэрэглэгчийн эрхийн хүснэгт
CREATE TABLE IF NOT EXISTS user_roles (
    role_id   INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(45) NOT NULL UNIQUE
);

-- 2. Хэрэглэгчдийн хүснэгт
CREATE TABLE IF NOT EXISTS users (
    user_id                  INT AUTO_INCREMENT PRIMARY KEY,
    username                 VARCHAR(100) NOT NULL UNIQUE,
    password_hash            VARCHAR(255) NOT NULL,
    role_id                  INT NOT NULL,
    full_name                VARCHAR(200) NOT NULL,
    email                    VARCHAR(150),
    phone                    VARCHAR(20),
    is_active                TINYINT(1) DEFAULT 1,
    created_at               DATETIME DEFAULT CURRENT_TIMESTAMP,
    reset_token              VARCHAR(100) NULL,
    reset_expires            DATETIME NULL,
    failed_logins            INT DEFAULT 0,
    locked_until             DATETIME NULL,
    two_factor_enabled       TINYINT(1) DEFAULT 0,
    two_factor_secret        VARCHAR(64) NULL,
    api_token                VARCHAR(80) NULL,
    api_token_expires        DATETIME NULL,
    profile_image            VARCHAR(255) NULL,
    can_edit_grades          TINYINT(1) DEFAULT 0,
    can_post_announcements   TINYINT(1) DEFAULT 1,
    last_activity            DATETIME NULL,
    FOREIGN KEY (role_id) REFERENCES user_roles(role_id)
);

-- 3. Ангийн хүснэгт
CREATE TABLE IF NOT EXISTS classes (
    class_id      INT AUTO_INCREMENT PRIMARY KEY,
    class_name    VARCHAR(50) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    teacher_id    INT,
    room          VARCHAR(50),
    FOREIGN KEY (teacher_id) REFERENCES users(user_id)
);

-- 4. Сурагчийн хүснэгт
CREATE TABLE IF NOT EXISTS students (
    student_id  INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL UNIQUE,
    last_name   VARCHAR(100) NOT NULL,
    first_name  VARCHAR(100) NOT NULL,
    register_no VARCHAR(20) NULL,
    gender      VARCHAR(10) NULL,
    birth_date  DATE,
    class_id    INT,
    parent_id   INT,
    address     VARCHAR(255),
    phone       VARCHAR(20),
    is_active   TINYINT(1) DEFAULT 1,
    merit_points INT DEFAULT 0,
    FOREIGN KEY (user_id)   REFERENCES users(user_id),
    FOREIGN KEY (class_id)  REFERENCES classes(class_id),
    FOREIGN KEY (parent_id) REFERENCES users(user_id)
);

-- 5. Багшийн хүснэгт
CREATE TABLE IF NOT EXISTS teachers (
    teacher_id  INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL UNIQUE,
    last_name   VARCHAR(100) NOT NULL,
    first_name  VARCHAR(100) NOT NULL,
    register_no VARCHAR(20) NULL,
    gender      VARCHAR(10) NULL,
    position    VARCHAR(100),
    phone       VARCHAR(20),
    email       VARCHAR(150),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- 6. Эцэг эхийн хүснэгт
CREATE TABLE IF NOT EXISTS parents (
    parent_id   INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL UNIQUE,
    last_name   VARCHAR(100) NOT NULL,
    first_name  VARCHAR(100) NOT NULL,
    phone       VARCHAR(20) NOT NULL,
    email       VARCHAR(150),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- 7. Хичээлийн хүснэгт
CREATE TABLE IF NOT EXISTS subjects (
    subject_id   INT AUTO_INCREMENT PRIMARY KEY,
    subject_name VARCHAR(150) NOT NULL,
    class_id     INT NOT NULL,
    teacher_id   INT NOT NULL,
    FOREIGN KEY (class_id)   REFERENCES classes(class_id),
    FOREIGN KEY (teacher_id) REFERENCES users(user_id)
);

-- 8. Хичээлийн хуваарийн хүснэгт
CREATE TABLE IF NOT EXISTS schedule (
    schedule_id  INT AUTO_INCREMENT PRIMARY KEY,
    class_id     INT NOT NULL,
    subject_id   INT NOT NULL,
    teacher_id   INT NOT NULL,
    day_of_week  TINYINT NOT NULL,
    start_time   TIME NOT NULL,
    end_time     TIME NOT NULL,
    room         VARCHAR(50),
    FOREIGN KEY (class_id)   REFERENCES classes(class_id),
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id),
    FOREIGN KEY (teacher_id) REFERENCES users(user_id)
);

-- 9. Ирцийн төлвийн хүснэгт
CREATE TABLE IF NOT EXISTS attendance_status (
    status_id   INT AUTO_INCREMENT PRIMARY KEY,
    status_name VARCHAR(45) NOT NULL
);

-- 10. Ирцийн бүртгэлийн хүснэгт
CREATE TABLE IF NOT EXISTS attendance (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id    INT NOT NULL,
    subject_id    INT NOT NULL,
    date          DATE NOT NULL,
    status_id     INT NOT NULL,
    recorded_by   INT NOT NULL,
    note          VARCHAR(255),
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_attendance (student_id, subject_id, date),
    FOREIGN KEY (student_id)  REFERENCES students(student_id),
    FOREIGN KEY (subject_id)  REFERENCES subjects(subject_id),
    FOREIGN KEY (status_id)   REFERENCES attendance_status(status_id),
    FOREIGN KEY (recorded_by) REFERENCES users(user_id)
);

-- 11. Дүнгийн хүснэгт
CREATE TABLE IF NOT EXISTS grades (
    grade_id    INT AUTO_INCREMENT PRIMARY KEY,
    student_id  INT NOT NULL,
    subject_id  INT NOT NULL,
    grade_type  VARCHAR(50) NOT NULL,
    grade_value DECIMAL(5,2) NOT NULL CHECK (grade_value BETWEEN 0 AND 100),
    recorded_by INT NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id)  REFERENCES students(student_id),
    FOREIGN KEY (subject_id)  REFERENCES subjects(subject_id),
    FOREIGN KEY (recorded_by) REFERENCES users(user_id)
);

-- 12. Даалгаврын хүснэгт
CREATE TABLE IF NOT EXISTS assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id    INT NOT NULL,
    title         VARCHAR(255) NOT NULL,
    description   TEXT,
    due_date      DATETIME NOT NULL,
    created_by    INT NOT NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id)  REFERENCES subjects(subject_id),
    FOREIGN KEY (created_by)  REFERENCES users(user_id)
);

-- 13. Даалгаврын хариултын хүснэгт
CREATE TABLE IF NOT EXISTS assignment_submissions (
    submission_id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_id    INT NOT NULL,
    file_url      VARCHAR(500),
    submitted_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    grade         DECIMAL(5,2),
    UNIQUE KEY uq_submission (assignment_id, student_id),
    FOREIGN KEY (assignment_id) REFERENCES assignments(assignment_id),
    FOREIGN KEY (student_id)    REFERENCES students(student_id)
);

-- 13B. Шалгалтын хүснэгтүүд
CREATE TABLE IF NOT EXISTS exams (
    exam_id          INT AUTO_INCREMENT PRIMARY KEY,
    title            VARCHAR(255) NOT NULL,
    description      TEXT,
    file_url         VARCHAR(500),
    subject_id       INT,
    class_id         INT,
    duration_minutes INT DEFAULT 60,
    is_active        TINYINT(1) DEFAULT 1,
    created_by       INT,
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE SET NULL,
    FOREIGN KEY (class_id)   REFERENCES classes(class_id)   ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(user_id)      ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS exam_questions (
    question_id   INT AUTO_INCREMENT PRIMARY KEY,
    exam_id       INT NOT NULL,
    question_text TEXT NOT NULL,
    points        INT DEFAULT 1,
    FOREIGN KEY (exam_id) REFERENCES exams(exam_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS exam_options (
    option_id    INT AUTO_INCREMENT PRIMARY KEY,
    question_id  INT NOT NULL,
    option_text  TEXT NOT NULL,
    is_correct   TINYINT(1) DEFAULT 0,
    FOREIGN KEY (question_id) REFERENCES exam_questions(question_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS exam_results (
    result_id   INT AUTO_INCREMENT PRIMARY KEY,
    exam_id     INT NOT NULL,
    student_id  INT NOT NULL,
    score       DECIMAL(5,2) DEFAULT 0,
    answers     JSON,
    started_at  DATETIME,
    finished_at DATETIME,
    FOREIGN KEY (exam_id)    REFERENCES exams(exam_id)       ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    UNIQUE KEY unique_attempt (exam_id, student_id)
);

-- 14. Сургалтын төлбөрийн хүснэгт
CREATE TABLE IF NOT EXISTS tuition (
    tuition_id     INT AUTO_INCREMENT PRIMARY KEY,
    student_id     INT NOT NULL,
    amount         DECIMAL(12,2) NOT NULL,
    payment_method VARCHAR(50),
    paid_date      DATE,
    due_date       DATE NOT NULL,
    status         VARCHAR(20) DEFAULT 'unpaid',
    receipt_no     VARCHAR(50) UNIQUE,
    recorded_by    INT,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id)  REFERENCES students(student_id),
    FOREIGN KEY (recorded_by) REFERENCES users(user_id)
);

-- 15. Мессеж хүснэгт
CREATE TABLE IF NOT EXISTS messages (
    message_id  INT AUTO_INCREMENT PRIMARY KEY,
    sender_id   INT NOT NULL,
    receiver_id INT NOT NULL,
    content     TEXT NOT NULL,
    is_read     TINYINT(1) DEFAULT 0,
    sent_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id)   REFERENCES users(user_id),
    FOREIGN KEY (receiver_id) REFERENCES users(user_id)
);

-- 16. Системийн audit log
CREATE TABLE IF NOT EXISTS audit_log (
    log_id     INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT,
    action     VARCHAR(100) NOT NULL,
    target_id  INT,
    detail     VARCHAR(255),
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- 17. Зарлалын хүснэгт
CREATE TABLE IF NOT EXISTS announcements (
    ann_id      INT AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(255) NOT NULL,
    body        TEXT NOT NULL,
    pinned      TINYINT(1) DEFAULT 0,
    created_by  INT,
    image_url   VARCHAR(500),
    target_audience VARCHAR(50) DEFAULT 'all',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- 18. Багшийн тэмдэглэлийн хүснэгт
CREATE TABLE IF NOT EXISTS student_remarks (
    remark_id   INT AUTO_INCREMENT PRIMARY KEY,
    student_id  INT NOT NULL,
    teacher_id  INT,
    remark_type VARCHAR(30) DEFAULT 'general',
    content     TEXT NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE SET NULL
);

-- 19. Санал хүсэлтийн тикет хүснэгт
CREATE TABLE IF NOT EXISTS feedback (
    feedback_id  INT AUTO_INCREMENT PRIMARY KEY,
    sender_id    INT NOT NULL,
    receiver_id  INT,
    subject      VARCHAR(255) NOT NULL,
    body         TEXT NOT NULL,
    category     VARCHAR(50) DEFAULT 'general',
    status       VARCHAR(20) DEFAULT 'open',
    priority     VARCHAR(20) DEFAULT 'normal',
    response     TEXT,
    responded_by INT,
    responded_at DATETIME,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id)    REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id)  REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (responded_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- 20. Эрүүл мэндийн бүртгэл
CREATE TABLE IF NOT EXISTS health_records (
    health_id       INT AUTO_INCREMENT PRIMARY KEY,
    student_id      INT NOT NULL,
    record_date     DATE NOT NULL,
    height_cm       DECIMAL(5,1),
    weight_kg       DECIMAL(5,2),
    blood_type      VARCHAR(5),
    vision_left     VARCHAR(20),
    vision_right    VARCHAR(20),
    allergies       TEXT,
    chronic_illness TEXT,
    emergency_contact VARCHAR(100),
    emergency_phone VARCHAR(20),
    notes           TEXT,
    recorded_by     INT,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id)  REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- 21. Вакцинжуулалтын бүртгэл
CREATE TABLE IF NOT EXISTS vaccinations (
    vax_id      INT AUTO_INCREMENT PRIMARY KEY,
    student_id  INT NOT NULL,
    vaccine_name VARCHAR(150) NOT NULL,
    given_date  DATE NOT NULL,
    next_due    DATE,
    notes       VARCHAR(255),
    recorded_by INT,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id)  REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- 22. Номын сан (library_books)
CREATE TABLE IF NOT EXISTS library_books (
    book_id     INT AUTO_INCREMENT PRIMARY KEY,
    isbn        VARCHAR(30) UNIQUE,
    title       VARCHAR(255) NOT NULL,
    author      VARCHAR(255),
    publisher   VARCHAR(200),
    year        YEAR,
    category    VARCHAR(100),
    total_copies INT DEFAULT 1,
    available   INT DEFAULT 1,
    cover_url   VARCHAR(500),
    description TEXT,
    added_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 23. Номын сангийн зээл (library_loans)
CREATE TABLE IF NOT EXISTS library_loans (
    loan_id     INT AUTO_INCREMENT PRIMARY KEY,
    book_id     INT NOT NULL,
    borrower_id INT NOT NULL,
    loan_date   DATE NOT NULL,
    due_date    DATE NOT NULL,
    return_date DATE,
    status      VARCHAR(20) DEFAULT 'borrowed',
    fine_amount DECIMAL(10,2) DEFAULT 0,
    notes       VARCHAR(255),
    recorded_by INT,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id)     REFERENCES library_books(book_id) ON DELETE CASCADE,
    FOREIGN KEY (borrower_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- 24. Үйл ажиллагааны календар (events)
CREATE TABLE IF NOT EXISTS events (
    event_id    INT AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(255) NOT NULL,
    description TEXT,
    event_type  VARCHAR(50) DEFAULT 'general',
    start_date  DATETIME NOT NULL,
    end_date    DATETIME,
    location    VARCHAR(200),
    target_audience VARCHAR(50) DEFAULT 'all',
    class_id    INT,
    color       VARCHAR(20) DEFAULT '#3b82f6',
    created_by  INT,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id)   REFERENCES classes(class_id)  ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(user_id)     ON DELETE SET NULL
);

-- 25. Чөлөөний хүсэлт
CREATE TABLE IF NOT EXISTS leave_requests (
    leave_id    INT AUTO_INCREMENT PRIMARY KEY,
    student_id  INT NOT NULL,
    request_by  INT NOT NULL,
    start_date  DATE NOT NULL,
    end_date    DATE NOT NULL,
    reason      TEXT NOT NULL,
    leave_type  VARCHAR(30) DEFAULT 'sick',
    status      VARCHAR(20) DEFAULT 'pending',
    reviewed_by INT,
    review_note VARCHAR(255),
    reviewed_at DATETIME,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id)  REFERENCES students(student_id)  ON DELETE CASCADE,
    FOREIGN KEY (request_by)  REFERENCES users(user_id)        ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id)        ON DELETE SET NULL
);

-- Индексүүд
CREATE INDEX idx_class_active ON students(class_id, is_active);
CREATE INDEX idx_stu_name ON students(last_name, first_name);
CREATE INDEX idx_student_date ON attendance(student_id, date);
CREATE INDEX idx_gr_student_subject ON grades(student_id, subject_id);
CREATE INDEX idx_ann_audience ON announcements(target_audience);
CREATE INDEX idx_rmk_student ON student_remarks(student_id);
CREATE INDEX idx_rmk_teacher ON student_remarks(teacher_id);
CREATE INDEX idx_fb_sender ON feedback(sender_id);
CREATE INDEX idx_hlth_student ON health_records(student_id);
CREATE INDEX idx_vax_student ON vaccinations(student_id);
CREATE INDEX idx_bk_title ON library_books(title);
CREATE INDEX idx_ln_borrower ON library_loans(borrower_id);
CREATE INDEX idx_ev_start ON events(start_date);
CREATE INDEX idx_lv_student ON leave_requests(student_id);
CREATE INDEX idx_gr_student_id ON grades(student_id);
CREATE INDEX idx_gr_subject_id ON grades(subject_id);
CREATE INDEX idx_att_date ON attendance(date);
CREATE INDEX idx_msg_receiver_sender ON messages(receiver_id, sender_id);
CREATE INDEX idx_audit_created ON audit_log(created_at);
CREATE INDEX idx_user_api_token ON users(api_token);

-- 22. Ухаалаг хүүхэд авах систем (Smart Pickup)
CREATE TABLE IF NOT EXISTS pickup_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    parent_id INT NOT NULL,
    status VARCHAR(20) DEFAULT 'waiting',
    picker_name VARCHAR(100) DEFAULT 'Эцэг эх',
    picker_relation VARCHAR(50) DEFAULT 'Өөрөө',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- 23. Урамшууллын онооны лог (Merit System)
CREATE TABLE IF NOT EXISTS merit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    teacher_id INT NOT NULL,
    points INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- 24. Хэрэглэгчидийн төхөөрөмжүүдийн бүртгэл (user_devices)
CREATE TABLE IF NOT EXISTS user_devices (
    device_id    INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    device_hash  VARCHAR(64) NOT NULL,
    ip_address   VARCHAR(45),
    user_agent   TEXT,
    last_used_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_device (user_id, device_hash),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ─── Баазын тестийн өгөгдөл (Seed Data) ───────────────────────────────────────────
INSERT IGNORE INTO user_roles (role_id, role_name) VALUES
(1,'admin'),(2,'manager'),(3,'teacher'),(4,'student'),(5,'parent'),(6,'director');

INSERT IGNORE INTO attendance_status (status_id, status_name) VALUES
(1,'present'),(2,'absent'),(3,'sick');

INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) 
VALUES
(1,'admin','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',1,'Систем Админ','admin@school.mn','99001100'),
(2,'manager1','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',2,'Менежер Дорж','manager@school.mn','99001101'),
(3,'teacher1','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',3,'Д.Батбаяр','d.batbayar@school.mn','99001102'),
(4,'teacher2','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',3,'Б.Номин','b.nomin@school.mn','99001103'),
(5,'student1','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'Батбаяр Амар','amarbat@school.mn','99001104'),
(6,'student2','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'Дорж Саран','saran@school.mn','99001105'),
(7,'student3','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'Гантулга Бат','gantulga@school.mn','99001106'),
(8,'parent1','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',5,'Ганзориг Намдаг','parent@school.mn','99001107');

INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone)
VALUES (100, 'director', '$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',
6, 'Захирал', 'director@school.mn', '99000006');

INSERT IGNORE INTO teachers (user_id, last_name, first_name, position, phone, email) 
VALUES
(3,'Д','Батбаяр','Математикийн багш','99001102','d.batbayar@school.mn'),
(4,'Б','Номин','Монгол хэлний багш','99001103','b.nomin@school.mn');

INSERT IGNORE INTO parents (user_id, last_name, first_name, phone, email) 
VALUES
(8,'Ганзориг','Намдаг','99001107','parent@school.mn');

INSERT IGNORE INTO classes (class_id, class_name, academic_year, teacher_id, room) 
VALUES
(1,'10А','2025-2026',3,'201'),
(2,'11Б','2025-2026',4,'305'),
(3,'9В','2025-2026',3,'108');

-- Нэмэлт багш нарын хэрэглэгчийн бүртгэл (Удахгүй болох ангиудад)
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES
(19,'teacher6','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',3,'Ж.Бат-Эрдэнэ','bat@school.mn','99003301'),
(20,'teacher7','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',3,'А.Сайнзаяа','zaya@school.mn','99003302'),
(21,'teacher8','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',3,'Б.Орхон','orkhon@school.mn','99003303'),
(22,'teacher9','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',3,'Д.Наран','naran@school.mn','99003304'),
(23,'teacher10','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',3,'Т.Галан','gal@school.mn','99003305'),
(24,'teacher11','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',3,'Е.Баяр','bayaraa@school.mn','99003306');

INSERT IGNORE INTO teachers (user_id, last_name, first_name, position, phone, email) VALUES
(19,'Ж','Бат-Эрдэнэ','Физик','99003301','bat@school.mn'),
(20,'А','Сайнзаяа','Газар зүй','99003302','zaya@school.mn'),
(21,'Б','Орхон','Түүх','99003303','orkhon@school.mn'),
(22,'Д','Наран','Мэдээлэл зүй','99003304','naran@school.mn'),
(23,'Т','Галдан','Зураг зүй','99003305','gal@school.mn'),
(24,'Е','Баяр','Орос хэл','99003306','bayaraa@school.mn');

-- 1-12 А, Б, В ангиуд
INSERT IGNORE INTO classes (class_id, class_name, academic_year, teacher_id, room) VALUES
(9,'1Б','2025-2026',9,'110'),(10,'1В','2025-2026',10,'111'),
(11,'2А','2025-2026',11,'112'),(12,'2В','2025-2026',3,'113'),
(13,'3Б','2025-2026',4,'114'),(14,'3В','2025-2026',19,'115'),
(15,'4А','2025-2026',20,'210'),(16,'4Б','2025-2026',21,'211'),
(17,'5Б','2025-2026',22,'212'),(18,'5В','2025-2026',23,'213'),
(19,'6А','2025-2026',24,'301'),(20,'6Б','2025-2026',3,'302'),(21,'6В','2025-2026',4,'303'),
(22,'7А','2025-2026',9,'304'),(23,'7Б','2025-2026',10,'305'),(24,'7В','2025-2026',11,'306'),
(25,'8А','2025-2026',19,'401'),(26,'8Б','2025-2026',20,'402'),(27,'8В','2025-2026',21,'403'),
(28,'9А','2025-2026',22,'404'),(29,'9Б','2025-2026',23,'405'),
(30,'10Б','2025-2026',24,'501'),(31,'10В','2025-2026',3,'502'),
(32,'11А','2025-2026',4,'503'),(33,'11В','2025-2026',9,'504'),
(34,'12А','2025-2026',10,'601'),(35,'12Б','2025-2026',11,'602'),(36,'12В','2025-2026',19,'603');

-- ҮНДСЭН СУРАГЧИД (10 хүүхэд анги бүрт — зарим ангиудад туршилт болгож олноор нэмэв)
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name) VALUES
(101,'stu_1a_1','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'Б.Ариунболд'),(102,'stu_1a_2','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'Г.Мөнх-Эрдэнэ'),
(103,'stu_1a_3','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'Д.Цэцэгээ'),(104,'stu_1a_4','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'Ж.Тэмүүлэн'),
(105,'stu_1a_5','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'С.Марал'),(106,'stu_1a_6','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'О.Энх-Уянга'),
(107,'stu_1a_7','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'Т.Батбаяр'),(108,'stu_1a_8','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'Л.Номин'),
(109,'stu_1a_9','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'М.Алтан'),(110,'stu_1a_10','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'Н.Баярмаа');

INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id, merit_points) VALUES
(101,101,'Б','Ариунболд',1, 40),(102,102,'Г','Мөнх-Эрдэнэ',1, 55),(103,103,'Д','Цэцэгээ',1, 90),
(104,104,'Ж','Тэмүүлэн',1, 12),(105,105,'С','Марал',1, 75),(106,106,'О','Энх-Уянга',1, 60),
(107,107,'Т','Батбаяр',1, 35),(108,108,'Л','Номин',1, 88),(109,109,'М','Алтан',1, 20),(110,110,'Н','Баярмаа',1, 50);

-- Анги 4 (1А) — 10 хүүхэд
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name) VALUES
(201,'stu_4_1','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'А.Ганзориг'),(202,'stu_4_2','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'Б.Сарнай'),
(203,'stu_4_3','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'Г.Төгөлдөр'),(204,'stu_4_4','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'Д.Уянга'),
(205,'stu_4_5','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'Е.Хишигэ'),(206,'stu_4_6','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'Ж.Золбоо'),
(207,'stu_4_7','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'З.Ивээл'),(208,'stu_4_8','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'И.Цолмон'),
(209,'stu_4_9','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'К.Түмэн'),(210,'stu_4_10','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'Л.Болор');

INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id, merit_points) VALUES
(201,201,'А','Ганзориг',4, 15),(202,202,'Б','Сарнай',4, 85),(203,203,'Г','Төгөлдөр',4, 44),
(204,204,'Д','Уянга',4, 62),(205,205,'Е','Хишигэ',4, 30),(206,206,'Ж','Золбоо',4, 51),
(207,207,'З','Ивээл',4, 95),(208,208,'И','Цолмон',4, 18),(209,209,'К','Түмэн',4, 73),(210,210,'Л','Болор',4, 40);

-- Анги 5 (2Б) — 10 хүүхэд
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name) VALUES
(301,'stu_5_1','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'М.Сүхбат'),(302,'stu_5_2','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'Н.Туяа'),
(303,'stu_5_3','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'О.Амар'),(304,'stu_5_4','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'П.Энэрэл'),
(305,'stu_5_5','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'Р.Сайнбилэг'),(306,'stu_5_6','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'С.Оргил'),
(307,'stu_5_7','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'Т.Ану'),(308,'stu_5_8','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'У.Батхүү'),
(309,'stu_5_9','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'Ф.Гэрэл'),(310,'stu_5_10','$2y$10$66.pqaF0xmQw7A4IH9q7wOagVUPcad38HBKUVP5DOn4/B6rmTru2e',4,'Х.Цогт');

INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id, merit_points) VALUES
(301,301,'М','Сүхбат',5, 22),(302,302,'Н','Туяа',5, 77),(303,303,'О','Амар',5, 61),
(304,304,'П','Энэрэл',5, 45),(305,305,'Р','Сайнбилэг',5, 99),(306,306,'С','Оргил',5, 12),
(307,307,'Т','Ану',5, 130),(308,308,'У','Батхүү',5, 54),(309,309,'Ф','Гэрэл',5, 82),(310,310,'Х','Цогт',5, 33);

-- Бусад ангиудад тус бүр 2-3 сурагч нэмэх (Бүгдийг нь нэмбэл файл хэтэрхий том болно)
-- Энэ нь систем ажиллаж буйг харуулахад хангалттай.

INSERT IGNORE INTO subjects (subject_id, subject_name, class_id, teacher_id) VALUES
(6,'Физик',19,19),(7,'Газар зүй',22,20),(8,'Түүх',25,21),(9,'Мэдээлэл зүй',28,22);

INSERT IGNORE INTO student_remarks (remark_id, student_id, teacher_id, remark_type, content) 
VALUES
(1, 1, 1, 'academic',  'Амарын математикийн дүн маш сайн байна. Цааш ч тэгж хичээгээрэй.'),
(2, 2, 1, 'behavior',  'Хичээлийн цагт анхааралтай сонсоорой.');

-- 26. Системийн Тохиргоо (System Settings)
CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES 
('school_name', 'Цахим Сургууль'),
('school_address', 'Улаанбаатар хот'),
('contact_email', 'info@school.edu.mn'),
('contact_phone', '9911XXXX'),
('academic_year', '2025-2026'),
('semester', '1');

-- 27. Хүлээгдэж буй бүртгэлүүд (Pending Registrations)
CREATE TABLE IF NOT EXISTS pending_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    register_no VARCHAR(20) NULL,
    gender VARCHAR(10) NULL,
    birth_date DATE NULL,
    class_id INT NULL,
    student_register_no VARCHAR(20) NULL,
    phone VARCHAR(20) NULL,
    email VARCHAR(100) NULL,
    address VARCHAR(255) NULL,
    status VARCHAR(20) DEFAULT 'pending',
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES user_roles(role_id),
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (class_id) REFERENCES classes(class_id) ON DELETE SET NULL
);


-- ==========================================
-- ТУРШИЛТЫН 200+ ӨГӨГДӨЛ (SEED DATA)
-- ==========================================

-- Хэрэглэгчид (Багш нар)
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (101, 'teacher1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'Багш 1', 'teacher1@test.mn', '9911001');
INSERT IGNORE INTO teachers (user_id, last_name, first_name) VALUES (101, 'Овог1', 'Багш1');
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (102, 'teacher2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'Багш 2', 'teacher2@test.mn', '9911002');
INSERT IGNORE INTO teachers (user_id, last_name, first_name) VALUES (102, 'Овог2', 'Багш2');
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (103, 'teacher3', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'Багш 3', 'teacher3@test.mn', '9911003');
INSERT IGNORE INTO teachers (user_id, last_name, first_name) VALUES (103, 'Овог3', 'Багш3');
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (104, 'teacher4', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'Багш 4', 'teacher4@test.mn', '9911004');
INSERT IGNORE INTO teachers (user_id, last_name, first_name) VALUES (104, 'Овог4', 'Багш4');
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (105, 'teacher5', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'Багш 5', 'teacher5@test.mn', '9911005');
INSERT IGNORE INTO teachers (user_id, last_name, first_name) VALUES (105, 'Овог5', 'Багш5');
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (106, 'teacher6', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'Багш 6', 'teacher6@test.mn', '9911006');
INSERT IGNORE INTO teachers (user_id, last_name, first_name) VALUES (106, 'Овог6', 'Багш6');
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (107, 'teacher7', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'Багш 7', 'teacher7@test.mn', '9911007');
INSERT IGNORE INTO teachers (user_id, last_name, first_name) VALUES (107, 'Овог7', 'Багш7');
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (108, 'teacher8', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'Багш 8', 'teacher8@test.mn', '9911008');
INSERT IGNORE INTO teachers (user_id, last_name, first_name) VALUES (108, 'Овог8', 'Багш8');
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (109, 'teacher9', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'Багш 9', 'teacher9@test.mn', '9911009');
INSERT IGNORE INTO teachers (user_id, last_name, first_name) VALUES (109, 'Овог9', 'Багш9');
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (1010, 'teacher10', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'Багш 10', 'teacher10@test.mn', '99110010');
INSERT IGNORE INTO teachers (user_id, last_name, first_name) VALUES (1010, 'Овог10', 'Багш10');

-- Ангиуд болон Хичээлүүд
INSERT IGNORE INTO classes (class_id, class_name, academic_year, teacher_id) VALUES (1, '10-1', '2025-2026', 101);
INSERT IGNORE INTO subjects (subject_id, subject_name, class_id) VALUES (1, 'Хичээл 1', 1);
INSERT IGNORE INTO classes (class_id, class_name, academic_year, teacher_id) VALUES (2, '10-2', '2025-2026', 102);
INSERT IGNORE INTO subjects (subject_id, subject_name, class_id) VALUES (2, 'Хичээл 2', 2);
INSERT IGNORE INTO classes (class_id, class_name, academic_year, teacher_id) VALUES (3, '10-3', '2025-2026', 103);
INSERT IGNORE INTO subjects (subject_id, subject_name, class_id) VALUES (3, 'Хичээл 3', 3);
INSERT IGNORE INTO classes (class_id, class_name, academic_year, teacher_id) VALUES (4, '10-4', '2025-2026', 104);
INSERT IGNORE INTO subjects (subject_id, subject_name, class_id) VALUES (4, 'Хичээл 4', 4);
INSERT IGNORE INTO classes (class_id, class_name, academic_year, teacher_id) VALUES (5, '10-5', '2025-2026', 105);
INSERT IGNORE INTO subjects (subject_id, subject_name, class_id) VALUES (5, 'Хичээл 5', 5);
INSERT IGNORE INTO classes (class_id, class_name, academic_year, teacher_id) VALUES (6, '10-6', '2025-2026', 106);
INSERT IGNORE INTO subjects (subject_id, subject_name, class_id) VALUES (6, 'Хичээл 6', 6);
INSERT IGNORE INTO classes (class_id, class_name, academic_year, teacher_id) VALUES (7, '10-7', '2025-2026', 107);
INSERT IGNORE INTO subjects (subject_id, subject_name, class_id) VALUES (7, 'Хичээл 7', 7);
INSERT IGNORE INTO classes (class_id, class_name, academic_year, teacher_id) VALUES (8, '10-8', '2025-2026', 108);
INSERT IGNORE INTO subjects (subject_id, subject_name, class_id) VALUES (8, 'Хичээл 8', 8);
INSERT IGNORE INTO classes (class_id, class_name, academic_year, teacher_id) VALUES (9, '10-9', '2025-2026', 109);
INSERT IGNORE INTO subjects (subject_id, subject_name, class_id) VALUES (9, 'Хичээл 9', 9);
INSERT IGNORE INTO classes (class_id, class_name, academic_year, teacher_id) VALUES (10, '10-10', '2025-2026', 1010);
INSERT IGNORE INTO subjects (subject_id, subject_name, class_id) VALUES (10, 'Хичээл 10', 10);

-- Хэрэглэгчид (Сурагчид)
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2001, 'student1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 1', 'student1@test.mn', '88110001');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (1, 2001, 'Овог1', 'Сурагч1', 1);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (1, 1, 100, 1, 101);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (1, 1, CURDATE(), 1, 101);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2002, 'student2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 2', 'student2@test.mn', '88110002');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (2, 2002, 'Овог2', 'Сурагч2', 1);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (2, 1, 89, 1, 101);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (2, 1, CURDATE(), 1, 101);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2003, 'student3', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 3', 'student3@test.mn', '88110003');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (3, 2003, 'Овог3', 'Сурагч3', 1);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (3, 1, 72, 1, 101);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (3, 1, CURDATE(), 2, 101);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2004, 'student4', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 4', 'student4@test.mn', '88110004');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (4, 2004, 'Овог4', 'Сурагч4', 1);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (4, 1, 84, 1, 101);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (4, 1, CURDATE(), 1, 101);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2005, 'student5', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 5', 'student5@test.mn', '88110005');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (5, 2005, 'Овог5', 'Сурагч5', 1);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (5, 1, 74, 1, 101);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (5, 1, CURDATE(), 1, 101);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2006, 'student6', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 6', 'student6@test.mn', '88110006');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (6, 2006, 'Овог6', 'Сурагч6', 1);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (6, 1, 83, 1, 101);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (6, 1, CURDATE(), 1, 101);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2007, 'student7', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 7', 'student7@test.mn', '88110007');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (7, 2007, 'Овог7', 'Сурагч7', 1);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (7, 1, 95, 1, 101);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (7, 1, CURDATE(), 1, 101);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2008, 'student8', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 8', 'student8@test.mn', '88110008');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (8, 2008, 'Овог8', 'Сурагч8', 1);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (8, 1, 60, 1, 101);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (8, 1, CURDATE(), 1, 101);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2009, 'student9', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 9', 'student9@test.mn', '88110009');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (9, 2009, 'Овог9', 'Сурагч9', 1);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (9, 1, 79, 1, 101);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (9, 1, CURDATE(), 1, 101);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2010, 'student10', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 10', 'student10@test.mn', '88110010');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (10, 2010, 'Овог10', 'Сурагч10', 1);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (10, 1, 69, 1, 101);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (10, 1, CURDATE(), 1, 101);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2011, 'student11', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 11', 'student11@test.mn', '88110011');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (11, 2011, 'Овог11', 'Сурагч11', 1);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (11, 1, 86, 1, 101);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (11, 1, CURDATE(), 1, 101);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2012, 'student12', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 12', 'student12@test.mn', '88110012');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (12, 2012, 'Овог12', 'Сурагч12', 1);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (12, 1, 93, 1, 101);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (12, 1, CURDATE(), 1, 101);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2013, 'student13', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 13', 'student13@test.mn', '88110013');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (13, 2013, 'Овог13', 'Сурагч13', 1);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (13, 1, 78, 1, 101);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (13, 1, CURDATE(), 1, 101);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2014, 'student14', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 14', 'student14@test.mn', '88110014');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (14, 2014, 'Овог14', 'Сурагч14', 1);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (14, 1, 74, 1, 101);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (14, 1, CURDATE(), 1, 101);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2015, 'student15', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 15', 'student15@test.mn', '88110015');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (15, 2015, 'Овог15', 'Сурагч15', 1);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (15, 1, 90, 1, 101);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (15, 1, CURDATE(), 1, 101);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2016, 'student16', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 16', 'student16@test.mn', '88110016');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (16, 2016, 'Овог16', 'Сурагч16', 1);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (16, 1, 90, 1, 101);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (16, 1, CURDATE(), 2, 101);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2017, 'student17', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 17', 'student17@test.mn', '88110017');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (17, 2017, 'Овог17', 'Сурагч17', 1);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (17, 1, 64, 1, 101);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (17, 1, CURDATE(), 1, 101);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2018, 'student18', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 18', 'student18@test.mn', '88110018');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (18, 2018, 'Овог18', 'Сурагч18', 1);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (18, 1, 90, 1, 101);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (18, 1, CURDATE(), 1, 101);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2019, 'student19', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 19', 'student19@test.mn', '88110019');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (19, 2019, 'Овог19', 'Сурагч19', 1);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (19, 1, 87, 1, 101);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (19, 1, CURDATE(), 1, 101);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2020, 'student20', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 20', 'student20@test.mn', '88110020');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (20, 2020, 'Овог20', 'Сурагч20', 1);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (20, 1, 98, 1, 101);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (20, 1, CURDATE(), 1, 101);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2021, 'student21', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 21', 'student21@test.mn', '88110021');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (21, 2021, 'Овог21', 'Сурагч21', 2);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (21, 2, 98, 1, 102);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (21, 2, CURDATE(), 1, 102);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2022, 'student22', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 22', 'student22@test.mn', '88110022');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (22, 2022, 'Овог22', 'Сурагч22', 2);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (22, 2, 92, 1, 102);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (22, 2, CURDATE(), 1, 102);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2023, 'student23', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 23', 'student23@test.mn', '88110023');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (23, 2023, 'Овог23', 'Сурагч23', 2);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (23, 2, 83, 1, 102);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (23, 2, CURDATE(), 1, 102);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2024, 'student24', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 24', 'student24@test.mn', '88110024');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (24, 2024, 'Овог24', 'Сурагч24', 2);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (24, 2, 66, 1, 102);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (24, 2, CURDATE(), 1, 102);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2025, 'student25', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 25', 'student25@test.mn', '88110025');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (25, 2025, 'Овог25', 'Сурагч25', 2);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (25, 2, 91, 1, 102);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (25, 2, CURDATE(), 1, 102);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2026, 'student26', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 26', 'student26@test.mn', '88110026');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (26, 2026, 'Овог26', 'Сурагч26', 2);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (26, 2, 92, 1, 102);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (26, 2, CURDATE(), 1, 102);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2027, 'student27', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 27', 'student27@test.mn', '88110027');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (27, 2027, 'Овог27', 'Сурагч27', 2);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (27, 2, 64, 1, 102);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (27, 2, CURDATE(), 1, 102);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2028, 'student28', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 28', 'student28@test.mn', '88110028');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (28, 2028, 'Овог28', 'Сурагч28', 2);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (28, 2, 66, 1, 102);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (28, 2, CURDATE(), 1, 102);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2029, 'student29', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 29', 'student29@test.mn', '88110029');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (29, 2029, 'Овог29', 'Сурагч29', 2);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (29, 2, 62, 1, 102);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (29, 2, CURDATE(), 1, 102);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2030, 'student30', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 30', 'student30@test.mn', '88110030');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (30, 2030, 'Овог30', 'Сурагч30', 2);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (30, 2, 61, 1, 102);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (30, 2, CURDATE(), 1, 102);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2031, 'student31', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 31', 'student31@test.mn', '88110031');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (31, 2031, 'Овог31', 'Сурагч31', 2);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (31, 2, 91, 1, 102);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (31, 2, CURDATE(), 1, 102);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2032, 'student32', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 32', 'student32@test.mn', '88110032');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (32, 2032, 'Овог32', 'Сурагч32', 2);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (32, 2, 74, 1, 102);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (32, 2, CURDATE(), 1, 102);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2033, 'student33', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 33', 'student33@test.mn', '88110033');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (33, 2033, 'Овог33', 'Сурагч33', 2);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (33, 2, 64, 1, 102);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (33, 2, CURDATE(), 1, 102);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2034, 'student34', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 34', 'student34@test.mn', '88110034');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (34, 2034, 'Овог34', 'Сурагч34', 2);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (34, 2, 67, 1, 102);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (34, 2, CURDATE(), 2, 102);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2035, 'student35', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 35', 'student35@test.mn', '88110035');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (35, 2035, 'Овог35', 'Сурагч35', 2);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (35, 2, 65, 1, 102);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (35, 2, CURDATE(), 1, 102);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2036, 'student36', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 36', 'student36@test.mn', '88110036');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (36, 2036, 'Овог36', 'Сурагч36', 2);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (36, 2, 64, 1, 102);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (36, 2, CURDATE(), 1, 102);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2037, 'student37', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 37', 'student37@test.mn', '88110037');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (37, 2037, 'Овог37', 'Сурагч37', 2);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (37, 2, 72, 1, 102);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (37, 2, CURDATE(), 1, 102);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2038, 'student38', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 38', 'student38@test.mn', '88110038');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (38, 2038, 'Овог38', 'Сурагч38', 2);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (38, 2, 64, 1, 102);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (38, 2, CURDATE(), 1, 102);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2039, 'student39', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 39', 'student39@test.mn', '88110039');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (39, 2039, 'Овог39', 'Сурагч39', 2);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (39, 2, 76, 1, 102);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (39, 2, CURDATE(), 1, 102);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2040, 'student40', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 40', 'student40@test.mn', '88110040');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (40, 2040, 'Овог40', 'Сурагч40', 2);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (40, 2, 89, 1, 102);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (40, 2, CURDATE(), 1, 102);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2041, 'student41', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 41', 'student41@test.mn', '88110041');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (41, 2041, 'Овог41', 'Сурагч41', 3);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (41, 3, 83, 1, 103);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (41, 3, CURDATE(), 2, 103);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2042, 'student42', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 42', 'student42@test.mn', '88110042');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (42, 2042, 'Овог42', 'Сурагч42', 3);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (42, 3, 96, 1, 103);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (42, 3, CURDATE(), 1, 103);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2043, 'student43', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 43', 'student43@test.mn', '88110043');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (43, 2043, 'Овог43', 'Сурагч43', 3);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (43, 3, 86, 1, 103);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (43, 3, CURDATE(), 1, 103);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2044, 'student44', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 44', 'student44@test.mn', '88110044');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (44, 2044, 'Овог44', 'Сурагч44', 3);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (44, 3, 67, 1, 103);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (44, 3, CURDATE(), 1, 103);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2045, 'student45', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 45', 'student45@test.mn', '88110045');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (45, 2045, 'Овог45', 'Сурагч45', 3);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (45, 3, 79, 1, 103);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (45, 3, CURDATE(), 1, 103);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2046, 'student46', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 46', 'student46@test.mn', '88110046');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (46, 2046, 'Овог46', 'Сурагч46', 3);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (46, 3, 71, 1, 103);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (46, 3, CURDATE(), 1, 103);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2047, 'student47', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 47', 'student47@test.mn', '88110047');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (47, 2047, 'Овог47', 'Сурагч47', 3);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (47, 3, 100, 1, 103);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (47, 3, CURDATE(), 1, 103);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2048, 'student48', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 48', 'student48@test.mn', '88110048');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (48, 2048, 'Овог48', 'Сурагч48', 3);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (48, 3, 91, 1, 103);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (48, 3, CURDATE(), 1, 103);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2049, 'student49', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 49', 'student49@test.mn', '88110049');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (49, 2049, 'Овог49', 'Сурагч49', 3);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (49, 3, 61, 1, 103);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (49, 3, CURDATE(), 1, 103);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2050, 'student50', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 50', 'student50@test.mn', '88110050');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (50, 2050, 'Овог50', 'Сурагч50', 3);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (50, 3, 67, 1, 103);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (50, 3, CURDATE(), 1, 103);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2051, 'student51', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 51', 'student51@test.mn', '88110051');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (51, 2051, 'Овог51', 'Сурагч51', 3);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (51, 3, 85, 1, 103);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (51, 3, CURDATE(), 1, 103);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2052, 'student52', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 52', 'student52@test.mn', '88110052');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (52, 2052, 'Овог52', 'Сурагч52', 3);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (52, 3, 89, 1, 103);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (52, 3, CURDATE(), 1, 103);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2053, 'student53', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 53', 'student53@test.mn', '88110053');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (53, 2053, 'Овог53', 'Сурагч53', 3);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (53, 3, 90, 1, 103);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (53, 3, CURDATE(), 1, 103);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2054, 'student54', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 54', 'student54@test.mn', '88110054');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (54, 2054, 'Овог54', 'Сурагч54', 3);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (54, 3, 69, 1, 103);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (54, 3, CURDATE(), 1, 103);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2055, 'student55', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 55', 'student55@test.mn', '88110055');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (55, 2055, 'Овог55', 'Сурагч55', 3);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (55, 3, 91, 1, 103);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (55, 3, CURDATE(), 1, 103);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2056, 'student56', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 56', 'student56@test.mn', '88110056');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (56, 2056, 'Овог56', 'Сурагч56', 3);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (56, 3, 86, 1, 103);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (56, 3, CURDATE(), 2, 103);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2057, 'student57', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 57', 'student57@test.mn', '88110057');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (57, 2057, 'Овог57', 'Сурагч57', 3);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (57, 3, 98, 1, 103);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (57, 3, CURDATE(), 1, 103);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2058, 'student58', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 58', 'student58@test.mn', '88110058');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (58, 2058, 'Овог58', 'Сурагч58', 3);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (58, 3, 97, 1, 103);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (58, 3, CURDATE(), 2, 103);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2059, 'student59', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 59', 'student59@test.mn', '88110059');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (59, 2059, 'Овог59', 'Сурагч59', 3);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (59, 3, 64, 1, 103);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (59, 3, CURDATE(), 1, 103);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2060, 'student60', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 60', 'student60@test.mn', '88110060');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (60, 2060, 'Овог60', 'Сурагч60', 3);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (60, 3, 81, 1, 103);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (60, 3, CURDATE(), 2, 103);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2061, 'student61', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 61', 'student61@test.mn', '88110061');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (61, 2061, 'Овог61', 'Сурагч61', 4);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (61, 4, 92, 1, 104);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (61, 4, CURDATE(), 1, 104);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2062, 'student62', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 62', 'student62@test.mn', '88110062');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (62, 2062, 'Овог62', 'Сурагч62', 4);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (62, 4, 86, 1, 104);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (62, 4, CURDATE(), 1, 104);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2063, 'student63', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 63', 'student63@test.mn', '88110063');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (63, 2063, 'Овог63', 'Сурагч63', 4);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (63, 4, 91, 1, 104);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (63, 4, CURDATE(), 1, 104);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2064, 'student64', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 64', 'student64@test.mn', '88110064');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (64, 2064, 'Овог64', 'Сурагч64', 4);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (64, 4, 61, 1, 104);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (64, 4, CURDATE(), 2, 104);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2065, 'student65', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 65', 'student65@test.mn', '88110065');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (65, 2065, 'Овог65', 'Сурагч65', 4);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (65, 4, 67, 1, 104);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (65, 4, CURDATE(), 1, 104);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2066, 'student66', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 66', 'student66@test.mn', '88110066');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (66, 2066, 'Овог66', 'Сурагч66', 4);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (66, 4, 99, 1, 104);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (66, 4, CURDATE(), 2, 104);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2067, 'student67', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 67', 'student67@test.mn', '88110067');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (67, 2067, 'Овог67', 'Сурагч67', 4);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (67, 4, 63, 1, 104);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (67, 4, CURDATE(), 1, 104);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2068, 'student68', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 68', 'student68@test.mn', '88110068');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (68, 2068, 'Овог68', 'Сурагч68', 4);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (68, 4, 95, 1, 104);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (68, 4, CURDATE(), 1, 104);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2069, 'student69', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 69', 'student69@test.mn', '88110069');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (69, 2069, 'Овог69', 'Сурагч69', 4);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (69, 4, 74, 1, 104);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (69, 4, CURDATE(), 2, 104);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2070, 'student70', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 70', 'student70@test.mn', '88110070');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (70, 2070, 'Овог70', 'Сурагч70', 4);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (70, 4, 63, 1, 104);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (70, 4, CURDATE(), 1, 104);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2071, 'student71', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 71', 'student71@test.mn', '88110071');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (71, 2071, 'Овог71', 'Сурагч71', 4);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (71, 4, 95, 1, 104);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (71, 4, CURDATE(), 1, 104);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2072, 'student72', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 72', 'student72@test.mn', '88110072');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (72, 2072, 'Овог72', 'Сурагч72', 4);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (72, 4, 82, 1, 104);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (72, 4, CURDATE(), 1, 104);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2073, 'student73', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 73', 'student73@test.mn', '88110073');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (73, 2073, 'Овог73', 'Сурагч73', 4);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (73, 4, 89, 1, 104);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (73, 4, CURDATE(), 1, 104);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2074, 'student74', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 74', 'student74@test.mn', '88110074');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (74, 2074, 'Овог74', 'Сурагч74', 4);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (74, 4, 84, 1, 104);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (74, 4, CURDATE(), 1, 104);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2075, 'student75', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 75', 'student75@test.mn', '88110075');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (75, 2075, 'Овог75', 'Сурагч75', 4);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (75, 4, 64, 1, 104);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (75, 4, CURDATE(), 1, 104);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2076, 'student76', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 76', 'student76@test.mn', '88110076');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (76, 2076, 'Овог76', 'Сурагч76', 4);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (76, 4, 70, 1, 104);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (76, 4, CURDATE(), 1, 104);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2077, 'student77', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 77', 'student77@test.mn', '88110077');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (77, 2077, 'Овог77', 'Сурагч77', 4);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (77, 4, 100, 1, 104);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (77, 4, CURDATE(), 1, 104);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2078, 'student78', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 78', 'student78@test.mn', '88110078');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (78, 2078, 'Овог78', 'Сурагч78', 4);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (78, 4, 86, 1, 104);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (78, 4, CURDATE(), 2, 104);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2079, 'student79', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 79', 'student79@test.mn', '88110079');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (79, 2079, 'Овог79', 'Сурагч79', 4);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (79, 4, 62, 1, 104);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (79, 4, CURDATE(), 1, 104);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2080, 'student80', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 80', 'student80@test.mn', '88110080');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (80, 2080, 'Овог80', 'Сурагч80', 4);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (80, 4, 65, 1, 104);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (80, 4, CURDATE(), 1, 104);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2081, 'student81', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 81', 'student81@test.mn', '88110081');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (81, 2081, 'Овог81', 'Сурагч81', 5);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (81, 5, 79, 1, 105);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (81, 5, CURDATE(), 2, 105);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2082, 'student82', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 82', 'student82@test.mn', '88110082');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (82, 2082, 'Овог82', 'Сурагч82', 5);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (82, 5, 61, 1, 105);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (82, 5, CURDATE(), 2, 105);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2083, 'student83', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 83', 'student83@test.mn', '88110083');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (83, 2083, 'Овог83', 'Сурагч83', 5);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (83, 5, 86, 1, 105);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (83, 5, CURDATE(), 1, 105);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2084, 'student84', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 84', 'student84@test.mn', '88110084');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (84, 2084, 'Овог84', 'Сурагч84', 5);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (84, 5, 77, 1, 105);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (84, 5, CURDATE(), 2, 105);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2085, 'student85', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 85', 'student85@test.mn', '88110085');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (85, 2085, 'Овог85', 'Сурагч85', 5);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (85, 5, 83, 1, 105);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (85, 5, CURDATE(), 2, 105);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2086, 'student86', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 86', 'student86@test.mn', '88110086');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (86, 2086, 'Овог86', 'Сурагч86', 5);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (86, 5, 69, 1, 105);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (86, 5, CURDATE(), 2, 105);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2087, 'student87', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 87', 'student87@test.mn', '88110087');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (87, 2087, 'Овог87', 'Сурагч87', 5);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (87, 5, 70, 1, 105);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (87, 5, CURDATE(), 1, 105);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2088, 'student88', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 88', 'student88@test.mn', '88110088');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (88, 2088, 'Овог88', 'Сурагч88', 5);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (88, 5, 79, 1, 105);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (88, 5, CURDATE(), 1, 105);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2089, 'student89', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 89', 'student89@test.mn', '88110089');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (89, 2089, 'Овог89', 'Сурагч89', 5);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (89, 5, 72, 1, 105);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (89, 5, CURDATE(), 1, 105);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2090, 'student90', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 90', 'student90@test.mn', '88110090');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (90, 2090, 'Овог90', 'Сурагч90', 5);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (90, 5, 62, 1, 105);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (90, 5, CURDATE(), 1, 105);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2091, 'student91', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 91', 'student91@test.mn', '88110091');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (91, 2091, 'Овог91', 'Сурагч91', 5);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (91, 5, 94, 1, 105);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (91, 5, CURDATE(), 1, 105);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2092, 'student92', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 92', 'student92@test.mn', '88110092');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (92, 2092, 'Овог92', 'Сурагч92', 5);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (92, 5, 70, 1, 105);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (92, 5, CURDATE(), 1, 105);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2093, 'student93', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 93', 'student93@test.mn', '88110093');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (93, 2093, 'Овог93', 'Сурагч93', 5);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (93, 5, 100, 1, 105);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (93, 5, CURDATE(), 1, 105);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2094, 'student94', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 94', 'student94@test.mn', '88110094');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (94, 2094, 'Овог94', 'Сурагч94', 5);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (94, 5, 88, 1, 105);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (94, 5, CURDATE(), 1, 105);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2095, 'student95', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 95', 'student95@test.mn', '88110095');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (95, 2095, 'Овог95', 'Сурагч95', 5);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (95, 5, 86, 1, 105);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (95, 5, CURDATE(), 1, 105);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2096, 'student96', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 96', 'student96@test.mn', '88110096');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (96, 2096, 'Овог96', 'Сурагч96', 5);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (96, 5, 94, 1, 105);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (96, 5, CURDATE(), 1, 105);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2097, 'student97', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 97', 'student97@test.mn', '88110097');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (97, 2097, 'Овог97', 'Сурагч97', 5);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (97, 5, 64, 1, 105);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (97, 5, CURDATE(), 1, 105);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2098, 'student98', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 98', 'student98@test.mn', '88110098');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (98, 2098, 'Овог98', 'Сурагч98', 5);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (98, 5, 94, 1, 105);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (98, 5, CURDATE(), 1, 105);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2099, 'student99', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 99', 'student99@test.mn', '88110099');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (99, 2099, 'Овог99', 'Сурагч99', 5);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (99, 5, 63, 1, 105);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (99, 5, CURDATE(), 1, 105);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2100, 'student100', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 100', 'student100@test.mn', '88110100');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (100, 2100, 'Овог100', 'Сурагч100', 5);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (100, 5, 77, 1, 105);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (100, 5, CURDATE(), 1, 105);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2101, 'student101', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 101', 'student101@test.mn', '88110101');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (101, 2101, 'Овог101', 'Сурагч101', 6);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (101, 6, 65, 1, 106);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (101, 6, CURDATE(), 1, 106);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2102, 'student102', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 102', 'student102@test.mn', '88110102');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (102, 2102, 'Овог102', 'Сурагч102', 6);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (102, 6, 68, 1, 106);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (102, 6, CURDATE(), 1, 106);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2103, 'student103', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 103', 'student103@test.mn', '88110103');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (103, 2103, 'Овог103', 'Сурагч103', 6);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (103, 6, 96, 1, 106);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (103, 6, CURDATE(), 1, 106);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2104, 'student104', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 104', 'student104@test.mn', '88110104');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (104, 2104, 'Овог104', 'Сурагч104', 6);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (104, 6, 60, 1, 106);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (104, 6, CURDATE(), 1, 106);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2105, 'student105', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 105', 'student105@test.mn', '88110105');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (105, 2105, 'Овог105', 'Сурагч105', 6);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (105, 6, 98, 1, 106);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (105, 6, CURDATE(), 1, 106);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2106, 'student106', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 106', 'student106@test.mn', '88110106');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (106, 2106, 'Овог106', 'Сурагч106', 6);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (106, 6, 74, 1, 106);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (106, 6, CURDATE(), 1, 106);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2107, 'student107', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 107', 'student107@test.mn', '88110107');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (107, 2107, 'Овог107', 'Сурагч107', 6);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (107, 6, 61, 1, 106);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (107, 6, CURDATE(), 1, 106);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2108, 'student108', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 108', 'student108@test.mn', '88110108');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (108, 2108, 'Овог108', 'Сурагч108', 6);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (108, 6, 70, 1, 106);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (108, 6, CURDATE(), 1, 106);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2109, 'student109', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 109', 'student109@test.mn', '88110109');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (109, 2109, 'Овог109', 'Сурагч109', 6);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (109, 6, 72, 1, 106);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (109, 6, CURDATE(), 1, 106);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2110, 'student110', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 110', 'student110@test.mn', '88110110');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (110, 2110, 'Овог110', 'Сурагч110', 6);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (110, 6, 79, 1, 106);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (110, 6, CURDATE(), 1, 106);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2111, 'student111', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 111', 'student111@test.mn', '88110111');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (111, 2111, 'Овог111', 'Сурагч111', 6);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (111, 6, 90, 1, 106);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (111, 6, CURDATE(), 1, 106);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2112, 'student112', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 112', 'student112@test.mn', '88110112');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (112, 2112, 'Овог112', 'Сурагч112', 6);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (112, 6, 80, 1, 106);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (112, 6, CURDATE(), 1, 106);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2113, 'student113', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 113', 'student113@test.mn', '88110113');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (113, 2113, 'Овог113', 'Сурагч113', 6);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (113, 6, 69, 1, 106);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (113, 6, CURDATE(), 2, 106);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2114, 'student114', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 114', 'student114@test.mn', '88110114');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (114, 2114, 'Овог114', 'Сурагч114', 6);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (114, 6, 76, 1, 106);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (114, 6, CURDATE(), 1, 106);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2115, 'student115', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 115', 'student115@test.mn', '88110115');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (115, 2115, 'Овог115', 'Сурагч115', 6);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (115, 6, 69, 1, 106);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (115, 6, CURDATE(), 1, 106);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2116, 'student116', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 116', 'student116@test.mn', '88110116');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (116, 2116, 'Овог116', 'Сурагч116', 6);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (116, 6, 82, 1, 106);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (116, 6, CURDATE(), 1, 106);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2117, 'student117', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 117', 'student117@test.mn', '88110117');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (117, 2117, 'Овог117', 'Сурагч117', 6);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (117, 6, 79, 1, 106);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (117, 6, CURDATE(), 2, 106);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2118, 'student118', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 118', 'student118@test.mn', '88110118');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (118, 2118, 'Овог118', 'Сурагч118', 6);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (118, 6, 70, 1, 106);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (118, 6, CURDATE(), 1, 106);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2119, 'student119', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 119', 'student119@test.mn', '88110119');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (119, 2119, 'Овог119', 'Сурагч119', 6);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (119, 6, 64, 1, 106);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (119, 6, CURDATE(), 1, 106);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2120, 'student120', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 120', 'student120@test.mn', '88110120');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (120, 2120, 'Овог120', 'Сурагч120', 6);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (120, 6, 97, 1, 106);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (120, 6, CURDATE(), 1, 106);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2121, 'student121', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 121', 'student121@test.mn', '88110121');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (121, 2121, 'Овог121', 'Сурагч121', 7);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (121, 7, 97, 1, 107);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (121, 7, CURDATE(), 1, 107);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2122, 'student122', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 122', 'student122@test.mn', '88110122');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (122, 2122, 'Овог122', 'Сурагч122', 7);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (122, 7, 77, 1, 107);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (122, 7, CURDATE(), 1, 107);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2123, 'student123', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 123', 'student123@test.mn', '88110123');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (123, 2123, 'Овог123', 'Сурагч123', 7);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (123, 7, 97, 1, 107);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (123, 7, CURDATE(), 1, 107);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2124, 'student124', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 124', 'student124@test.mn', '88110124');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (124, 2124, 'Овог124', 'Сурагч124', 7);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (124, 7, 93, 1, 107);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (124, 7, CURDATE(), 1, 107);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2125, 'student125', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 125', 'student125@test.mn', '88110125');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (125, 2125, 'Овог125', 'Сурагч125', 7);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (125, 7, 76, 1, 107);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (125, 7, CURDATE(), 1, 107);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2126, 'student126', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 126', 'student126@test.mn', '88110126');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (126, 2126, 'Овог126', 'Сурагч126', 7);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (126, 7, 63, 1, 107);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (126, 7, CURDATE(), 1, 107);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2127, 'student127', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 127', 'student127@test.mn', '88110127');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (127, 2127, 'Овог127', 'Сурагч127', 7);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (127, 7, 82, 1, 107);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (127, 7, CURDATE(), 1, 107);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2128, 'student128', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 128', 'student128@test.mn', '88110128');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (128, 2128, 'Овог128', 'Сурагч128', 7);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (128, 7, 96, 1, 107);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (128, 7, CURDATE(), 1, 107);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2129, 'student129', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 129', 'student129@test.mn', '88110129');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (129, 2129, 'Овог129', 'Сурагч129', 7);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (129, 7, 63, 1, 107);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (129, 7, CURDATE(), 1, 107);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2130, 'student130', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 130', 'student130@test.mn', '88110130');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (130, 2130, 'Овог130', 'Сурагч130', 7);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (130, 7, 82, 1, 107);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (130, 7, CURDATE(), 1, 107);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2131, 'student131', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 131', 'student131@test.mn', '88110131');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (131, 2131, 'Овог131', 'Сурагч131', 7);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (131, 7, 90, 1, 107);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (131, 7, CURDATE(), 1, 107);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2132, 'student132', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 132', 'student132@test.mn', '88110132');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (132, 2132, 'Овог132', 'Сурагч132', 7);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (132, 7, 79, 1, 107);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (132, 7, CURDATE(), 1, 107);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2133, 'student133', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 133', 'student133@test.mn', '88110133');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (133, 2133, 'Овог133', 'Сурагч133', 7);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (133, 7, 67, 1, 107);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (133, 7, CURDATE(), 1, 107);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2134, 'student134', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 134', 'student134@test.mn', '88110134');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (134, 2134, 'Овог134', 'Сурагч134', 7);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (134, 7, 94, 1, 107);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (134, 7, CURDATE(), 1, 107);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2135, 'student135', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 135', 'student135@test.mn', '88110135');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (135, 2135, 'Овог135', 'Сурагч135', 7);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (135, 7, 91, 1, 107);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (135, 7, CURDATE(), 1, 107);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2136, 'student136', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 136', 'student136@test.mn', '88110136');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (136, 2136, 'Овог136', 'Сурагч136', 7);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (136, 7, 76, 1, 107);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (136, 7, CURDATE(), 1, 107);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2137, 'student137', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 137', 'student137@test.mn', '88110137');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (137, 2137, 'Овог137', 'Сурагч137', 7);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (137, 7, 99, 1, 107);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (137, 7, CURDATE(), 1, 107);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2138, 'student138', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 138', 'student138@test.mn', '88110138');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (138, 2138, 'Овог138', 'Сурагч138', 7);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (138, 7, 67, 1, 107);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (138, 7, CURDATE(), 1, 107);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2139, 'student139', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 139', 'student139@test.mn', '88110139');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (139, 2139, 'Овог139', 'Сурагч139', 7);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (139, 7, 74, 1, 107);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (139, 7, CURDATE(), 2, 107);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2140, 'student140', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 140', 'student140@test.mn', '88110140');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (140, 2140, 'Овог140', 'Сурагч140', 7);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (140, 7, 83, 1, 107);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (140, 7, CURDATE(), 1, 107);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2141, 'student141', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 141', 'student141@test.mn', '88110141');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (141, 2141, 'Овог141', 'Сурагч141', 8);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (141, 8, 73, 1, 108);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (141, 8, CURDATE(), 1, 108);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2142, 'student142', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 142', 'student142@test.mn', '88110142');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (142, 2142, 'Овог142', 'Сурагч142', 8);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (142, 8, 98, 1, 108);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (142, 8, CURDATE(), 1, 108);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2143, 'student143', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 143', 'student143@test.mn', '88110143');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (143, 2143, 'Овог143', 'Сурагч143', 8);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (143, 8, 65, 1, 108);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (143, 8, CURDATE(), 1, 108);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2144, 'student144', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 144', 'student144@test.mn', '88110144');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (144, 2144, 'Овог144', 'Сурагч144', 8);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (144, 8, 66, 1, 108);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (144, 8, CURDATE(), 1, 108);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2145, 'student145', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 145', 'student145@test.mn', '88110145');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (145, 2145, 'Овог145', 'Сурагч145', 8);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (145, 8, 82, 1, 108);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (145, 8, CURDATE(), 1, 108);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2146, 'student146', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 146', 'student146@test.mn', '88110146');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (146, 2146, 'Овог146', 'Сурагч146', 8);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (146, 8, 71, 1, 108);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (146, 8, CURDATE(), 1, 108);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2147, 'student147', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 147', 'student147@test.mn', '88110147');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (147, 2147, 'Овог147', 'Сурагч147', 8);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (147, 8, 68, 1, 108);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (147, 8, CURDATE(), 1, 108);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2148, 'student148', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 148', 'student148@test.mn', '88110148');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (148, 2148, 'Овог148', 'Сурагч148', 8);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (148, 8, 78, 1, 108);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (148, 8, CURDATE(), 1, 108);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2149, 'student149', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 149', 'student149@test.mn', '88110149');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (149, 2149, 'Овог149', 'Сурагч149', 8);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (149, 8, 78, 1, 108);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (149, 8, CURDATE(), 1, 108);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2150, 'student150', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 150', 'student150@test.mn', '88110150');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (150, 2150, 'Овог150', 'Сурагч150', 8);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (150, 8, 95, 1, 108);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (150, 8, CURDATE(), 1, 108);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2151, 'student151', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 151', 'student151@test.mn', '88110151');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (151, 2151, 'Овог151', 'Сурагч151', 8);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (151, 8, 87, 1, 108);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (151, 8, CURDATE(), 1, 108);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2152, 'student152', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 152', 'student152@test.mn', '88110152');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (152, 2152, 'Овог152', 'Сурагч152', 8);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (152, 8, 72, 1, 108);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (152, 8, CURDATE(), 1, 108);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2153, 'student153', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 153', 'student153@test.mn', '88110153');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (153, 2153, 'Овог153', 'Сурагч153', 8);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (153, 8, 67, 1, 108);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (153, 8, CURDATE(), 1, 108);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2154, 'student154', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 154', 'student154@test.mn', '88110154');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (154, 2154, 'Овог154', 'Сурагч154', 8);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (154, 8, 68, 1, 108);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (154, 8, CURDATE(), 1, 108);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2155, 'student155', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 155', 'student155@test.mn', '88110155');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (155, 2155, 'Овог155', 'Сурагч155', 8);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (155, 8, 91, 1, 108);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (155, 8, CURDATE(), 1, 108);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2156, 'student156', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 156', 'student156@test.mn', '88110156');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (156, 2156, 'Овог156', 'Сурагч156', 8);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (156, 8, 70, 1, 108);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (156, 8, CURDATE(), 2, 108);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2157, 'student157', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 157', 'student157@test.mn', '88110157');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (157, 2157, 'Овог157', 'Сурагч157', 8);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (157, 8, 97, 1, 108);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (157, 8, CURDATE(), 1, 108);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2158, 'student158', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 158', 'student158@test.mn', '88110158');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (158, 2158, 'Овог158', 'Сурагч158', 8);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (158, 8, 92, 1, 108);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (158, 8, CURDATE(), 1, 108);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2159, 'student159', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 159', 'student159@test.mn', '88110159');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (159, 2159, 'Овог159', 'Сурагч159', 8);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (159, 8, 63, 1, 108);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (159, 8, CURDATE(), 2, 108);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2160, 'student160', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 160', 'student160@test.mn', '88110160');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (160, 2160, 'Овог160', 'Сурагч160', 8);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (160, 8, 68, 1, 108);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (160, 8, CURDATE(), 1, 108);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2161, 'student161', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 161', 'student161@test.mn', '88110161');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (161, 2161, 'Овог161', 'Сурагч161', 9);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (161, 9, 74, 1, 109);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (161, 9, CURDATE(), 2, 109);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2162, 'student162', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 162', 'student162@test.mn', '88110162');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (162, 2162, 'Овог162', 'Сурагч162', 9);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (162, 9, 89, 1, 109);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (162, 9, CURDATE(), 1, 109);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2163, 'student163', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 163', 'student163@test.mn', '88110163');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (163, 2163, 'Овог163', 'Сурагч163', 9);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (163, 9, 96, 1, 109);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (163, 9, CURDATE(), 2, 109);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2164, 'student164', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 164', 'student164@test.mn', '88110164');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (164, 2164, 'Овог164', 'Сурагч164', 9);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (164, 9, 66, 1, 109);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (164, 9, CURDATE(), 2, 109);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2165, 'student165', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 165', 'student165@test.mn', '88110165');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (165, 2165, 'Овог165', 'Сурагч165', 9);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (165, 9, 69, 1, 109);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (165, 9, CURDATE(), 1, 109);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2166, 'student166', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 166', 'student166@test.mn', '88110166');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (166, 2166, 'Овог166', 'Сурагч166', 9);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (166, 9, 83, 1, 109);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (166, 9, CURDATE(), 1, 109);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2167, 'student167', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 167', 'student167@test.mn', '88110167');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (167, 2167, 'Овог167', 'Сурагч167', 9);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (167, 9, 92, 1, 109);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (167, 9, CURDATE(), 1, 109);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2168, 'student168', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 168', 'student168@test.mn', '88110168');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (168, 2168, 'Овог168', 'Сурагч168', 9);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (168, 9, 92, 1, 109);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (168, 9, CURDATE(), 2, 109);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2169, 'student169', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 169', 'student169@test.mn', '88110169');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (169, 2169, 'Овог169', 'Сурагч169', 9);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (169, 9, 82, 1, 109);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (169, 9, CURDATE(), 1, 109);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2170, 'student170', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 170', 'student170@test.mn', '88110170');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (170, 2170, 'Овог170', 'Сурагч170', 9);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (170, 9, 97, 1, 109);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (170, 9, CURDATE(), 1, 109);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2171, 'student171', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 171', 'student171@test.mn', '88110171');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (171, 2171, 'Овог171', 'Сурагч171', 9);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (171, 9, 93, 1, 109);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (171, 9, CURDATE(), 2, 109);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2172, 'student172', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 172', 'student172@test.mn', '88110172');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (172, 2172, 'Овог172', 'Сурагч172', 9);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (172, 9, 63, 1, 109);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (172, 9, CURDATE(), 1, 109);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2173, 'student173', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 173', 'student173@test.mn', '88110173');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (173, 2173, 'Овог173', 'Сурагч173', 9);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (173, 9, 60, 1, 109);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (173, 9, CURDATE(), 1, 109);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2174, 'student174', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 174', 'student174@test.mn', '88110174');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (174, 2174, 'Овог174', 'Сурагч174', 9);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (174, 9, 87, 1, 109);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (174, 9, CURDATE(), 1, 109);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2175, 'student175', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 175', 'student175@test.mn', '88110175');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (175, 2175, 'Овог175', 'Сурагч175', 9);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (175, 9, 65, 1, 109);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (175, 9, CURDATE(), 1, 109);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2176, 'student176', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 176', 'student176@test.mn', '88110176');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (176, 2176, 'Овог176', 'Сурагч176', 9);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (176, 9, 82, 1, 109);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (176, 9, CURDATE(), 1, 109);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2177, 'student177', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 177', 'student177@test.mn', '88110177');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (177, 2177, 'Овог177', 'Сурагч177', 9);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (177, 9, 73, 1, 109);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (177, 9, CURDATE(), 2, 109);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2178, 'student178', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 178', 'student178@test.mn', '88110178');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (178, 2178, 'Овог178', 'Сурагч178', 9);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (178, 9, 89, 1, 109);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (178, 9, CURDATE(), 2, 109);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2179, 'student179', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 179', 'student179@test.mn', '88110179');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (179, 2179, 'Овог179', 'Сурагч179', 9);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (179, 9, 98, 1, 109);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (179, 9, CURDATE(), 1, 109);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2180, 'student180', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 180', 'student180@test.mn', '88110180');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (180, 2180, 'Овог180', 'Сурагч180', 9);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (180, 9, 86, 1, 109);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (180, 9, CURDATE(), 1, 109);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2181, 'student181', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 181', 'student181@test.mn', '88110181');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (181, 2181, 'Овог181', 'Сурагч181', 10);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (181, 10, 76, 1, 1010);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (181, 10, CURDATE(), 1, 1010);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2182, 'student182', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 182', 'student182@test.mn', '88110182');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (182, 2182, 'Овог182', 'Сурагч182', 10);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (182, 10, 68, 1, 1010);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (182, 10, CURDATE(), 2, 1010);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2183, 'student183', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 183', 'student183@test.mn', '88110183');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (183, 2183, 'Овог183', 'Сурагч183', 10);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (183, 10, 75, 1, 1010);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (183, 10, CURDATE(), 2, 1010);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2184, 'student184', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 184', 'student184@test.mn', '88110184');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (184, 2184, 'Овог184', 'Сурагч184', 10);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (184, 10, 91, 1, 1010);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (184, 10, CURDATE(), 1, 1010);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2185, 'student185', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 185', 'student185@test.mn', '88110185');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (185, 2185, 'Овог185', 'Сурагч185', 10);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (185, 10, 93, 1, 1010);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (185, 10, CURDATE(), 2, 1010);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2186, 'student186', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 186', 'student186@test.mn', '88110186');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (186, 2186, 'Овог186', 'Сурагч186', 10);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (186, 10, 81, 1, 1010);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (186, 10, CURDATE(), 1, 1010);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2187, 'student187', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 187', 'student187@test.mn', '88110187');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (187, 2187, 'Овог187', 'Сурагч187', 10);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (187, 10, 98, 1, 1010);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (187, 10, CURDATE(), 1, 1010);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2188, 'student188', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 188', 'student188@test.mn', '88110188');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (188, 2188, 'Овог188', 'Сурагч188', 10);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (188, 10, 81, 1, 1010);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (188, 10, CURDATE(), 1, 1010);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2189, 'student189', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 189', 'student189@test.mn', '88110189');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (189, 2189, 'Овог189', 'Сурагч189', 10);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (189, 10, 78, 1, 1010);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (189, 10, CURDATE(), 1, 1010);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2190, 'student190', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 190', 'student190@test.mn', '88110190');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (190, 2190, 'Овог190', 'Сурагч190', 10);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (190, 10, 86, 1, 1010);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (190, 10, CURDATE(), 1, 1010);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2191, 'student191', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 191', 'student191@test.mn', '88110191');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (191, 2191, 'Овог191', 'Сурагч191', 10);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (191, 10, 75, 1, 1010);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (191, 10, CURDATE(), 2, 1010);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2192, 'student192', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 192', 'student192@test.mn', '88110192');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (192, 2192, 'Овог192', 'Сурагч192', 10);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (192, 10, 96, 1, 1010);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (192, 10, CURDATE(), 1, 1010);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2193, 'student193', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 193', 'student193@test.mn', '88110193');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (193, 2193, 'Овог193', 'Сурагч193', 10);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (193, 10, 68, 1, 1010);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (193, 10, CURDATE(), 1, 1010);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2194, 'student194', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 194', 'student194@test.mn', '88110194');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (194, 2194, 'Овог194', 'Сурагч194', 10);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (194, 10, 81, 1, 1010);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (194, 10, CURDATE(), 2, 1010);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2195, 'student195', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 195', 'student195@test.mn', '88110195');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (195, 2195, 'Овог195', 'Сурагч195', 10);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (195, 10, 82, 1, 1010);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (195, 10, CURDATE(), 1, 1010);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2196, 'student196', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 196', 'student196@test.mn', '88110196');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (196, 2196, 'Овог196', 'Сурагч196', 10);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (196, 10, 80, 1, 1010);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (196, 10, CURDATE(), 2, 1010);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2197, 'student197', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 197', 'student197@test.mn', '88110197');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (197, 2197, 'Овог197', 'Сурагч197', 10);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (197, 10, 78, 1, 1010);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (197, 10, CURDATE(), 1, 1010);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2198, 'student198', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 198', 'student198@test.mn', '88110198');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (198, 2198, 'Овог198', 'Сурагч198', 10);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (198, 10, 83, 1, 1010);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (198, 10, CURDATE(), 2, 1010);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2199, 'student199', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 199', 'student199@test.mn', '88110199');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (199, 2199, 'Овог199', 'Сурагч199', 10);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (199, 10, 80, 1, 1010);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (199, 10, CURDATE(), 1, 1010);
INSERT IGNORE INTO users (user_id, username, password_hash, role_id, full_name, email, phone) VALUES (2200, 'student200', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Сурагч 200', 'student200@test.mn', '88110200');
INSERT IGNORE INTO students (student_id, user_id, last_name, first_name, class_id) VALUES (200, 2200, 'Овог200', 'Сурагч200', 10);
INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (200, 10, 76, 1, 1010);
INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (200, 10, CURDATE(), 1, 1010);

-- Зарлалууд
INSERT IGNORE INTO announcements (title, body, created_by, target_audience) VALUES ('Зарлал 1', 'Энэ бол туршилтын зарлал 1', 1, 'all');
INSERT IGNORE INTO announcements (title, body, created_by, target_audience) VALUES ('Зарлал 2', 'Энэ бол туршилтын зарлал 2', 1, 'all');
INSERT IGNORE INTO announcements (title, body, created_by, target_audience) VALUES ('Зарлал 3', 'Энэ бол туршилтын зарлал 3', 1, 'all');
INSERT IGNORE INTO announcements (title, body, created_by, target_audience) VALUES ('Зарлал 4', 'Энэ бол туршилтын зарлал 4', 1, 'all');
INSERT IGNORE INTO announcements (title, body, created_by, target_audience) VALUES ('Зарлал 5', 'Энэ бол туршилтын зарлал 5', 1, 'all');
-- ═══════════════════════════════════════════════════════════════
-- GPA Trigger — Дүн нэмэгдэх бүрт автоматаар GPA тооцоолох
-- ═══════════════════════════════════════════════════════════════

-- 1. students хүснэгтэд gpa багана нэмэх
ALTER TABLE students ADD COLUMN IF NOT EXISTS gpa DECIMAL(4,2) DEFAULT NULL;

-- 2. Хуучин trigger байвал устгах
DROP TRIGGER IF EXISTS trg_grades_after_insert;
DROP TRIGGER IF EXISTS trg_grades_after_update;

-- 3. INSERT trigger — дүн нэмэгдэх үед GPA шинэчлэх
DELIMITER //
CREATE TRIGGER trg_grades_after_insert
AFTER INSERT ON grades
FOR EACH ROW
BEGIN
    DECLARE avg_gpa DECIMAL(4,2);

    -- Бүх хичээлүүдээр subject бүрийн дундаж авч, дараа нь ерөнхий дундаж тооцоолно
    -- GPA = subject бүрийн дундаж дүнг 4.0 scale-д хөрвүүлж, нийтийн дундаж авна
    SELECT AVG(
        CASE
            WHEN sub_avg >= 90 THEN 4.0
            WHEN sub_avg >= 80 THEN 3.0
            WHEN sub_avg >= 70 THEN 2.0
            WHEN sub_avg >= 60 THEN 1.0
            ELSE 0.0
        END
    ) INTO avg_gpa
    FROM (
        SELECT AVG(grade_value) AS sub_avg
        FROM grades
        WHERE student_id = NEW.student_id
        GROUP BY subject_id
    ) AS subject_averages;

    UPDATE students SET gpa = avg_gpa WHERE student_id = NEW.student_id;
END//

-- 4. UPDATE trigger — дүн засагдах үед GPA шинэчлэх
CREATE TRIGGER trg_grades_after_update
AFTER UPDATE ON grades
FOR EACH ROW
BEGIN
    DECLARE avg_gpa DECIMAL(4,2);

    SELECT AVG(
        CASE
            WHEN sub_avg >= 90 THEN 4.0
            WHEN sub_avg >= 80 THEN 3.0
            WHEN sub_avg >= 70 THEN 2.0
            WHEN sub_avg >= 60 THEN 1.0
            ELSE 0.0
        END
    ) INTO avg_gpa
    FROM (
        SELECT AVG(grade_value) AS sub_avg
        FROM grades
        WHERE student_id = NEW.student_id
        GROUP BY subject_id
    ) AS subject_averages;

    UPDATE students SET gpa = avg_gpa WHERE student_id = NEW.student_id;
END//

DELIMITER ;

-- 5. Одоо байгаа бүх сурагчдын GPA-г нэг удаа тооцоолох (migration)
UPDATE students s
SET gpa = (
    SELECT AVG(
        CASE
            WHEN sub_avg >= 90 THEN 4.0
            WHEN sub_avg >= 80 THEN 3.0
            WHEN sub_avg >= 70 THEN 2.0
            WHEN sub_avg >= 60 THEN 1.0
            ELSE 0.0
        END
    )
    FROM (
        SELECT AVG(grade_value) AS sub_avg
        FROM grades
        WHERE student_id = s.student_id
        GROUP BY subject_id
    ) AS subject_averages
)
WHERE s.student_id IN (SELECT DISTINCT student_id FROM grades);
