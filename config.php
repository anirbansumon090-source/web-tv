<?php
// OTT KING Server Configuration & Security Keys
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Keys
define('API_KEY', 'ott_king_secret_api_key_2026');
define('HMAC_KEY', 'ott_king_hmac_secret_key_998877');
define('ENCRYPTION_KEY', 'ott_king_enc_key_1234567890123456');

// MySQL / phpMyAdmin Configuration Constants
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ottking_db');
define('DB_PORT', 3306);

/**
 * Unified Database Adapter supporting MySQLi and SQLite (fallback)
 */
class AppDatabase {
    private $mysqli = null;
    private $pdo = null;
    private $is_mysqli = false;

    public function __construct() {
        // Attempt MySQLi Connection first (for phpMyAdmin / MySQL environment)
        mysqli_report(MYSQLI_REPORT_OFF);
        try {
            $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
            if (!$conn->connect_error) {
                $this->mysqli = $conn;
                $this->mysqli->set_charset("utf8mb4");
                $this->is_mysqli = true;
                $this->initMysqlTables();
                return;
            }
        } catch (Exception $e) {
            // MySQL unavailable - proceed to SQLite fallback
        }

        // SQLite PDO Fallback for local embedded dev environment
        $db_file = __DIR__ . '/ottking.sqlite';
        $this->pdo = new PDO('sqlite:' . $db_file);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->initSqliteTables();
    }

    public function isMysqli(): bool {
        return $this->is_mysqli;
    }

    public function getMysqli(): ?mysqli {
        return $this->mysqli;
    }

