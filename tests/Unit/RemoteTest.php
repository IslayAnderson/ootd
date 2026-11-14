<?php

use PHPUnit\Framework\TestCase;

class RemoteTest extends TestCase
{
    public function testParsesHandles(): void
    {
        $this->assertSame(array("alice", "mastodon.social"), RemoteActor::parse_handle("@alice@Mastodon.Social"));
        $this->assertSame(array("bob_1", "example.com:8443"), RemoteActor::parse_handle("bob_1@example.com:8443"));
        $this->assertNull(RemoteActor::parse_handle("alice"));
        $this->assertNull(RemoteActor::parse_handle("@alice@exa mple.com"));
        $this->assertNull(RemoteActor::parse_handle("@alice@host/../x"));
        $this->assertNull(RemoteActor::parse_handle("https://mastodon.social/@alice"));
    }

    private function actor(): object
    {
        return (object)array("ap_id" => "https://m.example/users/a", "followers" => "https://m.example/users/a/followers");
    }

    public function testPublicAudience(): void
    {
        $this->assertSame("public", RemotePost::audience($this->actor(), array("to" => array(Federation::PUBLIC_AUDIENCE))));
        //unlisted (public in cc) is still public
        $this->assertSame("public", RemotePost::audience($this->actor(), array("to" => "https://m.example/users/a/followers", "cc" => "as:Public")));
    }

    public function testFollowersAudience(): void
    {
        $this->assertSame("followers", RemotePost::audience($this->actor(), array("to" => array("https://m.example/users/a/followers"))));
    }

    public function testDirectMessageHasNoAudience(): void
    {
        $this->assertNull(RemotePost::audience($this->actor(), array("to" => array("https://ootd.example/user/b"), "cc" => array())));
        $this->assertNull(RemotePost::audience($this->actor(), array()));
    }
}
