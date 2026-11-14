<?php
use PHPUnit\Framework\TestCase;

class SafeHttpTest extends TestCase
{
    public static function blocked(): array
    {
        return array(array("127.0.0.1"), array("10.1.2.3"), array("172.16.0.5"), array("192.168.1.1"), array("169.254.169.254"),
            array("100.64.0.1"), array("0.0.0.0"), array("::1"), array("fc00::1"), array("fe80::1"), array("::ffff:127.0.0.1"), array("not an ip"));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('blocked')]
    public function testBlocked($ip): void
    {
        $this->assertFalse(SafeHttp::public_ip($ip));
    }

    public function testPublicAllowed(): void
    {
        $this->assertTrue(SafeHttp::public_ip("93.184.216.34"));
        $this->assertTrue(SafeHttp::public_ip("2606:4700::6810:85e5"));
    }

    public function testOnlyHttps(): void
    {
        unset($_ENV['AP_ALLOW_HTTP']);
        $this->expectExceptionMessage("https");
        SafeHttp::check("http://93.184.216.34/");
    }

    public function testPrivateAddressRefused(): void
    {
        unset($_ENV['AP_ALLOW_PRIVATE']);
        $this->expectExceptionMessage("not allowed");
        SafeHttp::check("https://127.0.0.1/");
    }

    public function testCredentialsInUrlRefused(): void
    {
        $this->expectExceptionMessage("Bad address");
        SafeHttp::check("https://user:pass@93.184.216.34/");
    }
}
