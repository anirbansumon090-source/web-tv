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

function hash_password(string $password): string {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verify_password(string $password, string $storedHash): bool {
    if (empty($storedHash)) {
        return false;
    }

    if (str_starts_with($storedHash, '$2') || str_starts_with($storedHash, '$argon2') || str_starts_with($storedHash, '$pbkdf2')) {
        return password_verify($password, $storedHash);
    }

    return hash_equals($storedHash, $password);
}

/**
 * Unified Database Adapter supporting MySQLi and SQLite (fallback)
 */
class AppDatabase {
    private $mysqli = null;
    private $pdo = null;
    private $is_mysqli = false;

    public function __construct() {
        if (function_exists('mysqli_init') && extension_loaded('mysqli')) {
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

        // No hardcoded bootstrap data. Tables are created empty and populated only through admin actions.
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

        // No hardcoded bootstrap data. Tables are created empty and populated only through admin actions.
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
        $sqlPos = $sql;
        $paramValues = [];

        if (!empty($params)) {
            $sqlPos = preg_replace_callback('/:([a-zA-Z0-9_]+)/', function ($matches) use (&$paramValues, $params) {
                $key = $matches[1];
                if (array_key_exists($key, $params)) {
                    $paramValues[] = $params[$key];
                } elseif (array_key_exists(':' . $key, $params)) {
                    $paramValues[] = $params[':' . $key];
                } else {
                    $paramValues[] = null;
                }

                return '?';
            }, $sql);

            if (empty($paramValues) && !preg_match('/:([a-zA-Z0-9_]+)/', $sql)) {
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
