<?php
require_once __DIR__ . '/security.php';

$input = get_request_payload();

if (!$input) {
    send_secure_response(["status" => "error", "message" => "Invalid report payload"], 400);
}

$username = $input['username'] ?? 'Anonymous';
$category = $input['category'] ?? 'General Issue';
$description = $input['description'] ?? '';

$db->execute("INSERT INTO reports (username, category, description) VALUES (:u, :c, :d)", [
    'u' => $username,
    'c' => $category,
    'd' => $description
]);

send_secure_response([
    "status" => "success",
    "message" => "Problem report submitted successfully! Our tech team will investigate."
]);
