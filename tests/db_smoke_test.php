<?php
require_once __DIR__ . '/../config.php';

function assertTrue($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "ASSERTION FAILED: $message\n");
        exit(1);
    }
}

$db = new AppDatabase();

$db->execute("DELETE FROM users");
$db->execute("DELETE FROM notifications");
$db->execute("DELETE FROM categories");
$db->execute("DELETE FROM channels");

$userRow = $db->fetchOne("SELECT username, password FROM users WHERE username = 'admin'");
$categoryRows = $db->fetchAll("SELECT id, name FROM categories ORDER BY id ASC");
$channelRows = $db->fetchAll("SELECT id, name FROM channels ORDER BY id ASC");
$notificationRows = $db->fetchAll("SELECT id, title FROM notifications ORDER BY id ASC");

assertTrue($userRow === null, 'No default admin user should be created automatically');
assertTrue(count($categoryRows) === 0, 'Categories should remain empty until added from admin');
assertTrue(count($channelRows) === 0, 'Channels should remain empty until added from admin');
assertTrue(count($notificationRows) === 0, 'Notifications should remain empty until added from admin');

echo "DB smoke test passed\n";
