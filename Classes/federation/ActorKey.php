<?php

/**
 * RSA keypairs for signing ActivityPub requests: one per local user, one for the instance actor
 * (user_id NULL). Made on first use. Private keys are stored encrypted (AES-256-GCM) with a key
 * derived from PWD_SEED, so a database dump alone can't be used to impersonate anyone.
 */
class ActorKey
{
    /**
     * @param int|null $user_id null for the instance actor
     * @return array [public_pem, private_pem]
     * @throws Exception
     */
    public static function get($user_id): array
    {
        $db = new Mysql();
        $row = $user_id === null
            ? $db->Fetch("SELECT * FROM `actor_keys` WHERE `user_id` IS NULL", null)
            : $db->Fetch("SELECT * FROM `actor_keys` WHERE `user_id` = :a", array(":a" => (int)$user_id));
        if ($row[0] != '00000') {
            return array($row[0]->public_key, self::decrypt($row[0]->private_key));
        }

        $key = openssl_pkey_new(array("private_key_bits" => 2048, "private_key_type" => OPENSSL_KEYTYPE_RSA));
        if ($key === false) {
            throw new Exception("Couldn't make a key");
        }
        openssl_pkey_export($key, $private);
        $public = openssl_pkey_get_details($key)['key'];
        //INSERT IGNORE: two requests making the first key at once keep whichever won
        $db->Fetch("INSERT IGNORE INTO `actor_keys` (`user_id`, `public_key`, `private_key`) VALUES (:a, :b, :c)",
            array(":a" => $user_id === null ? null : (int)$user_id, ":b" => $public, ":c" => self::encrypt($private)));
        if ($user_id === null) {
            //UNIQUE doesn't stop several NULLs, keep the first
            $db->Fetch("DELETE k1 FROM `actor_keys` k1 JOIN `actor_keys` k2 ON k1.user_id IS NULL AND k2.user_id IS NULL AND k1.key_num > k2.key_num", null);
        }
        return self::get($user_id);
    }

    private static function secret(): string
    {
        if (empty($_ENV['PWD_SEED'])) {
            throw new Exception("PWD_SEED isn't set");
        }
        return hash("sha256", "ootd actor keys " . $_ENV['PWD_SEED'], true);
    }

    public static function encrypt($plain): string
    {
        $iv = random_bytes(12);
        $cipher = openssl_encrypt($plain, "aes-256-gcm", self::secret(), OPENSSL_RAW_DATA, $iv, $tag);
        return "v1:" . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt($stored): string
    {
        if (strpos((string)$stored, "v1:") !== 0) {
            throw new Exception("Unknown key format");
        }
        $raw = base64_decode(substr($stored, 3));
        $plain = openssl_decrypt(substr($raw, 28), "aes-256-gcm", self::secret(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        if ($plain === false) {
            throw new Exception("Couldn't decrypt the key (has PWD_SEED changed?)");
        }
        return $plain;
    }
}
