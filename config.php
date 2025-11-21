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
    private PDO $pdo;
    private string $table = 'sessions';

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    #[\ReturnTypeWillChange]
    public function open($savePath, $sessionName) { return true; }

    #[\ReturnTypeWillChange]
    public function close() { return true; }

    #[\ReturnTypeWillChange]
    public function read($id) {
        $stmt = $this->pdo->prepare("SELECT data FROM {$this->table} WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? $row['data'] : '';
    }

    #[\ReturnTypeWillChange]
    public function write($id, $data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->table} (id, data, last_access)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE data=?, last_access=NOW()
        ");
        return $stmt->execute([$id, $data, $data]);
    }

    #[\ReturnTypeWillChange]
    public function destroy($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id=?");
        return $stmt->execute([$id]);
    }

    #[\ReturnTypeWillChange]
    public function gc($max_lifetime) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE last_access < NOW_
