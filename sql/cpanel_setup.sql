-- ============================================================
-- AI Smart Shopping Assistant — cPanel one-shot setup
-- Database: synergy1_weihern_ai_shop_assistant
-- Import this file in cPanel → phpMyAdmin → Import
-- Upload uploads/products/*.jpg together with the site files
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'admin',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(40) NOT NULL UNIQUE,
    barcode VARCHAR(64) NULL,
    name VARCHAR(120) NOT NULL,
    detect_keywords VARCHAR(255) DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    description TEXT NULL,
    image_path VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_barcode (barcode),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Default admin: admin@shop.local / Admin@12345  (change after first login)
INSERT INTO users (email, password_hash, role) VALUES
(
    'admin@shop.local',
    '$2y$12$2psSQrDb6Lub.jIREK9Ye..HNtsEqinadC7SP30XLQrd6OL4fiGb2',
    'admin'
);

INSERT INTO settings (setting_key, setting_value) VALUES
    ('ai_provider', 'agnes'),
    ('agnes_api_url', 'https://apihub.agnes-ai.com/v1'),
    ('agnes_api_key', ''),
    ('agnes_model', 'agnes-3.0-flash'),
    ('agnes_fallback_models', ''),
    ('gemini_api_url', 'https://generativelanguage.googleapis.com/v1beta'),
    ('gemini_api_key', ''),
    ('gemini_model', 'gemini-3.6-flash'),
    ('gemini_fallback_models', 'gemini-3.7-flash,gemini-3.8-flash');

INSERT INTO products (sku, barcode, name, detect_keywords, price, description, image_path) VALUES
(
    'SKU-001',
    '8857200535366',
    'Thai Herbal Inhaler',
    'herbal inhaler, thai inhaler, peppermint inhaler, po ying',
    5.00,
    'Refreshing traditional Thai herbal inhaler with menthol and essential oils. Ideal for quick relief on the go.',
    'uploads/products/p001-inhaler.jpg'
),
(
    'SKU-002',
    '8850123456789',
    'Green Tea Bottle 500ml',
    'green tea, iced tea, tea bottle, beverage',
    3.50,
    'Chilled green tea beverage with a light, refreshing taste. No artificial colors.',
    'uploads/products/p002-greentea.jpg'
),
(
    'SKU-003',
    '8901234567890',
    'Crispy Potato Chips',
    'potato chips, crisps, snack, salty chips',
    4.20,
    'Classic salted potato chips with a light crunch. Perfect snack for any time of day.',
    'uploads/products/p003-chips.jpg'
),
(
    'SKU-004',
    '5012345678900',
    'Mineral Water 600ml',
    'mineral water, water bottle, drinking water',
    1.50,
    'Pure mineral water in a convenient 600ml bottle. Stay hydrated throughout the day.',
    'uploads/products/p004-water.jpg'
),
(
    'SKU-005',
    '4006381333931',
    'Chocolate Wafer Bar',
    'chocolate, wafer, candy bar, sweet snack',
    2.80,
    'Crispy wafer layers covered in smooth milk chocolate. A sweet treat for chocolate lovers.',
    'uploads/products/p005-chocolate.jpg'
),
(
    'SKU-006',
    NULL,
    'Organic Hand Sanitizer',
    'hand sanitizer, sanitizer gel, alcohol gel, hygiene',
    8.90,
    'Alcohol-based hand sanitizer with aloe vera. Kills 99.9% of germs without harsh residue.',
    'uploads/products/p006-sanitizer.jpg'
);
