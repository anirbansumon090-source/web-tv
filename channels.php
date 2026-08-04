<?php
require_once __DIR__ . '/security.php';

$sessionToken = $_GET['session_token'] ?? '';
$isSubscriber = false;

if (!empty($sessionToken)) {
    $user = $db->fetchOne("SELECT id FROM users WHERE session_token = :s", ['s' => $sessionToken]);
    if ($user) {
        $isSubscriber = true;
    }
}

if ($isSubscriber) {
    // Return all channels including VIP premium
    $channels = $db->fetchAll("SELECT * FROM channels ORDER BY id ASC");
} else {
    // Return only non-premium free channels
    $channels = $db->fetchAll("SELECT * FROM channels WHERE is_premium = 0 ORDER BY id ASC");
}

send_secure_response([
    "status" => "success",
    "timestamp" => time(),
    "is_vip_subscriber" => $isSubscriber,
    "channels" => $channels
]);
