-- ExpertHub Database Schema
-- ICT Support and Maintenance Marketplace Platform
-- Online Service Shop for Hardware, Software, and Technology Solutions

-- Drop existing tables if they exist (in reverse order of dependencies)
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS order_milestones;
DROP TABLE IF EXISTS order_documents;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS cart_items;
DROP TABLE IF EXISTS shopping_carts;
DROP TABLE IF EXISTS provider_services;
DROP TABLE IF EXISTS provider_categories;
DROP TABLE IF EXISTS provider_availability;
DROP TABLE IF EXISTS service_categories;
DROP TABLE IF EXISTS service_providers;
DROP TABLE IF EXISTS customer_devices;
DROP TABLE IF EXISTS users;

-- Users table (customers, providers, admins)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    country VARCHAR(100),
    timezone VARCHAR(50) DEFAULT 'UTC',
    profile_image VARCHAR(255),
    user_type ENUM('customer', 'provider', 'admin') NOT NULL DEFAULT 'customer',
    status ENUM('active', 'inactive', 'suspended', 'pending_verification') NOT NULL DEFAULT 'active',
    email_verified BOOLEAN DEFAULT FALSE,
    phone_verified BOOLEAN DEFAULT FALSE,
    language_preference VARCHAR(10) DEFAULT 'en',
    communication_preferences JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Service Categories (Hardware, Software, Network, etc.)
CREATE TABLE service_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(255),
    parent_id INT NULL,
    sort_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES service_categories(id) ON DELETE SET NULL
);

-- Customer Devices (for tracking maintenance history)
CREATE TABLE customer_devices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    device_type ENUM('laptop', 'desktop', 'printer', 'server', 'network_device', 'mobile', 'other') NOT NULL,
    brand VARCHAR(100),
    model VARCHAR(100),
    serial_number VARCHAR(100),
    purchase_date DATE,
    warranty_expiry DATE,
    specifications JSON,
    notes TEXT,
    status ENUM('active', 'retired', 'under_repair') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Service Providers (extended profile for provider users)
CREATE TABLE service_providers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    professional_title VARCHAR(200),
    bio TEXT,
    experience_years INT DEFAULT 0,
    certifications JSON,
    skills JSON,
    hourly_rate DECIMAL(10,2),
    emergency_rate DECIMAL(10,2),
    availability_status ENUM('available', 'busy', 'offline') DEFAULT 'offline',
    rating DECIMAL(3,2) DEFAULT 0.00,
    total_reviews INT DEFAULT 0,
    total_earnings DECIMAL(12,2) DEFAULT 0.00,
    completion_rate DECIMAL(5,2) DEFAULT 0.00,
    response_time_avg INT DEFAULT 0, -- in minutes
    verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    verification_documents JSON,
    portfolio JSON,
    service_areas JSON,
    background_check_status ENUM('pending', 'passed', 'failed', 'not_required') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Provider Availability Schedule
CREATE TABLE provider_availability (
    id INT PRIMARY KEY AUTO_INCREMENT,
    provider_id INT NOT NULL,
    day_of_week TINYINT NOT NULL, -- 0=Sunday, 1=Monday, etc.
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_available BOOLEAN DEFAULT TRUE,
    timezone VARCHAR(50) DEFAULT 'UTC',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (provider_id) REFERENCES service_providers(id) ON DELETE CASCADE
);

-- Provider Categories (many-to-many relationship)
CREATE TABLE provider_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    provider_id INT NOT NULL,
    category_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (provider_id) REFERENCES service_providers(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES service_categories(id) ON DELETE CASCADE,
    UNIQUE KEY unique_provider_category (provider_id, category_id)
);

-- Provider Services (services offered by providers)
CREATE TABLE provider_services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    provider_id INT NOT NULL,
    category_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    service_type VARCHAR(100) NOT NULL,
    pricing_model ENUM('fixed', 'hourly', 'emergency') DEFAULT 'fixed',
    base_price DECIMAL(10,2) NOT NULL,
    hourly_rate DECIMAL(10,2),
    emergency_rate DECIMAL(10,2),
    estimated_duration INT, -- in minutes
    service_packages JSON, -- Basic/Standard/Premium packages
    requirements_form JSON, -- Dynamic form fields for customer requirements
    deliverables TEXT,
    technical_requirements TEXT,
    service_scope TEXT,
    status ENUM('active', 'inactive', 'draft') DEFAULT 'active',
    featured BOOLEAN DEFAULT FALSE,
    capacity_limit INT DEFAULT NULL, -- max concurrent orders
    geographic_availability JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (provider_id) REFERENCES service_providers(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES service_categories(id) ON DELETE CASCADE
);

