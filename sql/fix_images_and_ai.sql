-- Fix product images + Agnes API (correct host) — run in phpMyAdmin once

UPDATE products SET image_path = 'uploads/products/p001-inhaler.jpg'   WHERE sku = 'SKU-001';
UPDATE products SET image_path = 'uploads/products/p002-greentea.jpg'  WHERE sku = 'SKU-002';
UPDATE products SET image_path = 'uploads/products/p003-chips.jpg'     WHERE sku = 'SKU-003';
UPDATE products SET image_path = 'uploads/products/p004-water.jpg'     WHERE sku = 'SKU-004';
UPDATE products SET image_path = 'uploads/products/p005-chocolate.jpg' WHERE sku = 'SKU-005';
UPDATE products SET image_path = 'uploads/products/p006-sanitizer.jpg' WHERE sku = 'SKU-006';

INSERT INTO settings (setting_key, setting_value) VALUES
    ('ai_provider', 'agnes'),
    ('agnes_api_url', 'https://apihub.agnes-ai.com/v1'),
    ('agnes_api_key', ''),
    ('agnes_model', 'agnes-3.0-flash'),
    ('agnes_fallback_models', 'agnes-2.5-flash'),
    ('gemini_model', 'gemini-3.6-flash'),
    ('gemini_fallback_models', 'gemini-3.7-flash,gemini-3.8-flash')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
