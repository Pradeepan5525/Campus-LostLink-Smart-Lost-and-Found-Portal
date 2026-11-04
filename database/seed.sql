-- =====================================================
-- Campus LostLink: PostgreSQL Database Seed Data
-- =====================================================

-- 1. Insert Categories
INSERT INTO categories (id, name) VALUES
(1, 'Wallet'),
(2, 'Mobile Phone'),
(3, 'Keys'),
(4, 'Laptop'),
(5, 'Books'),
(6, 'Bottle'),
(7, 'Bag'),
(8, 'ID Card'),
(9, 'Other')
ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name;

SELECT setval('categories_id_seq', (SELECT MAX(id) FROM categories));

-- 2. Insert Locations
INSERT INTO locations (id, name) VALUES
(1, 'Library'),
(2, 'CSE Block'),
(3, 'Canteen'),
(4, 'Main Gate'),
(5, 'Lab'),
(6, 'Hostel'),
(7, 'Auditorium')
ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name;

SELECT setval('locations_id_seq', (SELECT MAX(id) FROM locations));

-- 3. Insert Users
-- Admin: admin@lostlink.com / Admin@123
-- Students: rahul@lostlink.com, priya@lostlink.com, rohan@lostlink.com, arun@lostlink.com / Student@123
INSERT INTO users (id, full_name, email, password_hash, role, is_active, created_at) VALUES
(1, 'System Administrator', 'admin@lostlink.com', '$2y$10$BnXBsg4mb0AA8ShsRKBYVuTbswARUPrkryNAdzizZyueAa68j4jYG', 'admin', true, '2025-01-01 09:00:00+00'),
(2, 'Rahul Sharma', 'rahul@lostlink.com', '$2y$10$l36gBa1naEOUua6XPq0cUeLlRIyDXPwACKCMya/hZYPWmCE5TF6JK', 'student', true, '2025-01-15 10:00:00+00'),
(3, 'Priya Singh', 'priya@lostlink.com', '$2y$10$l36gBa1naEOUua6XPq0cUeLlRIyDXPwACKCMya/hZYPWmCE5TF6JK', 'student', true, '2025-01-20 11:30:00+00'),
(4, 'Rohan Patel', 'rohan@lostlink.com', '$2y$10$l36gBa1naEOUua6XPq0cUeLlRIyDXPwACKCMya/hZYPWmCE5TF6JK', 'student', true, '2025-02-01 14:15:00+00'),
(5, 'Arun Kumar', 'arun@lostlink.com', '$2y$10$l36gBa1naEOUua6XPq0cUeLlRIyDXPwACKCMya/hZYPWmCE5TF6JK', 'student', true, '2025-02-10 16:45:00+00')
ON CONFLICT (id) DO UPDATE SET 
    full_name = EXCLUDED.full_name,
    email = EXCLUDED.email,
    password_hash = EXCLUDED.password_hash,
    role = EXCLUDED.role;

SELECT setval('users_id_seq', (SELECT MAX(id) FROM users));

-- 4. Insert Items
INSERT INTO items (id, user_id, type, item_name, category_id, location_id, event_date, description, image_path, status, created_at) VALUES
(1, 5, 'found', 'Wallet', 1, 1, '2025-04-25', 'Black leather bifold wallet with campus card and few cash notes found near study cubicles.', 'assets/img/items/wallet.svg', 'pending', '2025-04-25 10:15:00+00'),
(2, 2, 'lost', 'Mobile Phone', 2, 2, '2025-04-24', 'Midnight blue Samsung smartphone with a transparent protective case lost during lab hours.', 'assets/img/items/mobile.svg', 'available', '2025-04-24 14:30:00+00'),
(3, 3, 'found', 'Water Bottle', 6, 3, '2025-04-23', 'Matte black stainless steel insulated water bottle left on canteen table 4.', 'assets/img/items/bottle.svg', 'available', '2025-04-23 12:45:00+00'),
(4, 4, 'lost', 'Keys', 3, 4, '2025-04-22', 'Set of 3 silver door keys with a blue mini sneaker keychain lost near the security gate.', 'assets/img/items/keys.svg', 'available', '2025-04-22 09:20:00+00'),
(5, 2, 'lost', 'Laptop', 4, 5, '2025-04-20', 'A grey color laptop with a black sleeve. It has a few stickers on the back.', 'assets/img/items/laptop.svg', 'available', '2025-04-20 16:00:00+00'),
(6, 3, 'lost', 'Books', 5, 1, '2025-04-18', 'Database Management Systems 8th Edition textbook along with a spiral notebook.', 'assets/img/items/books.svg', 'available', '2025-04-18 11:10:00+00'),
(7, 4, 'found', 'Backpack', 7, 6, '2025-04-22', 'Navy blue Wildcraft college backpack left in hostel common study room.', 'assets/img/items/bag.svg', 'claimed', '2025-04-22 17:30:00+00'),
(8, 5, 'found', 'Student ID Card', 8, 4, '2025-04-26', 'Official campus student smart card found on the walkway near the main gate entrance.', 'assets/img/items/idcard.svg', 'available', '2025-04-26 08:45:00+00'),
(9, 2, 'lost', 'Smart Watch', 9, 5, '2025-04-21', 'Black fitness smartwatch with silicone wristband left beside Lab PC 18.', 'assets/img/items/watch.svg', 'available', '2025-04-21 15:20:00+00'),
(10, 3, 'found', 'Scientific Calculator', 9, 2, '2025-04-22', 'Casio fx-991EX scientific calculator with solar panel, name initial etched on backside.', 'assets/img/items/calc.svg', 'available', '2025-04-22 13:00:00+00'),
(11, 4, 'lost', 'Earbuds', 9, 3, '2025-04-24', 'White wireless earbuds in charging case lost at Canteen second floor.', 'assets/img/items/earbuds.svg', 'available', '2025-04-24 18:10:00+00'),
(12, 5, 'found', 'Umbrella', 9, 7, '2025-04-19', 'Compact foldable black umbrella found on auditorium seat row G.', 'assets/img/items/umbrella.svg', 'returned', '2025-04-19 16:40:00+00'),
(13, 2, 'found', 'Grey Laptop', 4, 5, '2025-04-21', 'Found a silver/grey laptop in black protective sleeve left on computer table in Lab 3.', 'assets/img/items/laptop.svg', 'available', '2025-04-21 17:00:00+00')
ON CONFLICT (id) DO UPDATE SET
    item_name = EXCLUDED.item_name,
    description = EXCLUDED.description,
    status = EXCLUDED.status;

SELECT setval('items_id_seq', (SELECT MAX(id) FROM items));

-- 5. Insert Claims
INSERT INTO claims (id, item_id, claimant_id, proof_description, status, verified_by, created_at, updated_at) VALUES
(1, 1, 2, 'Brown/black leather bifold wallet with my college ID (Rahul Sharma) and SBI debit card ending in 4120.', 'pending', NULL, '2025-04-25 14:00:00+00', '2025-04-25 14:00:00+00'),
(2, 7, 3, 'My blue Wildcraft bag containing engineering mathematics 2 notes with name Priya Singh on the cover.', 'approved', 1, '2025-04-23 10:00:00+00', '2025-04-24 09:30:00+00'),
(3, 12, 4, 'Black umbrella with curved wooden handle and automatic open button.', 'approved', 1, '2025-04-20 11:00:00+00', '2025-04-20 15:00:00+00')
ON CONFLICT (id) DO UPDATE SET
    status = EXCLUDED.status,
    proof_description = EXCLUDED.proof_description;

SELECT setval('claims_id_seq', (SELECT MAX(id) FROM claims));