    private function initMysqlTables() {
        if (!$this->mysqli) return;

        $this->mysqli->query("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) UNIQUE,
            password VARCHAR(255),
            package VARCHAR(100) DEFAULT 'Basic Plan',
            expiry_date DATE DEFAULT '2026-12-31',
            bound_device_id VARCHAR(255) DEFAULT NULL,
            session_token VARCHAR(255) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->mysqli->query("CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100),
            icon VARCHAR(50) DEFAULT 'ic_tv'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->mysqli->query("CREATE TABLE IF NOT EXISTS channels (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150),
            logo_url TEXT,
            stream_url TEXT,
            category_id INT DEFAULT 1,
            is_premium TINYINT(1) DEFAULT 0,
            stream_type VARCHAR(20) DEFAULT 'hls'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->mysqli->query("CREATE TABLE IF NOT EXISTS reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) DEFAULT 'Anonymous',
            category VARCHAR(100) DEFAULT 'General Issue',
            description TEXT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->mysqli->query("CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255),
            message TEXT,
            target_username VARCHAR(100) DEFAULT '',
            target_package VARCHAR(100) DEFAULT '',
            type VARCHAR(50) DEFAULT 'SYSTEM',
            action_text VARCHAR(50) DEFAULT 'View',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Seed initial data if empty
        $res = $this->mysqli->query("SELECT COUNT(*) as cnt FROM users");
        if ($res && $res->fetch_assoc()['cnt'] == 0) {
            $this->mysqli->query("INSERT INTO users (username, password, package, expiry_date) VALUES 
                ('admin', '123456', 'VIP Premium Ultra', '2030-12-31'),
                ('user1', '1234', 'Basic Plan', '2026-12-31')");
        }

        $res = $this->mysqli->query("SELECT COUNT(*) as cnt FROM notifications");
        if ($res && $res->fetch_assoc()['cnt'] == 0) {
            $this->mysqli->query("INSERT INTO notifications (title, message, target_username, target_package, type, action_text) VALUES 
                ('Server Upgrade Notice', 'OTT KING Live TV server upgrade completed successfully. All 4K streams are active.', '', '', 'SYSTEM', 'OK'),
                ('VIP Account Special Welcome', 'Exclusive access active for VIP Users! Stream 4K Live Sports & Ultra Movies now.', 'admin', 'VIP Premium Ultra', 'USER', 'Explore VIP')");
        }

        $res = $this->mysqli->query("SELECT COUNT(*) as cnt FROM categories");
        if ($res && $res->fetch_assoc()['cnt'] == 0) {
            $this->mysqli->query("INSERT INTO categories (id, name, icon) VALUES 
                (1, 'All Channels', 'ic_tv'),
                (2, 'Sports Live', 'ic_play'),
                (3, 'News & World', 'ic_info'),
                (4, 'Movies & Cinema', 'ic_play'),
                (5, 'Entertainment', 'ic_tv')");
        }

        $res = $this->mysqli->query("SELECT COUNT(*) as cnt FROM channels");
        if ($res && $res->fetch_assoc()['cnt'] == 0) {
            $this->mysqli->query("INSERT INTO channels (name, logo_url, stream_url, category_id, is_premium, stream_type) VALUES 
                ('OTT KING Sports 1 HD', 'https://picsum.photos/200/200?random=1', 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8', 2, 0, 'hls'),
                ('OTT KING Premium Sports 4K', 'https://picsum.photos/200/200?random=2', 'https://playertest.longtailvideo.com/adaptive/bbbell/bbbell.m3u8', 2, 1, 'hls'),
                ('World News 24/7', 'https://picsum.photos/200/200?random=3', 'https://devstreaming-cdn.apple.com/videos/streaming/examples/bipbop_4x3/bipbop_4x3_variant.m3u8', 3, 0, 'hls'),
                ('Action Movies Live', 'https://picsum.photos/200/200?random=4', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4', 4, 0, 'ts'),
                ('VIP Cinema Ultra', 'https://picsum.photos/200/200?random=5', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4', 4, 1, 'ts'),
                ('Entertainment Plus', 'https://picsum.photos/200/200?random=6', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4', 5, 0, 'ts')");
        }
    }

    private function initSqliteTables() {
        if (!$this->pdo) return;

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE,
            password TEXT,
            package TEXT,
            expiry_date TEXT,
            bound_device_id TEXT,
            session_token TEXT
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            icon TEXT
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS channels (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            logo_url TEXT,
            stream_url TEXT,
            category_id INTEGER,
            is_premium INTEGER,
            stream_type TEXT
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT,
            category TEXT,
            description TEXT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT,
            message TEXT,
            target_username TEXT DEFAULT '',
            target_package TEXT DEFAULT '',
            type TEXT DEFAULT 'SYSTEM',
            action_text TEXT DEFAULT 'View',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $userCount = $this->pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($userCount == 0) {
            $this->pdo->exec("INSERT INTO users (username, password, package, expiry_date) VALUES 
                ('admin', '123456', 'VIP Premium Ultra', '2030-12-31'),
                ('user1', '1234', 'Basic Plan', '2026-12-31')");
        }

        $notifCount = $this->pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
        if ($notifCount == 0) {
            $this->pdo->exec("INSERT INTO notifications (title, message, target_username, target_package, type, action_text) VALUES 
                ('Server Upgrade Notice', 'OTT KING Live TV server upgrade completed successfully. All 4K streams are active.', '', '', 'SYSTEM', 'OK'),
                ('VIP Account Special Welcome', 'Exclusive access active for VIP Users! Stream 4K Live Sports & Ultra Movies now.', 'admin', 'VIP Premium Ultra', 'USER', 'Explore VIP')");
        }

        $catCount = $this->pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
        if ($catCount == 0) {
            $this->pdo->exec("INSERT INTO categories (id, name, icon) VALUES 
                (1, 'All Channels', 'ic_tv'),
                (2, 'Sports Live', 'ic_play'),
                (3, 'News & World', 'ic_info'),
                (4, 'Movies & Cinema', 'ic_play'),
                (5, 'Entertainment', 'ic_tv')");
        }

        $chanCount = $this->pdo->query("SELECT COUNT(*) FROM channels")->fetchColumn();
        if ($chanCount == 0) {
            $this->pdo->exec("INSERT INTO channels (name, logo_url, stream_url, category_id, is_premium, stream_type) VALUES 
                ('OTT KING Sports 1 HD', 'https://picsum.photos/200/200?random=1', 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8', 2, 0, 'hls'),
                ('OTT KING Premium Sports 4K', 'https://picsum.photos/200/200?random=2', 'https://playertest.longtailvideo.com/adaptive/bbbell/bbbell.m3u8', 2, 1, 'hls'),
                ('World News 24/7', 'https://picsum.photos/200/200?random=3', 'https://devstreaming-cdn.apple.com/videos/streaming/examples/bipbop_4x3/bipbop_4x3_variant.m3u8', 3, 0, 'hls'),
                ('Action Movies Live', 'https://picsum.photos/200/200?random=4', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4', 4, 0, 'ts'),
                ('VIP Cinema Ultra', 'https://picsum.photos/200/200?random=5', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4', 4, 1, 'ts'),
                ('Entertainment Plus', 'https://picsum.photos/200/200?random=6', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4', 5, 0, 'ts')");
        }
    }

    public function fetchAll(string $sql, array $params = []): array {
        if ($this->is_mysqli) {
            $stmt = $this->prepareMysqli($sql, $params);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        } else {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    public function fetchOne(string $sql, array $params = []): ?array {
        $rows = $this->fetchAll($sql, $params);
        return (!empty($rows) && isset($rows[0])) ? $rows[0] : null;
    }

    public function execute(string $sql, array $params = []): bool {
        if ($this->is_mysqli) {
            $stmt = $this->prepareMysqli($sql, $params);
            return $stmt->execute();
        } else {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        }
    }

    public function lastInsertId(): int {
        if ($this->is_mysqli) {
            return $this->mysqli->insert_id;
        } else {
            return (int)$this->pdo->lastInsertId();
        }
    }

    private function prepareMysqli(string $sql, array $params = []) {
        // Convert named parameters like :u or :p to ?
        $paramValues = [];
        $sqlPos = $sql;

        if (!empty($params)) {
            preg_match_all('/:([a_zA-Z0-9_]+)/', $sql, $matches);
            if (!empty($matches[0])) {
                foreach ($matches[0] as $paramName) {
                    $key = ltrim($paramName, ':');
                    if (array_key_exists($key, $params)) {
                        $paramValues[] = $params[$key];
                    } else if (array_key_exists($paramName, $params)) {
                        $paramValues[] = $params[$paramName];
                    }
                }
                $sqlPos = preg_replace('/:([a_zA-Z0-9_]+)/', '?', $sql);
            } else {
                $paramValues = array_values($params);
            }
        }

        $stmt = $this->mysqli->prepare($sqlPos);
        if (!$stmt) {
            throw new Exception("MySQLi Prepare Error: " . $this->mysqli->error . " SQL: " . $sqlPos);
        }

        if (!empty($paramValues)) {
            $types = str_repeat('s', count($paramValues));
            $stmt->bind_param($types, ...$paramValues);
        }

        return $stmt;
    }
}

// Global Database Object
$db = new AppDatabase();
$mysqli = $db->getMysqli(); // Direct MySQLi object for phpMyAdmin compatible raw scripts
