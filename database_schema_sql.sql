-- गुनासो व्यवस्थापन प्रणाली Database Schema
-- बेसीशहर नगरपालिका

CREATE DATABASE IF NOT EXISTS besishahar_gunaso CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE besishahar_gunaso;

-- Users Table (Admin र Employee)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'employee') NOT NULL DEFAULT 'employee',
    email VARCHAR(100),
    phone VARCHAR(15),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Branches Table (शाखा/विभाग)
CREATE TABLE branches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    branch_name VARCHAR(100) NOT NULL,
    branch_code VARCHAR(20) UNIQUE,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Complaint Types Table (गुनासो प्रकार)
CREATE TABLE complaint_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type_name VARCHAR(100) NOT NULL,
    type_code VARCHAR(20) UNIQUE,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Complaints Table (मुख्य गुनासो)
CREATE TABLE complaints (
    id INT PRIMARY KEY AUTO_INCREMENT,
    complaint_id VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    address VARCHAR(255) NOT NULL,
    contact VARCHAR(15) NOT NULL,
    email VARCHAR(100),
    branch_id INT,
    type_id INT,
    subject VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    file_path VARCHAR(255),
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('pending', 'in-progress', 'resolved', 'rejected') DEFAULT 'pending',
    assigned_to INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
    FOREIGN KEY (type_id) REFERENCES complaint_types(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Complaint Logs Table (गुनासो लग)
CREATE TABLE complaint_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    complaint_id INT NOT NULL,
    updated_by INT NOT NULL,
    status ENUM('pending', 'in-progress', 'resolved', 'rejected') NOT NULL,
    remarks TEXT,
    admin_reply TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings Table (प्रणाली सेटिङ्ग)
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT,
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email Queue Table (इमेल सूचना)
CREATE TABLE email_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    complaint_id INT NOT NULL,
    recipient_email VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Default Admin (password: password)
INSERT INTO users (name, username, password, role, email, phone) VALUES
('प्रशासक', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'admin@besishahar.gov.np', '9876543210');

-- Insert Default Branches
INSERT INTO branches (branch_name, branch_code, description) VALUES
('प्रशासन शाखा', 'ADMIN', 'प्रशासन सम्बन्धी कार्य'),
('योजना शाखा', 'PLAN', 'योजना तर्जुमा तथा कार्यान्वयन'),
('शिक्षा शाखा', 'EDU', 'शिक्षा सम्बन्धी कार्य'),
('स्वास्थ्य शाखा', 'HEALTH', 'स्वास्थ्य सेवा सम्बन्धी कार्य'),
('पूर्वाधार शाखा', 'INFRA', 'पूर्वाधार विकास सम्बन्धी कार्य'),
('सामाजिक विकास शाखा', 'SOCIAL', 'सामाजिक विकास सम्बन्धी कार्य'),
('राजस्व शाखा', 'REV', 'राजस्व सम्बन्धी कार्य'),
('वन तथा वातावरण शाखा', 'ENV', 'वन तथा वातावरण सम्बन्धी कार्य');

-- Insert Default Complaint Types
INSERT INTO complaint_types (type_name, type_code, description) VALUES
('सेवा सम्बन्धी', 'SERVICE', 'सेवा प्रदानमा समस्या'),
('कर्मचारी व्यवहार', 'STAFF', 'कर्मचारीको व्यवहार सम्बन्धी'),
('भौतिक पूर्वाधार', 'INFRA', 'सडक, भवन, खानेपानी आदि'),
('सार्वजनिक सेवा', 'PUBLIC', 'सार्वजनिक सेवा सम्बन्धी'),
('भ्रष्टाचार', 'CORRUPT', 'भ्रष्टाचार सम्बन्धी गुनासो'),
('वातावरण', 'ENV', 'वातावरण सम्बन्धी समस्या'),
('योजना कार्यान्वयन', 'PROJECT', 'योजना कार्यान्वयनमा समस्या'),
('अन्य', 'OTHER', 'अन्य प्रकारका गुनासो');

-- Insert Default Settings
INSERT INTO settings (setting_key, setting_value, description) VALUES
('office_name', 'बेसीशहर नगरपालिका', 'कार्यालयको नाम'),
('office_address', 'बेसीशहर, लमजुङ', 'कार्यालयको ठेगाना'),
('office_phone', '065-560322', 'सम्पर्क फोन नम्बर'),
('office_email', 'info@besishahar.gov.np', 'सम्पर्क इमेल'),
('email_notification', '1', 'इमेल सूचना सक्रिय/निष्क्रिय'),
('complaint_id_prefix', 'GUN', 'गुनासो ID को उपसर्ग'),
('max_file_size', '5242880', 'फाइल अधिकतम साइज (bytes)');

-- Create Indexes
CREATE INDEX idx_complaint_status ON complaints(status);
CREATE INDEX idx_complaint_date ON complaints(created_at);
CREATE INDEX idx_complaint_assigned ON complaints(assigned_to);
CREATE INDEX idx_complaint_id_search ON complaints(complaint_id);
CREATE INDEX idx_logs_complaint ON complaint_logs(complaint_id);
CREATE INDEX idx_email_status ON email_queue(status);