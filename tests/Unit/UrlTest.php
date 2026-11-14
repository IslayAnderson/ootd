<?php
use PHPUnit\Framework\TestCase;

class UrlTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['APP_URL'], $_SERVER['HTTP_HOST'], $_SERVER['HTTPS']);
    }

    public function testAppUrlFromEnvDropsTrailingSlash(): void
    {
        $_ENV['APP_URL'] = "https://ootd.example/";
        $this->assertSame("https://ootd.example", app_url());
        $this->assertSame("https://ootd.example/outfit/x", absolute_url("/outfit/x"));
        $this->assertSame("https://ootd.example/outfit/x", absolute_url("outfit/x"));
    }

    public function testAppUrlFallsBackToRequest(): void
    {
        unset($_ENV['APP_URL']);
        $_SERVER['HTTP_HOST'] = "local.test:8089";
        $this->assertSame("http://local.test:8089", app_url());
        $_SERVER['HTTPS'] = "on";
        $this->assertSame("https://local.test:8089", app_url());
    }
}
