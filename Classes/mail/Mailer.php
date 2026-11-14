<?php

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Plain-text email over SMTP, configured in .env:
 * MAIL_HOST, MAIL_PORT (587), MAIL_USER, MAIL_PASS, MAIL_ENCRYPTION (tls, ssl or none), MAIL_FROM, MAIL_FROM_NAME.
 * Without MAIL_HOST nothing is sent; messages are written to tmp/mail.log instead, so a fresh install
 * (or a developer) can still see password reset and invite links.
 * Mail is sent from the job queue (Mailer::queue), so a slow SMTP server never holds up a page.
 */
class Mailer
{
    /**
     * @return bool is SMTP configured
     */
    public static function enabled(): bool
    {
        return !empty($_ENV['MAIL_HOST']) && !empty($_ENV['MAIL_FROM']);
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
     * @throws Exception when the SMTP server refuses it, so the job is retried
     */
    public static function send($to, $subject, $body): void
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Not an email address");
        }
        if (!self::enabled()) {
            $log = $_SERVER['DOCUMENT_ROOT'] . "/tmp/mail.log";
            file_put_contents($log, "---- " . date("c") . "\nTo: " . $to . "\nSubject: " . $subject . "\n\n" . $body . "\n", FILE_APPEND | LOCK_EX);
            @chmod($log, 0600);
            return;
        }

        $mail = new PHPMailer(true);
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
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->setFrom($_ENV['MAIL_FROM'], $_ENV['MAIL_FROM_NAME'] ?? instance_name());
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->isHTML(false);
        $mail->send();
    }
}
