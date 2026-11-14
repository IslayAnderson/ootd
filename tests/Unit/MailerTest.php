<?php

use PHPUnit\Framework\TestCase;

class MailerTest extends TestCase
{
    private $saved;

    protected function setUp(): void
    {
        $this->saved = $_ENV;
        foreach (array("MAIL_DRIVER", "MAIL_HOST", "MAIL_FROM") as $key) {
            unset($_ENV[$key]);
        }
        $_ENV['APP_URL'] = "https://ootd.example.com";
    }

    protected function tearDown(): void
    {
        $_ENV = $this->saved;
    }

    public function testExplicitDriverWins(): void
    {
        $_ENV['MAIL_HOST'] = "smtp.example.com";
        $_ENV['MAIL_DRIVER'] = "log";
        $this->assertSame("log", Mailer::driver());
        $this->assertFalse(Mailer::enabled());
    }

    public function testSmtpWhenHostSet(): void
    {
        $_ENV['MAIL_HOST'] = "smtp.example.com";
        $this->assertSame("smtp", Mailer::driver());
    }

    public function testFallsBackToMailOrLog(): void
    {
        $sendmail = explode(" ", trim((string)ini_get("sendmail_path")))[0];
        $this->assertSame($sendmail !== "" && is_executable($sendmail) ? "mail" : "log", Mailer::driver());
    }

    public function testFromDefaultsToNoreplyAtSiteDomain(): void
    {
        $this->assertSame("noreply@ootd.example.com", Mailer::from());
        $_ENV['MAIL_FROM'] = "hello@example.com";
        $this->assertSame("hello@example.com", Mailer::from());
    }

    public function testNoFromAddressOnLocalhost(): void
    {
        $_ENV['APP_URL'] = "http://localhost:8089";
        $_ENV['MAIL_DRIVER'] = "mail";
        $this->assertSame("", Mailer::from());
        $this->assertFalse(Mailer::enabled());
    }
}
