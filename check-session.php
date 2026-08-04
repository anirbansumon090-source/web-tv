<?php
require_once __DIR__ . '/security.php';

$input = get_request_payload();

$sessionToken = $input['session_token'] ?? '';
$deviceId = $input['device_id'] ?? '';

if (empty($sessionToken)) {
    send_secure_response(["status" => "error", "message" => "No active session"], 401);
}

$user = $db->fetchOne("SELECT * FROM users WHERE session_token = :s", ['s' => $sessionToken]);

if (!$user) {
    send_secure_response(["status" => "error", "message" => "Invalid session token"], 401);
}

if (!empty($user['bound_device_id']) && $user['bound_device_id'] !== $deviceId) {
    send_secure_response(["status" => "error", "message" => "Session invalidated on this device"], 403);
}

send_secure_response([
    "status" => "success",
    "valid" => true,
    "user_info" => [
        "username" => $user['username'],
        "package" => $user['package'],
        "expiry_date" => $user['expiry_date']
    ]
]);
