<?php
declare(strict_types=1);

// -----------------------------
// Database Configuration
// -----------------------------
$host     = "shuttle.proxy.rlwy.net";
$port     = 25965;
$user     = "root";
$pass     = "THgMALdtucPApKGCBKzkeMQjyvoNwsLK";
$dbname   = "railway";

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

$pdo = null;
try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
}

// -----------------------------
// Database-backed PHP sessions
// -----------------------------
class MySQLSessionHandler implements SessionHandlerInterface {
    private ?PDO $pdo;
    private string $table = 'sessions';

    public function __construct(?PDO $pdo) { $this->pdo = $pdo; }

    #[\ReturnTypeWillChange]
    public function open(string $savePath, string $sessionName): bool { return true; }

    #[\ReturnTypeWillChange]
    public function close(): bool { return true; }

    #[\ReturnTypeWillChange]
    public function read(string $id): string|false {
        if (!$this->pdo) return '';
        $stmt = $this->pdo->prepare("SELECT data FROM {$this->table} WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? $row['data'] : '';
    }

    #[\ReturnTypeWillChange]
    public function write(string $id, string $data): bool {
        if (!$this->pdo) return false;
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->table} (id, data, last_access)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE data=?, last_access=NOW()
        ");
        return $stmt->execute([$id, $data, $data]);
    }

    #[\ReturnTypeWillChange]
    public function destroy(string $id): bool {
        if (!$this->pdo) return false;
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id=?");
        return $stmt->execute([$id]);
    }

    #[\ReturnTypeWillChange]
    public function gc(int $max_lifetime): int|false {
        if (!$this->pdo) return false;
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE last_access < NOW() - INTERVAL ? SECOND");
        $stmt->execute([$max_lifetime]);
        return $stmt->rowCount();
    }
}

// Only set session handler if session not started
if (session_status() === PHP_SESSION_NONE) {
    $handler = new MySQLSessionHandler($pdo);
    session_set_save_handler($handler, true);

    session_name("n8n_session");
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'] ?? '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}
