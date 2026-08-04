<?php
require_once __DIR__ . '/security.php';

$input = get_request_payload();

$sessionToken = $input['session_token'] ?? '';
$deviceId = $input['device_id'] ?? '';
$username = $input['username'] ?? '';

if (empty($sessionToken) && empty($deviceId) && empty($username)) {
    send_secure_response(["status" => "error", "message" => "Session token, device ID, or username required"], 400);
}

$unbound = false;

if (!empty($sessionToken)) {
    $db->execute("UPDATE users SET bound_device_id = NULL, session_token = NULL WHERE session_token = :s", ['s' => $sessionToken]);
    $unbound = true;
}

if (!$unbound && !empty($username)) {
    $db->execute("UPDATE users SET bound_device_id = NULL, session_token = NULL WHERE username = :u", ['u' => $username]);
    $unbound = true;
}

if (!$unbound && !empty($deviceId)) {
    $db->execute("UPDATE users SET bound_device_id = NULL, session_token = NULL WHERE bound_device_id = :d", ['d' => $deviceId]);
}

send_secure_response([
    "status" => "success",
    "message" => "Logged out successfully from server. Device unbound."
]);