-- Shopping Carts
CREATE TABLE shopping_carts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    session_id VARCHAR(255),
    status ENUM('active', 'abandoned', 'converted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Cart Items
CREATE TABLE cart_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cart_id INT NOT NULL,
    service_id INT NOT NULL,
    quantity INT DEFAULT 1,
    selected_package VARCHAR(50), -- Basic/Standard/Premium
    custom_requirements JSON,
    estimated_price DECIMAL(10,2),
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cart_id) REFERENCES shopping_carts(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES provider_services(id) ON DELETE CASCADE
);

-- Orders (main order management)
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    customer_id INT NOT NULL,
    provider_id INT NOT NULL,
    service_id INT NOT NULL,
    device_id INT NULL,
    order_type ENUM('immediate', 'scheduled', 'emergency') DEFAULT 'immediate',
    status ENUM('requested', 'quoted', 'accepted', 'in_progress', 'awaiting_review', 'completed', 'cancelled', 'disputed') DEFAULT 'requested',
    priority ENUM('low', 'normal', 'high', 'emergency') DEFAULT 'normal',
    service_title VARCHAR(200) NOT NULL,
    service_description TEXT,
    customer_requirements JSON,
    quoted_price DECIMAL(10,2),
    final_price DECIMAL(10,2),
    estimated_duration INT, -- in minutes
    scheduled_date DATE,
    scheduled_time TIME,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    deadline TIMESTAMP NULL,
    cancellation_reason TEXT,
    special_instructions TEXT,
    meeting_link VARCHAR(500),
    remote_access_details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES service_providers(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES provider_services(id) ON DELETE CASCADE,
    FOREIGN KEY (device_id) REFERENCES customer_devices(id) ON DELETE SET NULL
);

-- Order Milestones (for tracking progress)
CREATE TABLE order_milestones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    milestone_name VARCHAR(200) NOT NULL,
    description TEXT,
    status ENUM('pending', 'in_progress', 'completed', 'skipped') DEFAULT 'pending',
    due_date TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    payment_percentage DECIMAL(5,2) DEFAULT 0.00,
    deliverables TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- Messages (communication between customers and providers)
CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message_type ENUM('text', 'file', 'system', 'video_call', 'screen_share') DEFAULT 'text',
    message_content TEXT,
    file_attachments JSON,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Order Documents (files and deliverables)
CREATE TABLE order_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    document_type ENUM('requirement', 'deliverable', 'diagnostic', 'report', 'invoice', 'other') NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    mime_type VARCHAR(100),
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE, -- visible to both parties
    version_number INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Payments
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    milestone_id INT NULL,
    payment_type ENUM('full', 'milestone', 'refund', 'commission') DEFAULT 'full',
    amount DECIMAL(10,2) NOT NULL,
    platform_commission DECIMAL(10,2) DEFAULT 0.00,
    provider_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('credit_card', 'paypal', 'bank_transfer', 'wallet', 'mobile_money') NOT NULL,
    payment_status ENUM('pending', 'processing', 'completed', 'failed', 'refunded', 'disputed') DEFAULT 'pending',
    transaction_id VARCHAR(255),
    payment_gateway VARCHAR(100),
    gateway_response JSON,
    escrow_status ENUM('held', 'released', 'disputed') DEFAULT 'held',
    processed_at TIMESTAMP NULL,
    released_at TIMESTAMP NULL,
    refund_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (milestone_id) REFERENCES order_milestones(id) ON DELETE SET NULL
);

