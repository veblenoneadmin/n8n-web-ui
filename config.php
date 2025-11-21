<?php
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

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// -----------------------------
// Database-backed PHP sessions
// -----------------------------
class MySQLSessionHandler implements SessionHandlerInterface {
    private $pdo;
    private $table;

    public function __construct(PDO $pdo, $table = 'sessions') {
        $this->pdo = $pdo;
        $this->table = $table;
    }

    public function open($savePath, $sessionName) { return true; }
    public function close() { return true; }

    public function read($id) {
        $stmt = $this->pdo->prepare("SELECT data FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['data'] : '';
    }

    public function write($id, $data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->table} (id, data, last_access)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE data = ?, last_access = NOW()
        ");
        return $stmt->execute([$id, $data, $data]);
    }

    public function destroy($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function gc($maxlifetime) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE last_access < NOW() - INTERVAL ? SECOND");
        return $stmt->execute([$maxlifetime]);
    }
}

// Set session handler
$handler = new MySQLSessionHandler($pdo);
session_set_save_handler($handler, true);

// Session cookie parameters
session_name("n8n_session");
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();
