<?php
declare(strict_types=1);

// -----------------------------
// Database Configuration
// -----------------------------
$host = 'shortline.proxy.rlwy.net';
$port = 31315;
$db   = 'railway';
$user = 'root';
$pass = 'rVkBsGReslMeafTlzATAlrIvbCPWSbaY';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

$pdo = null;

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    // DB connection failed, but don't die — just warn
    error_log("Database connection failed: " . $e->getMessage());
}

// -----------------------------
// Database-backed PHP sessions
// -----------------------------
class MySQLSessionHandler implements SessionHandlerInterface {
    private ?PDO $pdo;
    private string $table = 'sessions';

    public function __construct(?PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function open($savePath, $sessionName) { return true; }
    public function close() { return true; }

    public function read($id) {
        if (!$this->pdo) return '';
        $stmt = $this->pdo->prepare("SELECT data FROM {$this->table} WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? $row['data'] : '';
    }

    public function write($id, $data) {
        if (!$this->pdo) return false;
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->table} (id, data, last_access)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE data=?, last_access=NOW()
        ");
        return $stmt->execute([$id, $data, $data]);
    }

    public function destroy($id) {
        if (!$this->pdo) return false;
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id=?");
        return $stmt->execute([$id]);
    }

    public function gc($max_lifetime) {
        if (!$this->pdo) return false;
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE last_access < NOW() - INTERVAL ? SECOND");
        $stmt->execute([$max_lifetime]);
        return $stmt->rowCount();
    }
}

// Use sessions
$handler = new MySQLSessionHandler($pdo);
session_set_save_handler($handler, true);
session_name("n8n_session");
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'] ?? '',
    'secure' => false, // set to true on HTTPS
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
