<?php
require_once __DIR__ . '/../config.php';

function assertTrue($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "ASSERTION FAILED: $message\n");
        exit(1);
    }
}

$db = new AppDatabase();

$userRow = $db->fetchOne("SELECT username, password FROM users WHERE username = 'admin'");
$categoryRows = $db->fetchAll("SELECT id, name FROM categories ORDER BY id ASC");
$channelRows = $db->fetchAll("SELECT id, name FROM channels ORDER BY id ASC");
$notificationRows = $db->fetchAll("SELECT id, title FROM notifications ORDER BY id ASC");

assertTrue(!empty($userRow), 'Admin user should be present in the database');
assertTrue(verify_password('123456', $userRow['password']), 'Admin password should verify against the stored hash');
assertTrue(count($categoryRows) >= 5, 'Default categories should be seeded into the database');
assertTrue(count($channelRows) >= 6, 'Default channels should be seeded into the database');
assertTrue(count($notificationRows) >= 2, 'Default notifications should be seeded into the database');

echo "DB smoke test passed\n";
