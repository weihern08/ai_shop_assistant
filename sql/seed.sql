-- AI Smart Shopping Assistant — Seed Data
-- Default admin password: Admin@12345  (change immediately after install)

SET NAMES utf8mb4;

INSERT INTO users (email, password_hash, role)
VALUES (
    'admin@shop.local',
    '$2y$12$2psSQrDb6Lub.jIREK9Ye..HNtsEqinadC7SP30XLQrd6OL4fiGb2',
    'admin'
)
ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = VALUES(role);

INSERT INTO settings (setting_key, setting_value) VALUES
    ('ai_provider', 'gemini'),
    ('agnes_api_url', 'https://api.agnes-ai.com/v1'),
    ('agnes_api_key', ''),
    ('agnes_model', 'agnes-2.5-flash'),
    ('agnes_fallback_models', ''),
    ('gemini_api_url', 'https://generativelanguage.googleapis.com/v1beta'),
    ('gemini_api_key', ''),
    ('gemini_model', 'gemini-3.6-flash'),
    ('gemini_fallback_models', 'gemini-3.8-flash,gemini-3.7-flash')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

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
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    image_path = VALUES(image_path),
    detect_keywords = VALUES(detect_keywords),
    price = VALUES(price),
    description = VALUES(description);
