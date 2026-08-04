<?php
require_once __DIR__ . '/config.php';

// Handle Admin Authentication
$adminUser = 'admin';
$adminPass = '123456';

$loginError = '';
if (isset($_POST['admin_login'])) {
    $u = trim($_POST['username'] ?? '');
    $p = trim($_POST['password'] ?? '');
    if ($u === $adminUser && $p === $adminPass) {
        $_SESSION['admin_logged_in'] = true;
        header("Location: admin.php");
        exit();
    } else {
        $loginError = 'Invalid admin credentials! Try admin / 123456';
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['admin_logged_in']);
    header("Location: admin.php");
    exit();
}

$isLoggedIn = !empty($_SESSION['admin_logged_in']);

// Handle Actions (POST requests when logged in)
$actionMsg = '';
$actionError = '';

if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['post_action'] ?? '';

    if ($postAction === 'send_notification') {
        $title = trim($_POST['notif_title'] ?? '');
        $message = trim($_POST['notif_message'] ?? '');
        $targetUser = trim($_POST['notif_target_user'] ?? '');
        $targetPkg = trim($_POST['notif_target_pkg'] ?? '');
        $type = trim($_POST['notif_type'] ?? 'SYSTEM');
        $actionText = trim($_POST['notif_action_text'] ?? 'View');

        if (!empty($title) && !empty($message)) {
            $db->execute("INSERT INTO notifications (title, message, target_username, target_package, type, action_text) VALUES (:t, :m, :u, :p, :tp, :at)", [
                't' => $title,
                'm' => $message,
                'u' => $targetUser,
                'p' => $targetPkg,
                'tp' => $type,
                'at' => $actionText
            ]);
            $actionMsg = "Notification successfully sent to server!";
        } else {
            $actionError = "Title and Message cannot be empty.";
        }
    } else if ($postAction === 'delete_notification') {
        $nid = intval($_POST['notif_id'] ?? 0);
        if ($nid > 0) {
            $db->execute("DELETE FROM notifications WHERE id = :id", ['id' => $nid]);
            $actionMsg = "Notification #{$nid} deleted.";
        }
    } else if ($postAction === 'add_user') {
        $u = trim($_POST['user_name'] ?? '');
        $p = trim($_POST['user_pass'] ?? '');
        $pkg = trim($_POST['user_pkg'] ?? 'Basic Plan');
        $exp = trim($_POST['user_exp'] ?? '2026-12-31');

        if (!empty($u) && !empty($p)) {
            try {
                $db->execute("INSERT INTO users (username, password, package, expiry_date) VALUES (:u, :p, :pkg, :exp)", [
                    'u' => $u, 'p' => $p, 'pkg' => $pkg, 'exp' => $exp
                ]);
                $actionMsg = "User '{$u}' created successfully!";
            } catch (Exception $e) {
                $actionError = "Failed to add user: Username may already exist.";
            }
        }
    } else if ($postAction === 'unbind_user') {
        $uid = intval($_POST['user_id'] ?? 0);
        if ($uid > 0) {
            $db->execute("UPDATE users SET bound_device_id = NULL, session_token = NULL WHERE id = :id", ['id' => $uid]);
            $actionMsg = "Device unbound and session reset for user ID #{$uid}.";
        }
    } else if ($postAction === 'delete_user') {
        $uid = intval($_POST['user_id'] ?? 0);
        if ($uid > 0) {
            $db->execute("DELETE FROM users WHERE id = :id", ['id' => $uid]);
            $actionMsg = "User ID #{$uid} deleted.";
        }
    } else if ($postAction === 'add_channel') {
        $name = trim($_POST['chan_name'] ?? '');
        $logo = trim($_POST['chan_logo'] ?? '');
        $stream = trim($_POST['chan_stream'] ?? '');
        $catId = intval($_POST['chan_cat'] ?? 1);
        $isPrem = isset($_POST['chan_premium']) ? 1 : 0;
        $sType = trim($_POST['chan_type'] ?? 'hls');

        if (!empty($name) && !empty($stream)) {
            $db->execute("INSERT INTO channels (name, logo_url, stream_url, category_id, is_premium, stream_type) VALUES (:n, :l, :s, :c, :p, :st)", [
                'n' => $name, 'l' => $logo, 's' => $stream, 'c' => $catId, 'p' => $isPrem, 'st' => $sType
            ]);
            $actionMsg = "Channel '{$name}' added successfully!";
        }
    } else if ($postAction === 'delete_channel') {
        $cid = intval($_POST['chan_id'] ?? 0);
        if ($cid > 0) {
            $db->execute("DELETE FROM channels WHERE id = :id", ['id' => $cid]);
            $actionMsg = "Channel #{$cid} removed.";
        }
    } else if ($postAction === 'add_category') {
        $cName = trim($_POST['cat_name'] ?? '');
        $cIcon = trim($_POST['cat_icon'] ?? 'ic_tv');

        if (!empty($cName)) {
            $db->execute("INSERT INTO categories (name, icon) VALUES (:n, :i)", ['n' => $cName, 'i' => $cIcon]);
            $actionMsg = "Category '{$cName}' created!";
        }
    }
}

