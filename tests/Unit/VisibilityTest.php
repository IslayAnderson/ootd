<?php
use PHPUnit\Framework\TestCase;

class VisibilityTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
    }

    public function testNormaliseKeepsKnownValues(): void
    {
        foreach (array("public", "members", "followers", "private") as $value) {
            $this->assertSame($value, visibility_normalise($value));
        }
    }

    public function testNormaliseFallsBackToMembers(): void
    {
        $this->assertSame("members", visibility_normalise("everyone"));
        $this->assertSame("members", visibility_normalise(null));
        $this->assertSame("members", visibility_normalise(""));
    }

    public function testGuestSeesOnlyPublic(): void
    {
        unset($_SESSION['user_id']);
        $this->assertTrue(can_view(5, "public"));
        $this->assertFalse(can_view(5, "members"));
        $this->assertFalse(can_view(5, "followers"));
        $this->assertFalse(can_view(5, "private"));
    }

    public function testMemberSeesMembersButNotPrivate(): void
    {
        $_SESSION['user_id'] = 2;
        $this->assertTrue(can_view(5, "members"));
        $this->assertFalse(can_view(5, "private"));
    }

    public function testOwnerSeesEverything(): void
    {
        $_SESSION['user_id'] = 5;
        foreach (array("public", "members", "followers", "private") as $value) {
            $this->assertTrue(can_view(5, $value));
        }
    }

    public function testGuestWhereOnlyMatchesPublic(): void
    {
        unset($_SESSION['user_id']);
        $sql = visibility_where("outfits", "user");
        $this->assertStringContainsString("outfits.`deleted_at` IS NULL", $sql);
        $this->assertStringContainsString("IN ('public')", $sql);
        $this->assertStringNotContainsString("follows", $sql);
    }

    public function testMemberWhereIncludesOwnAndFollowed(): void
    {
        $_SESSION['user_id'] = 7;
        $sql = visibility_where("o", "user");
        $this->assertStringContainsString("IN ('public', 'members')", $sql);
        $this->assertStringContainsString("o.`user` = 7", $sql);
        $this->assertStringContainsString("`follower_id` = 7 AND `state` = 'accepted'", $sql);
    }
}
