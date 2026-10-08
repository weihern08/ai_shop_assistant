-- Run on existing cPanel DB if products already imported without images
UPDATE products SET image_path = 'uploads/products/p001-inhaler.jpg'   WHERE sku = 'SKU-001';
UPDATE products SET image_path = 'uploads/products/p002-greentea.jpg'  WHERE sku = 'SKU-002';
UPDATE products SET image_path = 'uploads/products/p003-chips.jpg'     WHERE sku = 'SKU-003';
UPDATE products SET image_path = 'uploads/products/p004-water.jpg'     WHERE sku = 'SKU-004';
UPDATE products SET image_path = 'uploads/products/p005-chocolate.jpg' WHERE sku = 'SKU-005';
UPDATE products SET image_path = 'uploads/products/p006-sanitizer.jpg' WHERE sku = 'SKU-006';
