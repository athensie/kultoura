<?php
/*
 |--------------------------------------------------------------------
 | SESSION BOOTSTRAP — replaces a bare session_start() everywhere
 |--------------------------------------------------------------------
 | Sets hardened cookie flags before the session actually starts:
 |   - httponly: JS (and so a stray XSS) can't read the session cookie
 |   - samesite=Lax: cross-site requests won't carry the cookie, which
 |     also blankets most CSRF vectors that don't already go through
 |     csrf.php's token check
 |   - secure: only when the request actually arrived over HTTPS —
 |     detected via $_SERVER['HTTPS'] (direct TLS, e.g. local XAMPP
 |     never sets this) or X-Forwarded-Proto (Railway terminates TLS
 |     at its edge and forwards plain HTTP internally, so this is the
 |     only signal available in that environment). Hardcoding this to
 |     true would silently break every login on local HTTP dev.
 */

/*
 |--------------------------------------------------------------------
 | DATABASE-BACKED SESSIONS (hosted only)
 |--------------------------------------------------------------------
 | PHP's default session handler stores each session as a file on the
 | container's local disk. On Railway that disk isn't guaranteed to
 | still have it on the very next request — a redeploy or restart
 | replaces the container (and its disk) outright, and if the service
 | is ever scaled to more than one instance, a later request can just
 | as easily land on a different instance that never saw the file in
 | the first place. Either way, a logged-in user gets silently signed
 | out on the next refresh, which looks like a random bug rather than
 | what it is. Storing session data in MySQL instead means it's read
 | from the same shared database every instance already uses, so it
 | survives all of that. Local XAMPP keeps PHP's normal file sessions —
 | nothing wrong with those on a single dev machine, and it keeps local
 | dev from needing a sessions table at all.
 */
if (!class_exists('KtDbSessionHandler')) {
    class KtDbSessionHandler implements SessionHandlerInterface
    {
        private mysqli $conn;
        private int $lifetime;

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
            return true;
        }

        public function read($id): string
        {
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

// Opens its own small connection just for session storage (this file runs
// before dbmain.php's $conn exists everywhere it's used) and wires it in —
// if that fails for any reason, it falls through to PHP's normal file
// sessions instead of breaking the page outright.
if (!function_exists('kt_session_use_db_handler')) {
    function kt_session_use_db_handler(int $lifetime): void
    {
        if (!getenv('MYSQLHOST')) return; // local XAMPP — keep file sessions

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

        // Without this, both default to PHP's stock 24 minutes — a
        // logged-in user gets silently signed out after sitting idle
        // for less than half an hour, which looks like a random bug
        // rather than an intentional timeout. A week is generous enough
        // that normal browsing never hits it, while still expiring
        // genuinely abandoned sessions eventually.
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
