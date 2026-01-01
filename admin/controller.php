<?php
/**
 * Controller.php
 * - Single PDO connection
 * - Admin + Users register
 * - Login supports old hashes (md5/sha256/sha384) + new (password_hash)
 */
require_once __DIR__ . '/../config.php';
class Controller
{
    
    private PDO $dbh;

    public function __construct()
    {
        $cfg = new Config();
        $this->dbh = $cfg->pdo();
    }

    public function pdo(): PDO
    {
        return $this->dbh;
    }

    /* =========================
       PASSWORD HELPERS
    ========================= */
    private function hashMatches(string $plain, string $dbHash): bool
    {
        if (password_get_info($dbHash)['algo'] !== 0) {
            return password_verify($plain, $dbHash);
        }
        if (hash('sha256', $plain) === $dbHash) return true;
        if (hash('sha384', $plain) === $dbHash) return true;
        if (md5($plain) === $dbHash) return true;
        return false;
    }

    private function upgradePasswordIfNeeded(int $idadmin, string $plain, string $dbHash): void
    {
        if (password_get_info($dbHash)['algo'] !== 0) return;

        $newHash = password_hash($plain, PASSWORD_DEFAULT);
        $stmt = $this->dbh->prepare("UPDATE admin SET password = :p WHERE idadmin = :id");
        $stmt->execute([':p' => $newHash, ':id' => $idadmin]);
    }

    // ---------------------------
    // Admin: Find existing
    // ---------------------------
    public function findAdminByEmailOrUsername(string $email, string $username)
    {
        $sql = "SELECT * FROM admin WHERE email = :email OR username = :username LIMIT 1";
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute([
            ':email' => $email,
            ':username' => $username
        ]);
        $row = $stmt->fetch();
        return $row ?: false;
    }

    // ---------------------------
    // Admin: Register (admin/register.php uses this)
    // ---------------------------
    public function registerAdmin(array $data): bool
    {
        $sql = "INSERT INTO admin (username, email, password, gender, mobile, designation, role, image, status)
                VALUES (:username, :email, :password, :gender, :mobile, :designation, :role, :image, :status)";
        $stmt = $this->dbh->prepare($sql);

        return $stmt->execute([
            ':username' => $data['username'],
            ':email' => $data['email'],
            ':password' => $data['password'], // store password_hash() result
            ':gender' => $data['gender'],
            ':mobile' => $data['mobile'],
            ':designation' => $data['designation'],
            ':role' => $data['role'],
            ':image' => $data['image'],
            ':status' => $data['status'],
        ]);
    }

    // ---------------------------
    // Users: Register (root /register.php uses this)
    // ---------------------------
    public function registerUser(array $data): bool
    {
        $sql = "INSERT INTO users (name, email, password, gender, mobile, designation, image, status)
                VALUES (:name, :email, :password, :gender, :mobile, :designation, :image, :status)";
        $stmt = $this->dbh->prepare($sql);

        return $stmt->execute([
            ':name' => $data['name'],
            ':email' => $data['email'],
            ':password' => $data['password'], // store password_hash() result
            ':gender' => $data['gender'],
            ':mobile' => $data['mobile'],
            ':designation' => $data['designation'],
            ':image' => $data['image'],
            ':status' => $data['status'],
        ]);
    }

    public function findUserByEmail(string $email)
    {
        $sql = "SELECT * FROM users WHERE email = :email LIMIT 1";
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ?: false;
    }


        private function userHashMatches(string $plain, string $dbHash): bool
    {
        // New secure hash (bcrypt/argon)
        if (password_get_info($dbHash)['algo'] !== 0) {
            return password_verify($plain, $dbHash);
        }

        // Legacy hashes
        if (hash('sha256', $plain) === $dbHash) return true;
        if (hash('sha384', $plain) === $dbHash) return true;
        if (md5($plain) === $dbHash) return true;

        return false;
    }

    private function upgradeUserPasswordIfNeeded(int $userId, string $plain, string $dbHash): void
    {
        // Already password_hash => nothing to do
        if (password_get_info($dbHash)['algo'] !== 0) {
            return;
        }

        // Upgrade to password_hash()
        $newHash = password_hash($plain, PASSWORD_DEFAULT);

        $sql = "UPDATE users SET password = :p WHERE id = :id LIMIT 1";
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute([
            ':p'  => $newHash,
            ':id' => $userId
        ]);
    }

