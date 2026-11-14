<?php

/**
 * Cloud blogs (0.14.0): anyone can create their own OOTD blog from the login page. Each one is a
 * real Plesk subscription under a reseller on this server, at <name>.CLOUD_DOMAIN, with its own
 * database and its own copy of OOTD, installed with the person as its admin.
 *
 * Off unless .env has all of: CLOUD_DOMAIN, CLOUD_SERVER_IP, PLESK_API_URL, PLESK_API_KEY and
 * CLOUD_PLAN (see SELF-HOSTING, "Cloud blogs"). DNS goes to Cloudflare when its keys are set too,
 * otherwise to Plesk (CloudDns).
 *
 * The request is confirmed by email, then the cloud_build job works through the steps below,
 * remembering each one, so a retry after a timeout carries on where it stopped. The new blog has no
 * cron of its own: this blog calls its /cron address every minute (ping_due).
 */
class CloudBlog
{
    const STEPS = array("customer", "webspace", "database", "dns", "upload", "setup", "https", "email");
    const RESERVED = array("www", "mail", "ftp", "smtp", "imap", "pop", "webmail", "admin", "api", "app", "blog", "blogs", "ootd", "static",
        "cdn", "ns1", "ns2", "dns", "autodiscover", "autoconfig", "plesk", "cpanel", "status", "help", "support", "root", "test", "staging",
        "dev", "about", "login", "signup", "abuse", "postmaster", "hostmaster", "security", "news", "shop", "store");
    const UNCONFIRMED_HOURS = 24;
    const PER_IP_PER_DAY = 2;

    /**
     * @return bool every setting a build needs is in .env
     */
    public static function enabled(): bool
    {
        foreach (array("CLOUD_DOMAIN", "CLOUD_SERVER_IP", "PLESK_API_URL", "PLESK_API_KEY", "CLOUD_PLAN") as $key) {
            if (trim((string)($_ENV[$key] ?? "")) === "") {
                return false;
            }
        }
        return true;
    }

    public static function domain(): string
    {
        return strtolower(trim((string)($_ENV['CLOUD_DOMAIN'] ?? ""), ". "));
    }

    public static function daily_limit(): int
    {
        return max(0, (int)($_ENV['CLOUD_DAILY_LIMIT'] ?? 10));
    }

