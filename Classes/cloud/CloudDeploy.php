<?php

/**
 * Puts a copy of OOTD into a new cloud blog's subscription and installs it.
 *
 * 1. package(): a zip of this blog's own code (vendor included, config and data left out), built
 *    once per version in tmp/cloud.
 * 2. upload(): the zip and a one-time setup script go to the subscription's httpdocs over FTP
 *    (FTPS when the server offers it), with the FTP account Plesk made with the subscription.
 *    CLOUD_DEPLOY_PATH=/some/dir copies them into /some/dir/<domain>/httpdocs instead (tests).
 * 3. run_setup(): POSTs the setup script's token to it. The script unpacks the zip, writes .env,
 *    runs the normal installer with the new blog's admin, names the blog, makes a "choose your
 *    password" link and deletes itself.
 */
class CloudDeploy
{
    //left out of the package: config, data, development files
    const EXCLUDE = array(".env", ".git", ".github", ".claude", ".idea", "tmp", "storage", "tests", "docs", "node_modules", "docker",
        "Dockerfile", "docker-compose.yml", "phpunit.xml", ".DS_Store", ".phpunit.result.cache");

    /**
     * @return string path of the zip for this version
     * @throws Exception
     */
    public static function package(): string
    {
        $root = $_SERVER['DOCUMENT_ROOT'];
        $dir = $root . "/tmp/cloud";
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $path = $dir . "/ootd-" . OOTD_VERSION . ".zip";
        if (is_file($path)) {
            return $path;
        }
        foreach (glob($dir . "/ootd-*.zip") ?: array() as $old) {
            unlink($old);
        }
        $zip = new ZipArchive();
        if ($zip->open($path . ".part", ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception("Couldn't build the package");
        }
        $files = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            function ($file, $key, $iterator) use ($root) {
                $relative = substr($file->getPathname(), strlen($root) + 1);
                return !in_array(explode("/", $relative)[0], self::EXCLUDE, true) && $file->getFilename() !== ".DS_Store";
            }
        ));
        foreach ($files as $file) {
            if ($file->isFile()) {
                $zip->addFile($file->getPathname(), substr($file->getPathname(), strlen($root) + 1));
            }
        }
        if (!$zip->close()) {
            throw new Exception("Couldn't write the package");
        }
        rename($path . ".part", $path);
        return $path;
    }

    /**
     * The one-time setup script. Everything it needs is written into it; the token that runs it
     * only goes over the POST and is stored as a hash.
     *
     * @param array $config db_host, db_name, db_user, db_pass, app_url, username, email, blog_name, cron_key
     * @param string $token
     * @return string PHP source
     */
    public static function setup_script($config, $token): string
    {
        $values = var_export(array_merge($config, array("token_hash" => hash("sha256", $token))), true);
        return <<<PHP
<?php
//One-time setup for a new OOTD cloud blog, uploaded by the blog that created it. Deletes itself.
\$c = $values;
header("Content-Type: application/json");
if (!hash_equals(\$c['token_hash'], hash("sha256", (string)(\$_POST['token'] ?? "")))) {
    http_response_code(404);
    exit();
}
@set_time_limit(600);
\$root = __DIR__;
\$done = function (\$answer) {
    @unlink(__FILE__);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header("Content-Type: application/json", true, empty(\$answer['ok']) ? 500 : 200);
    echo json_encode(\$answer);
    exit();
};
try {
    if (!is_file(\$root . "/index.php")) {
        \$zip = new ZipArchive();
        if (\$zip->open(\$root . "/ootd-package.zip") !== true || !\$zip->extractTo(\$root)) {
            throw new Exception("Couldn't unpack");
        }
        \$zip->close();
    }
    @unlink(\$root . "/ootd-package.zip");
    //Plesk's placeholder page
    @unlink(\$root . "/index.html");
    foreach (array("tmp", "storage") as \$dir) {
        if (!is_dir(\$root . "/" . \$dir)) {
            mkdir(\$root . "/" . \$dir, 0750, true);
        }
    }
    \$_SERVER['DOCUMENT_ROOT'] = \$root;
    chdir(\$root);
    if (!is_file(\$root . "/.env") || filesize(\$root . "/.env") == 0) {
        //the installer's web path reads these from the form
        \$_POST = array("host" => \$c['db_host'], "schema" => \$c['db_name'], "user" => \$c['db_user'], "password" => \$c['db_pass'], "app_url" => \$c['app_url']);
        \$GLOBALS['cli_admin'] = array("username" => \$c['username'], "email" => \$c['email'],
            "password" => bin2hex(random_bytes(24)), "first_name" => "", "last_name" => "");
        ob_start();
        require \$root . "/install_actions.php";
        ob_end_clean();
        file_put_contents(\$root . "/.env", "CRON_KEY=" . \$c['cron_key'] . "\\n", FILE_APPEND);
        @chmod(\$root . "/.env", 0600);
    } else {
        require \$root . "/Functions/functions.php";
        require \$root . "/Classes/classes.php";
        require \$root . "/vendor/autoload.php";
        Dotenv\Dotenv::createMutable(\$root)->load();
    }
    setting_set("instance_name", \$c['blog_name']);
    \$db = new Mysql();
    \$user = \$db->Fetch("SELECT `user_id` FROM `users` WHERE `username` = :a", array(":a" => \$c['username']));
    if (\$user[0] == '00000') {
        throw new Exception("The admin account wasn't made");
    }
    \$reset = bin2hex(random_bytes(32));
    \$db->Fetch("INSERT INTO `password_resets` (`user_id`, `token_hash`, `expires_at`) VALUES (:a, :b, DATE_ADD(NOW(), INTERVAL 7 DAY))",
        array(":a" => (int)\$user[0]->user_id, ":b" => hash("sha256", \$reset)));
    \$done(array("ok" => true, "reset" => \$reset, "version" => OOTD_VERSION));
} catch (Throwable \$e) {
    \$done(array("ok" => false, "error" => \$e->getMessage()));
}
PHP;
    }

    /**
     * @param string $domain
     * @param string $ftp_login
     * @param string $ftp_password
     * @param array $files remote name => local path
     * @return void
     * @throws Exception
     */
    public static function upload($domain, $ftp_login, $ftp_password, $files): void
    {
        $local = (string)($_ENV['CLOUD_DEPLOY_PATH'] ?? "");
        if ($local !== "") {
            $target = rtrim($local, "/") . "/" . $domain . "/httpdocs";
            if (!is_dir($target)) {
                throw new Exception("No httpdocs for " . $domain);
            }
            foreach ($files as $name => $path) {
                copy($path, $target . "/" . $name);
            }
            return;
        }
        $host = (string)($_ENV['CLOUD_FTP_HOST'] ?? $_ENV['CLOUD_SERVER_IP'] ?? "");
        $ftp = function_exists("ftp_ssl_connect") ? @ftp_ssl_connect($host, 21, 30) : false;
        if (!$ftp || !@ftp_login($ftp, $ftp_login, $ftp_password)) {
            //no FTPS: plain FTP to this same server
            $ftp = @ftp_connect($host, 21, 30);
            if (!$ftp || !@ftp_login($ftp, $ftp_login, $ftp_password)) {
                throw new Exception("Couldn't log in to FTP for " . $domain);
            }
        }
        ftp_pasv($ftp, true);
        if (!@ftp_chdir($ftp, "httpdocs")) {
            ftp_close($ftp);
            throw new Exception("No httpdocs for " . $domain);
        }
        foreach ($files as $name => $path) {
            if (!@ftp_put($ftp, $name, $path, FTP_BINARY)) {
                ftp_close($ftp);
                throw new Exception("Upload of " . $name . " failed");
            }
        }
        ftp_close($ftp);
    }

    /**
     * @param string $domain
     * @return bool the blog answers over https with a certificate that checks out
     */
    public static function https_ready($domain): bool
    {
        $base = (string)($_ENV['CLOUD_HTTPS_URL'] ?? "");
        $curl = curl_init(($base !== "" ? rtrim($base, "/") : "https://" . $domain) . "/login");
        $options = array(CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 10);
        if ($base !== "") {
            $options[CURLOPT_HTTPHEADER] = array("Host: " . $domain);
        } elseif (!CloudDns::cloudflare() && !empty($_ENV['CLOUD_SERVER_IP'])) {
            //this server's own certificate, before DNS caches have caught up
            $options[CURLOPT_RESOLVE] = array($domain . ":443:" . $_ENV['CLOUD_SERVER_IP']);
        }
        curl_setopt_array($curl, $options);
        curl_exec($curl);
        $ok = curl_errno($curl) === 0 && curl_getinfo($curl, CURLINFO_RESPONSE_CODE) == 200;
        curl_close($curl);
        return $ok;
    }

    /**
     * @param string $domain
     * @param string $script file name of the setup script
     * @param string $token
     * @return array the script's answer (ok, reset)
     * @throws Exception
     */
    public static function run_setup($domain, $script, $token): array
    {
        //straight to this server by IP, so it works before DNS and certificates have caught up
        $base = (string)($_ENV['CLOUD_SETUP_URL'] ?? "");
        $curl = curl_init(($base !== "" ? rtrim($base, "/") : "http://" . $domain) . "/" . $script);
        $options = array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(array("token" => $token)),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_HTTPHEADER => array("Host: " . $domain)
        );
        if ($base === "" && !empty($_ENV['CLOUD_SERVER_IP'])) {
            $options[CURLOPT_RESOLVE] = array($domain . ":80:" . $_ENV['CLOUD_SERVER_IP']);
        }
        curl_setopt_array($curl, $options);
        $response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        $answer = json_decode((string)$response, true);
        if (!is_array($answer)) {
            throw new Exception("Setup didn't answer (" . ($error ?: "HTTP " . $status) . ")");
        }
        if (empty($answer['ok'])) {
            throw new Exception("Setup failed: " . ($answer['error'] ?? "unknown"));
        }
        return $answer;
    }
}
