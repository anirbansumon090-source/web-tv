<?php
require_once __DIR__ . '/security.php';

$payload = [];
try {
    $payload = get_request_payload() ?? [];
} catch (Exception $e) {
    // Ignore error if plain request
}

$action = $payload['action'] ?? $_GET['action'] ?? $_POST['action'] ?? 'list';

if ($action === 'send') {
    $title = trim($payload['title'] ?? $_POST['title'] ?? '');
    $message = trim($payload['message'] ?? $_POST['message'] ?? '');
    $targetUsername = trim($payload['target_username'] ?? $_POST['target_username'] ?? '');
    $targetPackage = trim($payload['target_package'] ?? $_POST['target_package'] ?? '');
    $type = trim($payload['type'] ?? $_POST['type'] ?? 'SYSTEM');
    $actionText = trim($payload['action_text'] ?? $_POST['action_text'] ?? 'View');

    if (empty($title) || empty($message)) {
        send_secure_response([
            'status' => 'error',
            'message' => 'Title and message are required parameters.'
        ], 400);
    }

    $db->execute("INSERT INTO notifications (title, message, target_username, target_package, type, action_text) VALUES (:t, :m, :u, :p, :tp, :at)", [
        't' => $title,
        'm' => $message,
        'u' => $targetUsername,
        'p' => $targetPackage,
        'tp' => $type,
        'at' => $actionText
    ]);

    send_secure_response([
        'status' => 'success',
        'message' => 'Notification created/sent successfully.',
        'notification_id' => $db->lastInsertId()
    ]);
} else {
    // Action == 'list'
    $username = trim($payload['username'] ?? $_GET['username'] ?? '');
    $package = trim($payload['package'] ?? $_GET['package'] ?? '');
    $sessionToken = trim($payload['session_token'] ?? $_GET['session_token'] ?? '');

    if (!empty($sessionToken)) {
        $user = $db->fetchOne("SELECT username, package FROM users WHERE session_token = :s", ['s' => $sessionToken]);
        if ($user) {
            if (empty($username)) $username = $user['username'];
            if (empty($package)) $package = $user['package'];
        }
    }

    $rows = $db->fetchAll("SELECT id, title, message, target_username, target_package, type, action_text, created_at 
        FROM notifications 
        WHERE (target_username = '' OR LOWER(target_username) = 'all' OR LOWER(target_username) = LOWER(:u))
          AND (target_package = '' OR LOWER(target_package) = 'all' OR LOWER(target_package) = LOWER(:p))
        ORDER BY id DESC", [
            'u' => $username,
            'p' => $package
        ]);

    $result = [];
    foreach ($rows as $row) {
        $result[] = [
            'id' => 'server_notif_' . $row['id'],
            'title' => $row['title'],
            'message' => $row['message'],
            'target_username' => $row['target_username'],
            'target_package' => $row['target_package'],
            'time' => date('Y-m-d H:i', strtotime($row['created_at'] ?? 'now')),
            'type' => !empty($row['type']) ? $row['type'] : 'SYSTEM',
            'action_text' => !empty($row['action_text']) ? $row['action_text'] : 'View'
        ];
    }

    send_secure_response([
        'status' => 'success',
        'username' => $username,
        'package' => $package,
        'notifications' => $result
    ]);
}