-- Reviews and Ratings
CREATE TABLE reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    provider_id INT NOT NULL,
    overall_rating INT NOT NULL CHECK (overall_rating >= 1 AND overall_rating <= 5),
    technical_skill_rating INT CHECK (technical_skill_rating >= 1 AND technical_skill_rating <= 5),
    communication_rating INT CHECK (communication_rating >= 1 AND communication_rating <= 5),
    timeliness_rating INT CHECK (timeliness_rating >= 1 AND timeliness_rating <= 5),
    value_rating INT CHECK (value_rating >= 1 AND value_rating <= 5),
    review_text TEXT,
    photos JSON, -- array of photo URLs
    is_anonymous BOOLEAN DEFAULT FALSE,
    provider_response TEXT,
    provider_response_date TIMESTAMP NULL,
    status ENUM('active', 'hidden', 'reported', 'disputed') DEFAULT 'active',
    helpfulness_votes INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES service_providers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_order_review (order_id)
);

-- Insert Service Categories
INSERT INTO service_categories (name, description, icon, parent_id, sort_order) VALUES
('Technology Services', 'Web development, mobile apps, IT support', 'tech-icon.png', NULL, 1),
('Design & Creative', 'Graphic design, UI/UX, video editing', 'design-icon.png', NULL, 2),
('Marketing & Business', 'Digital marketing, business consulting', 'marketing-icon.png', NULL, 3),
('Writing & Translation', 'Content writing, translation, proofreading', 'writing-icon.png', NULL, 4),
('Education & Training', 'Online tutoring, course creation', 'education-icon.png', NULL, 5),
('Administrative', 'Virtual assistance, data entry', 'admin-icon.png', NULL, 6),
('Government Services', 'Job applications, scholarship applications, Irembo services', 'gov-icon.png', NULL, 7),
('Professional Services', 'Legal consultation, financial planning', 'professional-icon.png', NULL, 8);

-- Hardware subcategories
INSERT INTO service_categories (name, description, icon, parent_id, sort_order) VALUES
('Laptop Repair', 'Laptop hardware repair and maintenance', 'laptop-icon.png', 1, 1),
('Desktop Support', 'Desktop computer repair and upgrade services', 'desktop-icon.png', 1, 2),
('Printer Services', 'Printer repair, maintenance, and setup', 'printer-icon.png', 1, 3),
('Server Maintenance', 'Server hardware maintenance and support', 'server-icon.png', 1, 4);

-- Software subcategories
INSERT INTO service_categories (name, description, icon, parent_id, sort_order) VALUES
('OS Installation', 'Operating system installation and configuration', 'os-icon.png', 2, 1),
('Virus Removal', 'Malware and virus removal services', 'antivirus-icon.png', 2, 2),
('Software Configuration', 'Application setup and configuration', 'config-icon.png', 2, 3),
('Data Recovery', 'Data recovery and backup services', 'recovery-icon.png', 2, 4);

-- Network subcategories
INSERT INTO service_categories (name, description, icon, parent_id, sort_order) VALUES
('WiFi Setup', 'Wireless network setup and configuration', 'wifi-icon.png', 3, 1),
('Network Troubleshooting', 'Network connectivity and performance issues', 'troubleshoot-icon.png', 3, 2),
('Security Setup', 'Network security configuration and monitoring', 'security-icon.png', 3, 3),
('VPN Configuration', 'VPN setup and management services', 'vpn-icon.png', 3, 4);

-- Create indexes for better performance
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_type ON users(user_type);
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_providers_user_id ON service_providers(user_id);
CREATE INDEX idx_providers_rating ON service_providers(rating);
CREATE INDEX idx_providers_status ON service_providers(verification_status);
CREATE INDEX idx_services_provider_id ON provider_services(provider_id);
CREATE INDEX idx_services_category_id ON provider_services(category_id);
CREATE INDEX idx_services_status ON provider_services(status);
CREATE INDEX idx_orders_customer_id ON orders(customer_id);
CREATE INDEX idx_orders_provider_id ON orders(provider_id);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_date ON orders(scheduled_date);
CREATE INDEX idx_orders_number ON orders(order_number);
CREATE INDEX idx_payments_order_id ON payments(order_id);
CREATE INDEX idx_payments_status ON payments(payment_status);
CREATE INDEX idx_messages_order_id ON messages(order_id);
CREATE INDEX idx_messages_sender ON messages(sender_id);
CREATE INDEX idx_messages_receiver ON messages(receiver_id);
CREATE INDEX idx_reviews_provider_id ON reviews(provider_id);
CREATE INDEX idx_reviews_rating ON reviews(overall_rating);
CREATE INDEX idx_cart_customer ON shopping_carts(customer_id);
CREATE INDEX idx_devices_customer ON customer_devices(customer_id);