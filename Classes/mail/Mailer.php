<?php

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Plain-text email, configured in .env. MAIL_DRIVER picks how it's sent:
 * - smtp: MAIL_HOST, MAIL_PORT (587), MAIL_USER, MAIL_PASS, MAIL_ENCRYPTION (tls, ssl or none)
 * - mail: PHP's mail(), through the server's own sendmail (most PHP hosts, Plesk and cPanel included)
 * - log: nothing is sent; messages go to tmp/mail.log, so a developer can still see reset and invite links
 * Without MAIL_DRIVER: smtp when MAIL_HOST is set, otherwise mail when the server has sendmail,
 * otherwise log. MAIL_FROM (default noreply@ the site's domain) and MAIL_FROM_NAME apply to both.
 * Mail is sent from the job queue (Mailer::queue), so a slow mail server never holds up a page.
 */
class Mailer
{
    /**
     * @return string smtp, mail or log
     */
    public static function driver(): string
    {
        $driver = strtolower(trim((string)($_ENV['MAIL_DRIVER'] ?? "")));
        if (in_array($driver, array("smtp", "mail", "log"), true)) {
            return $driver;
        }
        if (!empty($_ENV['MAIL_HOST'])) {
            return "smtp";
        }
        $sendmail = explode(" ", trim((string)ini_get("sendmail_path")))[0];
        return $sendmail !== "" && is_executable($sendmail) ? "mail" : "log";
    }

    /**
     * @return bool does email actually leave the server
     */
    public static function enabled(): bool
    {
        return self::driver() != "log" && self::from() !== "";
    }

    /**
     * @return string the From address: MAIL_FROM, or noreply@ the site's domain
     */
    public static function from(): string
    {
        if (!empty($_ENV['MAIL_FROM'])) {
            return (string)$_ENV['MAIL_FROM'];
        }
        $host = (string)parse_url(app_url(), PHP_URL_HOST);
        $address = "noreply@" . $host;
        return strpos($host, ".") !== false && filter_var($address, FILTER_VALIDATE_EMAIL) ? $address : "";
    }

    /**
     * @param string $to
     * @param string $subject
     * @param string $body plain text
     * @return void
     */
    public static function queue($to, $subject, $body): void
    {
        Jobs::dispatch("mail", array("to" => $to, "subject" => $subject, "body" => $body));
    }

    /**
     * @param string $to
     * @param string $subject
     * @param string $body
     * @return void
     * @throws Exception when the mail server refuses it, so the job is retried
     */
    public static function send($to, $subject, $body): void
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new PermanentFailure("Not an email address");
        }
        if (!self::enabled()) {
            $log = $_SERVER['DOCUMENT_ROOT'] . "/tmp/mail.log";
            file_put_contents($log, "---- " . date("c") . "\nTo: " . $to . "\nSubject: " . $subject . "\n\n" . $body . "\n", FILE_APPEND | LOCK_EX);
            @chmod($log, 0600);
            return;
        }

        $mail = new PHPMailer(true);
        if (self::driver() == "mail") {
            $mail->isMail();
            //envelope sender, so bounces go somewhere real
            $mail->Sender = self::from();
        } else {
            self::smtp($mail);
        }
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->setFrom(self::from(), ($_ENV["MAIL_FROM_NAME"] ?? "") ?: instance_name());
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->isHTML(false);
        $mail->send();
    }

    private static function smtp(PHPMailer $mail): void
    {
        $mail->isSMTP();
        $mail->Host = $_ENV['MAIL_HOST'];
        $mail->Port = (int)($_ENV['MAIL_PORT'] ?? 587);
        $encryption = strtolower($_ENV['MAIL_ENCRYPTION'] ?? "tls");
        if ($encryption == "ssl") {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption == "tls") {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = "";
            $mail->SMTPAutoTLS = false;
        }
        if (!empty($_ENV['MAIL_USER'])) {
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['MAIL_USER'];
            $mail->Password = $_ENV['MAIL_PASS'] ?? "";
        }
    }
}
