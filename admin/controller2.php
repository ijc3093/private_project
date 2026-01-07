<?php
/**
 * /Business_only3/admin/controller.php
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
       SECURITY AUDIT LOG
    ========================= */
    public function logSecurity(
        string $action,
        bool $success,
        ?string $email = null,
        ?string $username = null,
        ?int $adminId = null,
        array $meta = []
    ): void {
          try {
            $stmt = $this->dbh->prepare("
                INSERT INTO security_audit_log
                (email, username, admin_id, action, success, ip, user_agent, meta)
                VALUES (:email, :username, :admin_id, :action, :success, :ip, :ua, :meta)
            ");
            $stmt->execute([
                ':email'    => $email,
                ':username' => $username,
                ':admin_id' => $adminId,
                ':action'   => $action,
                ':success'  => $success ? 1 : 0,
                ':ip'       => clientIp(),
                ':ua'       => clientUserAgent(),
                ':meta'     => $meta ? json_encode($meta, JSON_UNESCAPED_SLASHES) : null,
            ]);
        } catch (Throwable $e) {
            // Don't block app if logging fails
        }
    }

    
    /* =========================
       PASSWORD HELPERS (ADMIN)
    ========================= */
    private function hashMatches(string $plain, string $dbHash): bool
    {
        if ($dbHash === '') return false;

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
        if ($idadmin <= 0) return;
        if (password_get_info($dbHash)['algo'] !== 0) return;

        $newHash = password_hash($plain, PASSWORD_DEFAULT);
        $stmt = $this->dbh->prepare("UPDATE admin SET password = :p WHERE idadmin = :id LIMIT 1");
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
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    }

    // ---------------------------
    // Admin: Register (legacy)
    // ---------------------------
    public function registerAdmin(array $data): bool
    {
        $sql = "INSERT INTO admin (fullname, username, email, password, gender, mobile, designation, role, image, status)
                VALUES (:fullname, :username, :email, :password, :gender, :mobile, :designation, :role, :image, :status)";
        $stmt = $this->dbh->prepare($sql);

        return $stmt->execute([
            ':fullname' => $data['fullname'],
            ':username' => $data['username'],
            ':email' => $data['email'],
            ':password' => $data['password'],
            ':gender' => $data['gender'],
            ':mobile' => $data['mobile'],
            ':designation' => $data['designation'],
            ':role' => $data['role'],
            ':image' => $data['image'],
            ':status' => $data['status'],
        ]);
    }

    // ---------------------------
    // Users: Register
    // ---------------------------
    public function registerUser(array $data): bool
    {
        $sql = "INSERT INTO users (name, username, email, password, gender, mobile, designation, image, status)
                VALUES (:name, :username, :email, :password, :gender, :mobile, :designation, :image, :status)";
        $stmt = $this->dbh->prepare($sql);

        return $stmt->execute([
            ':name'        => $data['name'],
            ':username'    => $data['username'],  // ✅ FIXED
            ':email'       => $data['email'],
            ':password'    => $data['password'],
            ':gender'      => $data['gender'],
            ':mobile'      => $data['mobile'],
            ':designation' => $data['designation'],
            ':image'       => $data['image'],
            ':status'      => $data['status'],
        ]);
    }

    public function findUserByEmail(string $email)
    {
        $sql = "SELECT * FROM users WHERE email = :email LIMIT 1";
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    }

    /* =========================
       PASSWORD HELPERS (USER)
    ========================= */
    private function userHashMatches(string $plain, string $dbHash): bool
    {
        if ($dbHash === '') return false;

        if (password_get_info($dbHash)['algo'] !== 0) {
            return password_verify($plain, $dbHash);
        }
        if (hash('sha256', $plain) === $dbHash) return true;
        if (hash('sha384', $plain) === $dbHash) return true;
        if (md5($plain) === $dbHash) return true;

        return false;
    }

    private function upgradeUserPasswordIfNeeded(int $userId, string $plain, string $dbHash): void
    {
        if ($userId <= 0) return;

        if (password_get_info($dbHash)['algo'] !== 0) {
            return;
        }

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
    public function adminLogin(string $username, string $password): ?array
    {
        $stmt = $this->dbh->prepare("
            SELECT idadmin, username, email, password, role, status, image, friend_code
            FROM admin
            WHERE username = :username
            LIMIT 1
        ");

        $stmt->execute([
            ':username' => $username
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return null;
        if ((int)$row['status'] !== 1) return null;

        if (!$this->hashMatches($password, (string)$row['password'])) {
            return null;
        }

        $this->upgradePasswordIfNeeded((int)$row['idadmin'], $password, $row['password']);

        return [
            'idadmin'     => (int)$row['idadmin'],
            'username'    => (string)$row['username'],
            'email'       => (string)$row['email'],
            'role'        => (int)$row['role'],
            'image'       => (string)$row['image'],
            'friend_code' => (string)$row['friend_code'],
        ];
    }


    /* =========================
       USER LOGIN (NO SESSION WRITE)
    ========================= */
    public function userLogin(string $username, string $password): ?array
    {
        $stmt = $this->dbh->prepare("
            SELECT id, name, username, email, password, image, role, status, friend_code
            FROM users
            WHERE username = :username
            LIMIT 1
        ");

        $stmt->execute([
            ':username' => $username
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return null;
        if ((int)$row['status'] !== 1) return null;

        if (!$this->userHashMatches($password, (string)$row['password'])) {
            return null;
        }

        $this->upgradeUserPasswordIfNeeded((int)$row['id'], $password, $row['password']);

        return [
            'id'          => (int)$row['id'],
            'name'        => (string)$row['name'],
            'username'    => (string)$row['username'],
            'email'       => (string)$row['email'],
            'role'        => (int)$row['role'],
            'status'      => (int)$row['status'],
            'image'       => (string)($row['image'] ?? 'default.jpg'),
            'friend_code' => (string)$row['friend_code'],
        ];
    }





    /* =========================
       NOTIFICATION + EMAIL ALERT
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

                if (function_exists('sendNotificationEmail')) {
                    sendNotificationEmail($to, $subject, $html);
                }
            }
        }

        return true;
    }

    // ---------------------------
    // Roles
    // ---------------------------
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

    /**
     * Create internal account + invite email + force password change
     */
    public function createInternalAccountWithInvite(array $data): array
    {
        // required: fullname, username, email, role, status
        $fullname = trim($data['fullname'] ?? '');
        $username = trim($data['username'] ?? '');
        $email    = trim($data['email'] ?? '');
        $role     = (int)($data['role'] ?? 0);
        $status   = (int)($data['status'] ?? 1);

        if ($fullname === '' || $username === '' || $email === '' || $role <= 0) {
            return ['ok'=>false, 'error'=>'Missing required fields'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok'=>false, 'error'=>'Invalid email'];
        }

        // prevent duplicates
        $st = $this->dbh->prepare("SELECT 1 FROM admin WHERE email=:e OR username=:u LIMIT 1");
        $st->execute([':e'=>$email, ':u'=>$username]);
        if ($st->fetchColumn()) {
            return ['ok'=>false, 'error'=>'Email or username already exists'];
        }

        // friend code generator: ADM-XXXX-XXXX
        $makeCode = function(string $prefix='ADM'): string {
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $part = function() use ($chars){
                $s=''; for($i=0;$i<4;$i++) $s.=$chars[random_int(0, strlen($chars)-1)];
                return $s;
            };
            return strtoupper($prefix.'-'.$part().'-'.$part());
        };

        $genUniqueCode = function() use ($makeCode): string {
            for ($i=0; $i<120; $i++) {
                $code = $makeCode('ADM');
                $q = $this->dbh->prepare("SELECT 1 FROM admin WHERE friend_code=:c LIMIT 1");
                $q->execute([':c'=>$code]);
                if (!$q->fetchColumn()) return $code;
            }
            throw new RuntimeException("Unable to generate unique friend code");
        };

        // temp password
        $makeTempPassword = function(int $len=12): string {
            $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*?';
            $out=''; for($i=0;$i<$len;$i++) $out.=$chars[random_int(0, strlen($chars)-1)];
            return $out;
        };

        $friendCode = $genUniqueCode();
        $tempPass   = $makeTempPassword(12);
        $hash       = password_hash($tempPass, PASSWORD_DEFAULT);

        // ✅ CODE DEFAULTS (Option A)
        $gender      = 'N/A';
        $mobile      = 'N/A';
        $designation = 'Internal';

        $image = 'default.jpg';

        try {
            $this->dbh->beginTransaction();

            // ✅ matches your table: gender/mobile/designation are NOT NULL
            $ins = $this->dbh->prepare("
                INSERT INTO admin
                (fullname, username, friend_code, email, password,
                gender, mobile, designation,
                role, image, status, force_password_change,
                failed_login_attempts, locked_until, last_login_at)
                VALUES
                (:fullname, :username, :friend_code, :email, :password,
                :gender, :mobile, :designation,
                :role, :image, :status, 1,
                0, NULL, NULL)
            ");

            $ins->execute([
                ':fullname'    => $fullname,
                ':username'    => $username,
                ':friend_code' => $friendCode,
                ':email'       => $email,
                ':password'    => $hash,
                ':gender'      => $gender,
                ':mobile'      => $mobile,
                ':designation' => $designation,
                ':role'        => $role,
                ':image'       => $image,
                ':status'      => $status
            ]);

            // notify admin panel (optional)
            $this->addNotification($email, 'Admin', 'Create Internal Account');

            // send invite email
            $mailerFile = __DIR__ . '/../includes/mailer.php';
            if (file_exists($mailerFile)) {
                require_once $mailerFile;

                $subject = "You're invited: Internal Account Created";
                $html = "
                <h3>Welcome!</h3>
                <p>An internal account has been created for you.</p>
                <p><b>Login URL:</b> http://localhost:8888/Business_only3/admin/</p>
                <p><b>Username:</b> ".htmlspecialchars($username)."</p>
                <p><b>Email:</b> ".htmlspecialchars($email)."</p>
                <p><b>Friend Code:</b> ".htmlspecialchars($friendCode)."</p>
                <p><b>Temporary Password:</b> ".htmlspecialchars($tempPass)."</p>
                <p><b>Important:</b> You will be forced to change password after login.</p>
                ";

                if (function_exists('sendNotificationEmail')) {
                    sendNotificationEmail($email, $subject, $html);
                }
            }

            $this->dbh->commit();

            return [
                'ok'=>true,
                'friend_code'=>$friendCode,
                'temp_password'=>$tempPass
            ];

        } catch (Throwable $e) {
            if ($this->dbh->inTransaction()) $this->dbh->rollBack();
            return ['ok'=>false, 'error'=>$e->getMessage()];
        }
    }


}