    /**
     * @param string $name
     * @return string|null why the address can't be had, null when it's free
     */
    public static function address_problem($name): ?string
    {
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{1,28}[a-z0-9])$/', $name)) {
            return "The address must be 3 to 30 lower-case letters, numbers or hyphens, not starting or ending with a hyphen.";
        }
        if (in_array($name, self::RESERVED, true) || strpos($name, "--") !== false) {
            return "That address is reserved. Try another.";
        }
        //this blog itself may live under the same domain (islay.treatmag.online)
        $own = strtolower((string)parse_url((string)($_ENV['APP_URL'] ?? ""), PHP_URL_HOST));
        if ($own !== "" && $own === $name . "." . self::domain()) {
            return "That address is taken. Try another.";
        }
        self::expire_unconfirmed();
        $db = new Mysql();
        $row = $db->Fetch("SELECT 1 AS yes FROM `cloud_blogs` WHERE `subdomain` = :a AND `state` <> 'deleted'", array(":a" => $name));
        return $row[0] != '00000' ? "That address is taken. Try another." : null;
    }

    private static function expire_unconfirmed(): void
    {
        $db = new Mysql();
        $db->Fetch("DELETE FROM `cloud_blogs` WHERE `state` = 'unconfirmed' AND `timestamp` < DATE_SUB(NOW(), INTERVAL " . self::UNCONFIRMED_HOURS . " HOUR)", null);
    }

    /**
     * Someone on the create-a-blog page. Emails a link to confirm; nothing is built before that.
     *
     * @param array $request subdomain, blog_name, username, email
     * @param string $ip
     * @return void
     * @throws Exception with a message for the form
     */
    public static function request($request, $ip): void
    {
        if (!self::enabled()) {
            throw new Exception("Creating blogs isn't open here.");
        }
        $name = strtolower(trim((string)($request['subdomain'] ?? "")));
        $blog_name = trim(preg_replace('/\s+/u', " ", strip_tags((string)($request['blog_name'] ?? ""))));
        $username = trim((string)($request['username'] ?? ""));
        $email = trim((string)($request['email'] ?? ""));
        if (($problem = self::address_problem($name)) !== null) {
            throw new Exception($problem);
        }
        if ($blog_name === "" || mb_strlen($blog_name) > 60) {
            throw new Exception("Give your blog a name of up to 60 characters.");
        }
        if (!preg_match('/^[A-Za-z0-9_.\-]{3,32}$/', $username)) {
            throw new Exception("Your username must be 3 to 32 letters, numbers, . _ or -.");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 128) {
            throw new Exception("Enter an email address we can send the link to.");
        }

        $db = new Mysql();
        $ip_hash = hash("sha256", "ootd cloud " . ($_ENV['PWD_SEED'] ?? "") . " " . $ip);
        $today = $db->Fetch("SELECT COUNT(*) AS n FROM `cloud_blogs` WHERE `ip_hash` = :a AND `timestamp` > DATE_SUB(NOW(), INTERVAL 1 DAY)", array(":a" => $ip_hash));
        if ((int)$today[0]->n >= self::PER_IP_PER_DAY) {
            throw new Exception("You've asked for a few blogs today already. Try again tomorrow.");
        }
        $all = $db->Fetch("SELECT COUNT(*) AS n FROM `cloud_blogs` WHERE `state` <> 'unconfirmed' AND `confirmed_at` > DATE_SUB(NOW(), INTERVAL 1 DAY)", null);
        if ((int)$all[0]->n >= self::daily_limit()) {
            throw new Exception("We've made as many new blogs as we can today. Try again tomorrow.");
        }

        $token = bin2hex(random_bytes(32));
        $cloud_id = bin2hex(random_bytes(16));
        $db->Fetch("INSERT INTO `cloud_blogs` (`cloud_id`, `subdomain`, `domain`, `blog_name`, `username`, `email`, `state`, `confirm_hash`, `ip_hash`)
                    VALUES (:a, :b, :c, :d, :e, :f, 'unconfirmed', :g, :h)",
            array(":a" => $cloud_id, ":b" => $name, ":c" => $name . "." . self::domain(), ":d" => mb_substr($blog_name, 0, 60), ":e" => $username,
                ":f" => $email, ":g" => hash("sha256", $token), ":h" => $ip_hash));

        Mailer::queue($email, "Confirm your new blog, " . $name . "." . self::domain(),
            "Someone (we hope you) asked " . instance_name() . " for a new TREAT blog called \"" . $blog_name . "\" at\n"
            . "https://" . $name . "." . self::domain() . "\n\n"
            . "Confirm it here and we'll start setting it up (the link works for " . self::UNCONFIRMED_HOURS . " hours):\n"
            . absolute_url("/create-blog/confirm?t=" . $token) . "\n\n"
            . "If it wasn't you, ignore this email and nothing will be made.\n");
    }

    /**
     * @param string $token from the email
     * @return object|null the cloud_blogs row, queued for building
     */
    public static function confirm($token)
    {
        if (!preg_match('/^[a-f0-9]{64}$/', (string)$token)) {
            return null;
        }
        self::expire_unconfirmed();
        $db = new Mysql();
        $row = $db->Fetch("SELECT * FROM `cloud_blogs` WHERE `confirm_hash` = :a", array(":a" => hash("sha256", $token)));
        if ($row[0] == '00000') {
            return null;
        }
        if ($row[0]->state === "unconfirmed") {
            $all = $db->Fetch("SELECT COUNT(*) AS n FROM `cloud_blogs` WHERE `state` <> 'unconfirmed' AND `confirmed_at` > DATE_SUB(NOW(), INTERVAL 1 DAY)", null);
            if ((int)$all[0]->n >= self::daily_limit()) {
                return $row[0];
            }
            $db->Fetch("UPDATE `cloud_blogs` SET `state` = 'queued', `confirmed_at` = NOW() WHERE `cloud_num` = :a", array(":a" => $row[0]->cloud_num));
            Jobs::dispatch("cloud_build", array("cloud_id" => $row[0]->cloud_id), 0, true);
            $row[0]->state = "queued";
        }
        return $row[0];
    }

    public static function get($cloud_id)
    {
        $db = new Mysql();
        $row = $db->Fetch("SELECT * FROM `cloud_blogs` WHERE `cloud_id` = :a", array(":a" => (string)$cloud_id));
        return $row[0] != '00000' ? $row[0] : null;
    }

    private static function password($length = 24): string
    {
        //Plesk's password rules want upper, lower, digits and a symbol
        $chars = "abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789";
        $out = "";
        for ($i = 0; $i < $length; $i++) {
            $out .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return "Aa1-" . $out;
    }

    private static function secrets($blog): array
    {
        return empty($blog->secrets) ? array() : (json_decode(ActorKey::decrypt($blog->secrets), true) ?: array());
    }

    private static function save($blog, $fields): void
    {
        $sets = array();
        $params = array(":id" => (int)$blog->cloud_num);
        foreach ($fields as $column => $value) {
            $sets[] = "`" . $column . "` = :" . $column;
            $params[":" . $column] = $value;
            $blog->$column = $value;
        }
        $db = new Mysql();
        $db->Fetch("UPDATE `cloud_blogs` SET " . implode(", ", $sets) . " WHERE `cloud_num` = :id", $params);
    }

    /**
     * Job: build a confirmed blog, carrying on from the last step that finished
     *
     * @param array $payload cloud_id
     * @return void
     * @throws Exception to retry
     */
    public static function build($payload): void
    {
        $blog = self::get($payload['cloud_id'] ?? "");
        if (!$blog || !in_array($blog->state, array("queued", "building"), true)) {
            return;
        }
        if (!self::enabled()) {
            throw new Exception("Cloud blogs aren't configured");
        }
        $secrets = self::secrets($blog);
        $keep = function () use ($blog, &$secrets) {
            self::save($blog, array("secrets" => ActorKey::encrypt(json_encode($secrets))));
        };
        self::save($blog, array("state" => "building"));
        try {
            $start = array_search($blog->step ?: "customer", self::STEPS, true);
            foreach (array_slice(self::STEPS, $start === false ? 0 : $start) as $step) {
                self::save($blog, array("step" => $step));
                switch ($step) {
                    case "customer":
                        if (empty($blog->plesk_customer_id)) {
                            $id = PleskApi::add_customer($blog->blog_name, "ootd" . (int)$blog->cloud_num . "-" . substr($blog->cloud_id, 0, 6),
                                self::password(), $blog->email);
                            self::save($blog, array("plesk_customer_id" => $id));
                        }
                        break;
                    case "webspace":
                        if (empty($blog->plesk_webspace_id)) {
                            $secrets['ftp_login'] = "ootd" . (int)$blog->cloud_num . substr($blog->cloud_id, 0, 6);
                            $secrets['ftp_password'] = self::password();
                            $keep();
                            $id = PleskApi::add_webspace($blog->domain, $blog->plesk_customer_id, $_ENV['CLOUD_SERVER_IP'], $_ENV['CLOUD_PLAN'],
                                $secrets['ftp_login'], $secrets['ftp_password']);
                            self::save($blog, array("plesk_webspace_id" => $id));
                        }
                        break;
                    case "database":
                        if (empty($blog->plesk_db_id)) {
                            $secrets['db_name'] = "ootd_" . (int)$blog->cloud_num . "_" . substr($blog->cloud_id, 0, 6);
                            $secrets['db_user'] = "ootd" . (int)$blog->cloud_num . "_" . substr($blog->cloud_id, 6, 6);
                            $secrets['db_pass'] = self::password();
                            $keep();
                            $id = PleskApi::add_database($blog->plesk_webspace_id, $secrets['db_name']);
                            self::save($blog, array("plesk_db_id" => $id));
                            PleskApi::add_database_user($id, $secrets['db_user'], $secrets['db_pass']);
                        }
                        break;
                    case "dns":
                        if (empty($blog->dns_record_id)) {
                            self::save($blog, array("dns_record_id" => CloudDns::point($blog->domain, $_ENV['CLOUD_SERVER_IP'])));
                        }
                        break;
                    case "upload":
                        $secrets['setup_token'] = bin2hex(random_bytes(32));
                        $secrets['setup_script'] = "ootd-setup-" . bin2hex(random_bytes(8)) . ".php";
                        $secrets['cron_key'] = $secrets['cron_key'] ?? bin2hex(random_bytes(24));
                        $keep();
                        $script = tempnam(sys_get_temp_dir(), "ootd");
                        file_put_contents($script, CloudDeploy::setup_script(array(
                            "db_host" => (string)($_ENV['CLOUD_DB_HOST'] ?? "localhost"),
                            "db_name" => $secrets['db_name'], "db_user" => $secrets['db_user'], "db_pass" => $secrets['db_pass'],
                            "app_url" => "https://" . $blog->domain, "username" => $blog->username, "email" => $blog->email,
                            "blog_name" => $blog->blog_name, "cron_key" => $secrets['cron_key']
                        ), $secrets['setup_token']));
                        try {
                            CloudDeploy::upload($blog->domain, $secrets['ftp_login'], $secrets['ftp_password'],
                                array("ootd-package.zip" => CloudDeploy::package(), $secrets['setup_script'] => $script));
                        } finally {
                            unlink($script);
                        }
                        break;
                    case "setup":
                        try {
                            $answer = CloudDeploy::run_setup($blog->domain, $secrets['setup_script'], $secrets['setup_token']);
                        } catch (Exception $e) {
                            //the script removes itself either way; the next try uploads a new one
                            self::save($blog, array("step" => "upload"));
                            throw $e;
                        }
                        $secrets['reset'] = $answer['reset'];
                        $keep();
                        break;
                    case "https":
                        //the owner's link is https, and other blogs only talk https
                        if (!CloudDeploy::https_ready($blog->domain)) {
                            $secrets['https_since'] = $secrets['https_since'] ?? time();
                            $keep();
                            $hours = max(1, (int)($_ENV['CLOUD_HTTPS_WAIT_HOURS'] ?? 6));
                            if (time() - $secrets['https_since'] > $hours * 3600) {
                                throw new PermanentFailure("No working HTTPS certificate after " . $hours . " hours. In Plesk, turn on SSL It! "
                                    . "\"Keep websites secured\" (or secure " . $blog->domain . " by hand), then retry.");
                            }
                            self::save($blog, array("last_error" => "Waiting for its HTTPS certificate"));
                            //a new job each time: this one is still running, so a unique one would find itself
                            Jobs::dispatch("cloud_build", array("cloud_id" => $blog->cloud_id, "check" => time()), 300);
                            return;
                        }
                        break;
                    case "email":
                        Mailer::queue($blog->email, "Your blog " . $blog->domain . " is ready",
                            "Your TREAT blog \"" . $blog->blog_name . "\" is ready:\nhttps://" . $blog->domain . "\n\n"
                            . "You're its admin, " . $blog->username . ". Choose your password here (the link works for 7 days):\n"
                            . "https://" . $blog->domain . "/reset?token=" . $secrets['reset'] . "\n\n"
                            . "Then log in with " . $blog->email . " and that password. From /admin you can name it, add a logo, invite people\n"
                            . "and decide who can join. People on other blogs (and Mastodon) can follow you as\n"
                            . $blog->username . "@" . $blog->domain . "\n");
                        break;
                }
            }
        } catch (Exception $e) {
            self::save($blog, array("last_error" => mb_substr($e->getMessage(), 0, 1000)));
            throw $e;
        }
        //only the cron key is still needed, to wake the blog up every minute
        self::save($blog, array("state" => "ready", "step" => null, "ready_at" => date("Y-m-d H:i:s"), "last_error" => null,
            "secrets" => ActorKey::encrypt(json_encode(array("cron_key" => $secrets['cron_key'])))));
    }

    /**
     * When the build job gives up for good
     */
    public static function failed($cloud_id, $error): void
    {
        $blog = self::get($cloud_id);
        if ($blog && in_array($blog->state, array("queued", "building"), true)) {
            self::save($blog, array("state" => "failed", "last_error" => mb_substr((string)$error, 0, 1000)));
        }
    }

    /**
     * @return array every blog made from here that hasn't been deleted, newest first
     */
    public static function all(): array
    {
        self::expire_unconfirmed();
        $db = new Mysql();
        //builds whose job has given up for good
        $stuck = $db->Fetch("SELECT `payload`, `last_error` FROM `jobs` WHERE `type` = 'cloud_build' AND `failed_at` IS NOT NULL", null);
        foreach ($stuck[0] != '00000' ? $stuck : array() as $job) {
            self::failed(json_decode((string)$job->payload, true)['cloud_id'] ?? "", $job->last_error);
        }
        $rows = $db->Fetch("SELECT * FROM `cloud_blogs` WHERE `state` <> 'deleted' ORDER BY `cloud_num` DESC", null);
        return $rows[0] != '00000' ? $rows : array();
    }

    /**
     * Admin: try a failed build again
     */
    public static function retry($cloud_id): void
    {
        $blog = self::get($cloud_id);
        if (!$blog || !in_array($blog->state, array("failed", "queued", "building"), true)) {
            throw new Exception("Nothing to retry");
        }
        //a job that gave up would mark it failed again from all(); a pending https check would run alongside the new one
        (new Mysql())->Fetch("DELETE FROM `jobs` WHERE `type` = 'cloud_build' AND `done_at` IS NULL AND `locked_at` IS NULL AND `payload` LIKE :a",
            array(":a" => "%" . $blog->cloud_id . "%"));
        $secrets = self::secrets($blog);
        unset($secrets['https_since']);
        self::save($blog, array("state" => "queued", "last_error" => null, "secrets" => $secrets ? ActorKey::encrypt(json_encode($secrets)) : null));
        Jobs::dispatch("cloud_build", array("cloud_id" => $blog->cloud_id), 0, true);
    }

    /**
     * Admin: suspend (Plesk stops serving it) or resume a blog
     */
    public static function set_active($cloud_id, $active): void
    {
        $blog = self::get($cloud_id);
        if (!$blog || empty($blog->plesk_webspace_id) || !in_array($blog->state, array("ready", "suspended"), true)) {
            throw new Exception("That blog can't be " . ($active ? "resumed" : "suspended"));
        }
        PleskApi::set_webspace_active($blog->plesk_webspace_id, $active);
        self::save($blog, array("state" => $active ? "ready" : "suspended"));
    }

    /**
     * Admin: delete the subscription, its customer and its DNS record. The address can be had again.
     */
    public static function delete($cloud_id): void
    {
        $blog = self::get($cloud_id);
        if (!$blog || $blog->state == "deleted") {
            throw new Exception("Blog not found");
        }
        if (!empty($blog->plesk_webspace_id)) {
            PleskApi::delete_webspace($blog->plesk_webspace_id);
        }
        if (!empty($blog->plesk_customer_id)) {
            PleskApi::delete_customer($blog->plesk_customer_id);
        }
        if (!empty($blog->dns_record_id)) {
            CloudDns::remove($blog->dns_record_id);
        }
        self::save($blog, array("state" => "deleted", "secrets" => null, "plesk_webspace_id" => null, "plesk_customer_id" => null,
            "plesk_db_id" => null, "dns_record_id" => null, "confirm_hash" => null));
    }

    /**
     * Every minute (from schedule_daily, which cron and the worker call): ask each ready blog to run
     * its jobs, all at once, without waiting long for any of them
     */
    public static function ping_due(): void
    {
        if (!self::enabled()) {
            return;
        }
        $db = new Mysql();
        $rows = $db->Fetch("SELECT * FROM `cloud_blogs` WHERE `state` = 'ready' AND (`pinged_at` IS NULL OR `pinged_at` < DATE_SUB(NOW(), INTERVAL 55 SECOND))", null);
        if ($rows[0] == '00000') {
            return;
        }
        $multi = curl_multi_init();
        $handles = array();
        foreach ($rows as $blog) {
            $key = self::secrets($blog)['cron_key'] ?? "";
            if ($key === "") {
                continue;
            }
            $base = (string)($_ENV['CLOUD_SETUP_URL'] ?? "");
            //https: plans may redirect http, which a POST doesn't survive. Ready blogs have a real certificate.
            $curl = curl_init(($base !== "" ? rtrim($base, "/") : "https://" . $blog->domain) . "/cron");
            $options = array(CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(array("key" => $key)), CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10, CURLOPT_HTTPHEADER => array("Host: " . $blog->domain));
            if ($base === "") {
                $options[CURLOPT_RESOLVE] = array($blog->domain . ":443:" . $_ENV['CLOUD_SERVER_IP']);
            }
            curl_setopt_array($curl, $options);
            curl_multi_add_handle($multi, $curl);
            $handles[] = $curl;
            $db->Fetch("UPDATE `cloud_blogs` SET `pinged_at` = NOW() WHERE `cloud_num` = :a", array(":a" => $blog->cloud_num));
        }
        do {
            $status = curl_multi_exec($multi, $running);
            if ($running) {
                curl_multi_select($multi, 1);
            }
        } while ($running && $status == CURLM_OK);
        foreach ($handles as $curl) {
            curl_multi_remove_handle($multi, $curl);
            curl_close($curl);
        }
        curl_multi_close($multi);
    }
}