// Fetch Data for Admin Dashboard
$allUsers = $isLoggedIn ? $db->fetchAll("SELECT * FROM users ORDER BY id DESC") : [];
$allNotifs = $isLoggedIn ? $db->fetchAll("SELECT * FROM notifications ORDER BY id DESC") : [];
$allChannels = $isLoggedIn ? $db->fetchAll("SELECT c.*, cat.name as cat_name FROM channels c LEFT JOIN categories cat ON c.category_id = cat.id ORDER BY c.id DESC") : [];
$allCategories = $isLoggedIn ? $db->fetchAll("SELECT * FROM categories ORDER BY id ASC") : [];
$allReports = $isLoggedIn ? $db->fetchAll("SELECT * FROM reports ORDER BY id DESC LIMIT 50") : [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTT KING IPTV - Web Admin Control Panel</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #0f172a; color: #f8fafc; font-family: 'Segoe UI', system-ui, sans-serif; }
        .card { background-color: #1e293b; border: 1px solid #334155; color: #f8fafc; border-radius: 12px; }
        .table { color: #f8fafc; }
        .table-dark { --bs-table-bg: #1e293b; --bs-table-border-color: #334155; }
        .form-control, .form-select { background-color: #0f172a; border: 1px solid #334155; color: #f8fafc; }
        .form-control:focus, .form-select:focus { background-color: #1e293b; color: #fff; border-color: #6366f1; box-shadow: 0 0 0 0.25rem rgba(99, 102, 241, 0.25); }
        .nav-tabs .nav-link { color: #94a3b8; border: none; font-weight: 600; padding: 12px 20px; }
        .nav-tabs .nav-link.active { color: #38bdf8; background-color: #1e293b; border-bottom: 3px solid #38bdf8; }
        .btn-primary { background-color: #6366f1; border-color: #6366f1; }
        .btn-primary:hover { background-color: #4f46e5; border-color: #4f46e5; }
        .btn-success { background-color: #10b981; border-color: #10b981; }
        .badge-system { background-color: #3b82f6; }
        .badge-update { background-color: #f59e0b; }
        .badge-user { background-color: #8b5cf6; }
        .badge-vip { background-color: #ec4899; }
    </style>
</head>
<body>

<?php if (!$isLoggedIn): ?>
<!-- Admin Login View -->
<div class="container d-flex align-items-center justify-content-center min-vh-100">
    <div class="card p-4 shadow-lg" style="max-width: 420px; width: 100%;">
        <div class="text-center mb-4">
            <i class="bi bi-shield-lock-fill text-primary display-4"></i>
            <h3 class="mt-2 fw-bold">OTT KING IPTV</h3>
            <p class="text-muted small">Server & Notification Control Panel</p>
        </div>

        <?php if (!empty($loginError)): ?>
            <div class="alert alert-danger py-2 small"><?= htmlspecialchars($loginError) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label text-muted small">Admin Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-person-fill"></i></span>
                    <input type="text" name="username" class="form-control" value="admin" required>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label text-muted small">Admin Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-key-fill"></i></span>
                    <input type="password" name="password" class="form-control" value="123456" required>
                </div>
            </div>
            <button type="submit" name="admin_login" class="btn btn-primary w-100 py-2 fw-bold">
                <i class="bi bi-box-arrow-in-right me-1"></i> Login to Admin Panel
            </button>
        </form>
        <div class="text-center mt-3 text-muted small">
            Default Credentials: <code>admin</code> / <code>123456</code>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Admin Dashboard Main View -->
<nav class="navbar navbar-dark bg-dark border-bottom border-secondary px-4">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="#">
            <i class="bi bi-tv text-info fs-4"></i>
            <span>OTT KING Server Panel</span>
        </a>
        <div class="d-flex align-items-center gap-3">
            <span class="badge bg-secondary p-2 small">
                <i class="bi bi-database-check me-1"></i>
                DB Mode: <strong><?= $db->isMysqli() ? 'MySQLi / phpMyAdmin' : 'SQLite Embedded' ?></strong>
            </span>
            <a href="schema.sql" download class="btn btn-sm btn-outline-info">
                <i class="bi bi-download me-1"></i> Download MySQL schema.sql
            </a>
            <a href="?action=logout" class="btn btn-sm btn-danger">
                <i class="bi bi-power me-1"></i> Logout
            </a>
        </div>
    </div>
</nav>

<div class="container-fluid py-4 px-4">

    <?php if (!empty($actionMsg)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($actionMsg) ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($actionError)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($actionError) ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Summary Quick Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card p-3 d-flex flex-row align-items-center justify-content-between">
                <div>
                    <span class="text-muted small">Total Users</span>
                    <h3 class="fw-bold mb-0 text-info"><?= count($allUsers) ?></h3>
                </div>
                <i class="bi bi-people-fill fs-1 text-info opacity-50"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-3 d-flex flex-row align-items-center justify-content-between">
                <div>
                    <span class="text-muted small">Total Channels</span>
                    <h3 class="fw-bold mb-0 text-success"><?= count($allChannels) ?></h3>
                </div>
                <i class="bi bi-play-btn-fill fs-1 text-success opacity-50"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-3 d-flex flex-row align-items-center justify-content-between">
                <div>
                    <span class="text-muted small">Notifications Sent</span>
                    <h3 class="fw-bold mb-0 text-warning"><?= count($allNotifs) ?></h3>
                </div>
                <i class="bi bi-bell-fill fs-1 text-warning opacity-50"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-3 d-flex flex-row align-items-center justify-content-between">
                <div>
                    <span class="text-muted small">User Reports</span>
                    <h3 class="fw-bold mb-0 text-danger"><?= count($allReports) ?></h3>
                </div>
                <i class="bi bi-chat-square-text-fill fs-1 text-danger opacity-50"></i>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mb-4" id="adminTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" id="notif-tab" data-bs-toggle="tab" data-bs-target="#notif-panel" type="button">
                <i class="bi bi-send-fill me-1"></i> Send Notifications
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#users-panel" type="button">
                <i class="bi bi-person-gear me-1"></i> Manage Users
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="channels-tab" data-bs-toggle="tab" data-bs-target="#channels-panel" type="button">
                <i class="bi bi-tv-fill me-1"></i> Channels & Categories
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports-panel" type="button">
                <i class="bi bi-envelope-exclamation-fill me-1"></i> User Reports
            </button>
        </li>
    </ul>

    <div class="tab-content" id="adminTabsContent">
        
        <!-- Tab 1: Send & Manage Notifications -->
        <div class="tab-pane fade show active" id="notif-panel">
            <div class="row g-4">
                <!-- Send Notification Form -->
                <div class="col-lg-4">
                    <div class="card p-4">
                        <h5 class="fw-bold mb-3 text-info"><i class="bi bi-broadcast me-2"></i>Create Notification</h5>
                        <form method="POST">
                            <input type="hidden" name="post_action" value="send_notification">
                            
                            <div class="mb-3">
                                <label class="form-label text-muted small">Title *</label>
                                <input type="text" name="notif_title" class="form-control" placeholder="e.g., Exclusive VIP Live Match" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label text-muted small">Message Content *</label>
                                <textarea name="notif_message" class="form-control" rows="3" placeholder="Enter message text for app notification..." required></textarea>
                            </div>

                            <div class="row mb-3">
                                <div class="col-6">
                                    <label class="form-label text-muted small">Target Username</label>
                                    <select name="notif_target_user" class="form-select">
                                        <option value="">ALL Users (Broadcast)</option>
                                        <?php foreach ($allUsers as $u): ?>
                                            <option value="<?= htmlspecialchars($u['username']) ?>">User: <?= htmlspecialchars($u['username']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label text-muted small">Target Package</label>
                                    <select name="notif_target_pkg" class="form-select">
                                        <option value="">ALL Packages</option>
                                        <option value="VIP Premium Ultra">VIP Premium Ultra</option>
                                        <option value="Basic Plan">Basic Plan</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-6">
                                    <label class="form-label text-muted small">Notification Type</label>
                                    <select name="notif_type" class="form-select">
                                        <option value="SYSTEM">SYSTEM</option>
                                        <option value="UPDATE">UPDATE</option>
                                        <option value="CHANNEL">CHANNEL</option>
                                        <option value="USER">USER</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label text-muted small">Button Action Text</label>
                                    <input type="text" name="notif_action_text" class="form-control" value="View">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
                                <i class="bi bi-send-check-fill me-1"></i> Broadcast Notification
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Notifications List -->
                <div class="col-lg-8">
                    <div class="card p-4">
                        <h5 class="fw-bold mb-3"><i class="bi bi-bell-fill me-2 text-warning"></i>Sent Server Notifications</h5>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title & Message</th>
                                        <th>Target</th>
                                        <th>Type</th>
                                        <th>Time</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($allNotifs)): ?>
                                        <tr><td colspan="6" class="text-center text-muted py-4">No server notifications sent yet.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($allNotifs as $n): ?>
                                            <tr>
                                                <td class="text-muted">#<?= $n['id'] ?></td>
                                                <td>
                                                    <div class="fw-bold"><?= htmlspecialchars($n['title']) ?></div>
                                                    <div class="small text-muted"><?= htmlspecialchars($n['message']) ?></div>
                                                </td>
                                                <td>
                                                    <?php if (empty($n['target_username']) && empty($n['target_package'])): ?>
                                                        <span class="badge bg-secondary">Global ALL</span>
                                                    <?php else: ?>
                                                        <?php if (!empty($n['target_username'])): ?>
                                                            <span class="badge badge-user">User: <?= htmlspecialchars($n['target_username']) ?></span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($n['target_package'])): ?>
                                                            <span class="badge badge-vip">Pkg: <?= htmlspecialchars($n['target_package']) ?></span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?= htmlspecialchars($n['type']) ?></span>
                                                </td>
                                                <td class="small text-muted"><?= htmlspecialchars($n['created_at']) ?></td>
                                                <td>
                                                    <form method="POST" onsubmit="return confirm('Delete this notification?');">
                                                        <input type="hidden" name="post_action" value="delete_notification">
                                                        <input type="hidden" name="notif_id" value="<?= $n['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 2: Manage Users -->
        <div class="tab-pane fade" id="users-panel">
            <div class="card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-people-fill me-2 text-info"></i>Registered IPTV Users</h5>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="bi bi-person-plus-fill me-1"></i> Add New User
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Password</th>
                                <th>Subscription Package</th>
                                <th>Expiry Date</th>
                                <th>Bound Device</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allUsers as $u): ?>
                                <tr>
                                    <td>#<?= $u['id'] ?></td>
                                    <td class="fw-bold text-info"><?= htmlspecialchars($u['username']) ?></td>
                                    <td><code><?= htmlspecialchars($u['password']) ?></code></td>
                                    <td>
                                        <span class="badge <?= $u['package'] === 'VIP Premium Ultra' ? 'badge-vip' : 'bg-primary' ?>">
                                            <?= htmlspecialchars($u['package']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($u['expiry_date']) ?></td>
                                    <td class="small">
                                        <?php if (!empty($u['bound_device_id'])): ?>
                                            <span class="text-success"><i class="bi bi-phone-fill me-1"></i><?= substr($u['bound_device_id'], 0, 10) ?>...</span>
                                        <?php else: ?>
                                            <span class="text-muted"><i class="bi bi-phone-vibrate me-1"></i>Unbound</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <?php if (!empty($u['bound_device_id'])): ?>
                                                <form method="POST">
                                                    <input type="hidden" name="post_action" value="unbind_user">
                                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-warning text-nowrap py-0 px-2" title="Reset Device Binding">
                                                        <i class="bi bi-arrow-counterclockwise"></i> Unbind Device
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" onsubmit="return confirm('Delete user <?= htmlspecialchars($u['username']) ?>?');">
                                                <input type="hidden" name="post_action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab 3: Channels & Categories -->
        <div class="tab-pane fade" id="channels-panel">
            <div class="row g-4">
                <!-- Add Channel -->
                <div class="col-lg-4">
                    <div class="card p-4 mb-4">
                        <h5 class="fw-bold mb-3 text-success"><i class="bi bi-plus-circle me-2"></i>Add Live Stream Channel</h5>
                        <form method="POST">
                            <input type="hidden" name="post_action" value="add_channel">
                            
                            <div class="mb-3">
                                <label class="form-label text-muted small">Channel Name *</label>
                                <input type="text" name="chan_name" class="form-control" placeholder="e.g., Sports 4K Live" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Stream URL (m3u8 / mp4) *</label>
                                <input type="url" name="chan_stream" class="form-control" placeholder="https://..." required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Logo URL</label>
                                <input type="url" name="chan_logo" class="form-control" placeholder="https://...">
                            </div>
                            <div class="row mb-3">
                                <div class="col-6">
                                    <label class="form-label text-muted small">Category</label>
                                    <select name="chan_cat" class="form-select">
                                        <?php foreach ($allCategories as $cat): ?>
                                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label text-muted small">Stream Format</label>
                                    <select name="chan_type" class="form-select">
                                        <option value="hls">HLS (.m3u8)</option>
                                        <option value="ts">MPEG-TS (.ts/.mp4)</option>
                                        <option value="dash">DASH (.mpd)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="chan_premium" id="premCheck">
                                <label class="form-check-label text-warning small" for="premCheck">
                                    VIP Subscriber Only Channel
                                </label>
                            </div>
                            <button type="submit" class="btn btn-success w-100 py-2 fw-bold">
                                <i class="bi bi-check-lg me-1"></i> Save Channel
                            </button>
                        </form>
                    </div>

                    <!-- Add Category -->
                    <div class="card p-4">
                        <h5 class="fw-bold mb-3 text-info"><i class="bi bi-folder-plus me-2"></i>Add Category</h5>
                        <form method="POST">
                            <input type="hidden" name="post_action" value="add_category">
                            <div class="mb-3">
                                <label class="form-label text-muted small">Category Name</label>
                                <input type="text" name="cat_name" class="form-control" placeholder="e.g., Live Cricket" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Icon Keyword</label>
                                <input type="text" name="cat_icon" class="form-control" value="ic_tv">
                            </div>
                            <button type="submit" class="btn btn-info w-100 py-2 fw-bold">Add Category</button>
                        </form>
                    </div>
                </div>

                <!-- Channels Table -->
                <div class="col-lg-8">
                    <div class="card p-4">
                        <h5 class="fw-bold mb-3"><i class="bi bi-play-btn-fill me-2 text-success"></i>Channel List</h5>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Channel</th>
                                        <th>Category</th>
                                        <th>Format</th>
                                        <th>Access</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allChannels as $ch): ?>
                                        <tr>
                                            <td>#<?= $ch['id'] ?></td>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($ch['name']) ?></div>
                                                <div class="small text-muted text-truncate" style="max-width: 280px;"><?= htmlspecialchars($ch['stream_url']) ?></div>
                                            </td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($ch['cat_name'] ?? 'General') ?></span></td>
                                            <td><code><?= strtoupper(htmlspecialchars($ch['stream_type'] ?? 'HLS')) ?></code></td>
                                            <td>
                                                <?php if ($ch['is_premium']): ?>
                                                    <span class="badge badge-vip">VIP</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Free</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="POST" onsubmit="return confirm('Delete channel?');">
                                                    <input type="hidden" name="post_action" value="delete_channel">
                                                    <input type="hidden" name="chan_id" value="<?= $ch['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 4: User Reports -->
        <div class="tab-pane fade" id="reports-panel">
            <div class="card p-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-chat-square-text-fill me-2 text-danger"></i>User Submitted Feedback & Stream Issue Reports</h5>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Issue Category</th>
                                <th>Description</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($allReports)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No issue reports submitted.</td></tr>
                            <?php else: ?>
                                <?php foreach ($allReports as $rep): ?>
                                    <tr>
                                        <td>#<?= $rep['id'] ?></td>
                                        <td class="fw-bold text-info"><?= htmlspecialchars($rep['username']) ?></td>
                                        <td><span class="badge bg-warning text-dark"><?= htmlspecialchars($rep['category']) ?></span></td>
                                        <td><?= htmlspecialchars($rep['description']) ?></td>
                                        <td class="small text-muted"><?= htmlspecialchars($rep['timestamp']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- Modal: Add User -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-2"></i>Add IPTV User Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="post_action" value="add_user">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Username *</label>
                        <input type="text" name="user_name" class="form-control" placeholder="e.g. user2" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Password *</label>
                        <input type="text" name="user_pass" class="form-control" value="123456" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Subscription Package</label>
                        <select name="user_pkg" class="form-select">
                            <option value="VIP Premium Ultra">VIP Premium Ultra</option>
                            <option value="Basic Plan">Basic Plan</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Expiry Date</label>
                        <input type="date" name="user_exp" class="form-control" value="2026-12-31">
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">Create User Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php endif; ?>

</body>
</html>
