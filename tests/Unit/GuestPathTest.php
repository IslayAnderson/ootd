<?php
use PHPUnit\Framework\TestCase;

class GuestPathTest extends TestCase
{
    public static function allowed(): array
    {
        return array(array("/login"), array("/privacy"), array("/@mia"), array("/user/mia"),
            array("/user/mia/blog/abc123"), array("/outfit/abc"), array("/garment/abc"), array("/media/abc123/thumb"));
    }

    public static function refused(): array
    {
        return array(array("/feed"), array("/account"), array("/notifications"), array("/admin"),
            array("/API/follow/create.php"), array("/user/mia/extra/deep"), array("/media/abc/original"), array("/login/../admin"));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('allowed')]
    public function testAllowed($path): void
    {
        $this->assertTrue(guest_path_allowed($path));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('refused')]
    public function testRefused($path): void
    {
        $this->assertFalse(guest_path_allowed($path));
    }
}
