<?php

/**
 * "Forgot password" links. The emailed token is random; only its sha256 is stored. A link works once,
 * for an hour, and using it signs the account out everywhere.
 */
class PasswordReset
{
    const LIFETIME = 3600;
    const PER_HOUR = 3;

    /**
     * Emails a reset link when the address has an account. Says nothing either way, so the form
     * can't be used to find out who has an account.
     *
     * @param string $email
     * @return void
     */
    public static function request($email): void
    {
        $db = new Mysql();
        $user = $db->Fetch("SELECT `user_id`, `email` FROM `users` WHERE `email` = :a", array(":a" => trim((string)$email)));
        if ($user[0] == '00000') {
            return;
        }
        $user_id = (int)$user[0]->user_id;

        $recent = $db->Fetch("SELECT COUNT(*) AS n FROM `password_resets` WHERE `user_id` = :a AND `timestamp` > DATE_SUB(NOW(), INTERVAL 1 HOUR)", array(":a" => $user_id));
        if ((int)$recent[0]->n >= self::PER_HOUR) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $db->Fetch("INSERT INTO `password_resets` (`user_id`, `token_hash`, `expires_at`) VALUES (:a, :b, DATE_ADD(NOW(), INTERVAL " . self::LIFETIME . " SECOND))",
            array(":a" => $user_id, ":b" => hash("sha256", $token)));

        Mailer::queue($user[0]->email, "Reset your " . instance_name() . " password",
            "Someone asked to reset the password for your " . instance_name() . " account.\n\n"
            . "Choose a new password here (the link works once, for an hour):\n"
            . absolute_url("/reset?token=" . $token) . "\n\n"
            . "If it wasn't you, ignore this email; your password hasn't changed.\n");
    }

    /**
     * @param string $token
     * @return int|null the user the token belongs to, when it's valid
     */
    public static function user_for($token): ?int
    {
        if (!preg_match('/^[a-f0-9]{64}$/', (string)$token)) {
            return null;
        }
        $db = new Mysql();
        $row = $db->Fetch("SELECT `user_id` FROM `password_resets` WHERE `token_hash` = :a AND `used_at` IS NULL AND `expires_at` > NOW()",
            array(":a" => hash("sha256", $token)));
        return $row[0] != '00000' ? (int)$row[0]->user_id : null;
    }

    /**
     * @param string $token
     * @param string $password
     * @return void
     * @throws Exception
     */
    public static function reset($token, $password): void
    {
        $user_id = self::user_for($token);
        if ($user_id === null) {
            throw new Exception("This link has expired or has already been used");
        }
        if (strlen((string)$password) < 8) {
            throw new Exception("Password must be at least 8 characters");
        }
        $db = new Mysql();
        //every outstanding link for the account stops working, and every session ends
        $db->Fetch("UPDATE `password_resets` SET `used_at` = NOW() WHERE `user_id` = :a AND `used_at` IS NULL", array(":a" => $user_id));
        $db->Fetch("UPDATE `users` SET `password` = :a, `session` = NULL WHERE `user_id` = :b", array(
            ":a" => password_hash(hash_hmac("sha256", $password, $_ENV['PWD_SEED']), PASSWORD_ARGON2ID),
            ":b" => $user_id
        ));
    }
}
