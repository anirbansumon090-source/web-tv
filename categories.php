<?php
require_once __DIR__ . '/security.php';

$categories = $db->fetchAll("SELECT * FROM categories ORDER BY id ASC");

send_secure_response([
    "status" => "success",
    "timestamp" => time(),
    "categories" => $categories
]);