    /* =========================
       ADMIN LOGIN (NO SESSION WRITE)
    ========================= */
    public function adminLogin(string $usernameOrEmail, string $password): ?array
    {
        $sql = "SELECT idadmin, username, email, password, role, status, image
                FROM admin
                WHERE username = :u OR email = :e
                LIMIT 1";
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute([
            ':u' => $usernameOrEmail,
            ':e' => $usernameOrEmail
        ]);

        $row = $stmt->fetch();
        if (!$row) return null;
        if ((int)$row['status'] !== 1) return null;

        $dbHash = (string)$row['password'];
        if (!$this->hashMatches($password, $dbHash)) return null;

        $this->upgradePasswordIfNeeded((int)$row['idadmin'], $password, $dbHash);

        return [
            'idadmin'  => (int)$row['idadmin'],
            'username' => (string)$row['username'],
            'email'    => (string)$row['email'],
            'role'     => (int)$row['role'],
            'image'    => (string)($row['image'] ?? 'default.jpg'),
        ];
    }

    /* =========================
       USER LOGIN (NO SESSION WRITE)
    ========================= */
    public function userLogin(string $email, string $password): array|false
    {
        $stmt = $this->dbh->prepare("
            SELECT id, name, email, password, image, status
            FROM users
            WHERE email = :email
            LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) return false;
        if ((int)$user['status'] !== 1) return false;

        $dbHash = (string)$user['password'];

        $ok = false;
        if (password_get_info($dbHash)['algo'] !== 0) {
            $ok = password_verify($password, $dbHash);
        } else {
            $ok = (md5($password) === $dbHash);
            if ($ok) {
                // auto-upgrade
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $up = $this->dbh->prepare("UPDATE users SET password = :p WHERE id = :id");
                $up->execute([':p' => $newHash, ':id' => (int)$user['id']]);
            }
        }

        return $ok ? $user : false;
    }


    /* =========================
       NOTIFICATION + EMAIL ALERT
       - inserts is_read = 0
       - sends email:
         * if receiver is an email => send to that email
         * if receiver is 'Admin' => send to ADMIN_ALERT_EMAIL
    ========================= */
    public function addNotification(string $notiuser, string $notireceiver, string $notitype): bool
    {
        $stmt = $this->dbh->prepare("
            INSERT INTO notification (notiuser, notireceiver, notitype, is_read)
            VALUES (:u, :r, :t, 0)
        ");
        $ok = $stmt->execute([
            ':u' => $notiuser,
            ':r' => $notireceiver,
            ':t' => $notitype,
        ]);

        if (!$ok) return false;

        // Decide email target
        $cfg = new Config();
        $to = '';

        if (filter_var($notireceiver, FILTER_VALIDATE_EMAIL)) {
            $to = $notireceiver;
        } elseif ($notireceiver === 'Admin') {
            $to = $cfg->ADMIN_ALERT_EMAIL;
        }

        if ($to !== '') {
            $mailerFile = __DIR__ . '/../includes/mailer.php';
            if (file_exists($mailerFile)) {
                require_once $mailerFile;

                $subject = "New Notification";
                $html = "
                    <h3>New Notification</h3>
                    <p><b>From:</b> " . htmlspecialchars($notiuser) . "</p>
                    <p><b>Type:</b> " . htmlspecialchars($notitype) . "</p>
                    <p>Please login to view it.</p>
                ";
                sendNotificationEmail($to, $subject, $html);
            }
        }

        return true;
    }




    // Return role record: ['idrole'=>..., 'name'=>...]
    public function getRoleById(int $idrole): ?array
    {
        $stmt = $this->dbh->prepare("SELECT idrole, name FROM role WHERE idrole = :id LIMIT 1");
        $stmt->execute([':id' => $idrole]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getAllRoles(): array
    {
        $stmt = $this->dbh->prepare("SELECT idrole, name FROM role ORDER BY idrole ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}
