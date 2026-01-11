<?php
// /Business_only3/admin/includes/friend_code_helpers.php
declare(strict_types=1);

function generateFriendCode(int $groups = 3): string {
    $parts = [];
    for ($i=0; $i<$groups; $i++) {
        $parts[] = strtoupper(bin2hex(random_bytes(2))); // 4 chars
    }
    return implode('-', $parts);
}

/**
 * Ensure current admin has a friend_code; generate one if missing.
 */
function ensureAdminFriendCode(PDO $dbh, int $adminId): string
{
    if ($adminId <= 0) return '';

    $st = $dbh->prepare("SELECT friend_code FROM admin WHERE idadmin = :id LIMIT 1");
    $st->execute([':id' => $adminId]);
    $code = trim((string)($st->fetchColumn() ?: ''));

    if ($code !== '') return $code;

    for ($i=0; $i<30; $i++) {
        $new = generateFriendCode();
        $dup = $dbh->prepare("SELECT idadmin FROM admin WHERE friend_code = :c LIMIT 1");
        $dup->execute([':c' => $new]);

        if (!$dup->fetchColumn()) {
            $upd = $dbh->prepare("UPDATE admin SET friend_code = :c WHERE idadmin = :id LIMIT 1");
            $upd->execute([':c' => $new, ':id' => $adminId]);
            return $new;
        }
    }

    return '';
}
