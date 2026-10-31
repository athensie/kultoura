<?php
if (!class_exists('KtDbSessionHandler')) {
    class KtDbSessionHandler implements SessionHandlerInterface
    {
        private mysqli $conn;
        private int $lifetime;
        private ?string $lockName = null;
        public function __construct(mysqli $conn, int $lifetime)
        {
            $this->conn = $conn;
            $this->lifetime = $lifetime;
        }
        public function open($path, $name): bool
        {
            return true;
        }
        public function close(): bool
        {
            if ($this->lockName !== null) {
                $stmt = $this->conn->prepare('SELECT RELEASE_LOCK(?)');
                $stmt->bind_param('s', $this->lockName);
                $stmt->execute();
                $stmt->close();
                $this->lockName = null;
            }
            return true;
        }
        public function read($id): string
        {
            $this->lockName = 'kt_session_' . $id;
            $lockStmt = $this->conn->prepare('SELECT GET_LOCK(?, 10)');
            $lockStmt->bind_param('s', $this->lockName);
            $lockStmt->execute();
            $lockStmt->close();
            $cutoff = time() - $this->lifetime;
            $stmt = $this->conn->prepare('SELECT data FROM sessions WHERE id = ? AND last_activity > ? LIMIT 1');
            $stmt->bind_param('si', $id, $cutoff);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $row ? $row['data'] : '';
        }
        public function write($id, $data): bool
        {
            $now = time();
            $stmt = $this->conn->prepare(
                'INSERT INTO sessions (id, data, last_activity) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE data = VALUES(data), last_activity = VALUES(last_activity)'
            );
            $stmt->bind_param('ssi', $id, $data, $now);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        }
        public function destroy($id): bool
        {
            $stmt = $this->conn->prepare('DELETE FROM sessions WHERE id = ?');
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $stmt->close();
            return true;
        }
        public function gc($max_lifetime): int
        {
            $cutoff = time() - $max_lifetime;
            $stmt = $this->conn->prepare('DELETE FROM sessions WHERE last_activity < ?');
            $stmt->bind_param('i', $cutoff);
            $stmt->execute();
            $deleted = $stmt->affected_rows;
            $stmt->close();
            return $deleted;
        }
    }
}
if (!function_exists('kt_session_use_db_handler')) {
    function kt_session_use_db_handler(int $lifetime): void
    {
        if (!getenv('MYSQLHOST')) return;
        try {
            $sessConn = new mysqli(
                getenv('MYSQLHOST'),
                getenv('MYSQLUSER') ?: 'root',
                getenv('MYSQLPASSWORD') ?: '',
                getenv('MYSQLDATABASE') ?: 'kultoura_db',
                (int) (getenv('MYSQLPORT') ?: 3306)
            );
            $sessConn->query(
                'CREATE TABLE IF NOT EXISTS sessions (
                    id VARCHAR(128) NOT NULL PRIMARY KEY,
                    data MEDIUMTEXT NOT NULL,
                    last_activity INT UNSIGNED NOT NULL,
                    INDEX (last_activity)
                ) ENGINE=InnoDB'
            );
            session_set_save_handler(new KtDbSessionHandler($sessConn, $lifetime), true);
        } catch (Throwable $e) {
            error_log('Session DB handler unavailable, falling back to file sessions: ' . $e->getMessage());
        }
    }
}
if (!function_exists('kt_session_start')) {
    function kt_session_start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') == 443)
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
        $sessionLifetime = 7 * 24 * 60 * 60;
        ini_set('session.gc_maxlifetime', (string) $sessionLifetime);
        kt_session_use_db_handler($sessionLifetime);
        session_set_cookie_params([
            'lifetime' => $sessionLifetime,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}
kt_session_start();
