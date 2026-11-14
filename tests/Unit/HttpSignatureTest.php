<?php
use PHPUnit\Framework\TestCase;

class HttpSignatureTest extends TestCase
{
    private static $private;
    private static $public;

    public static function setUpBeforeClass(): void
    {
        $key = openssl_pkey_new(array("private_key_bits" => 2048, "private_key_type" => OPENSSL_KEYTYPE_RSA));
        openssl_pkey_export($key, self::$private);
        self::$public = openssl_pkey_get_details($key)['key'];
    }

    private function headers($signed): array
    {
        $headers = array();
        foreach ($signed as $name => $value) {
            $headers[strtolower($name)] = $value;
        }
        return $headers;
    }

    private function verify($method, $target, $headers, $body, $now = null): string
    {
        return HttpSignature::verify($method, $target, $headers, $body, function () {
            return self::$public;
        }, $now);
    }

    public function testSignedPostVerifies(): void
    {
        $body = '{"type":"Follow"}';
        $signed = HttpSignature::sign("POST", "https://example.test/user/mia/inbox", $body, "https://me.test/user/a#main-key", self::$private);
        $this->assertSame("https://me.test/user/a#main-key", $this->verify("POST", "/user/mia/inbox", $this->headers($signed), $body));
    }

    public function testSignatureCoversTheExpectedHeaders(): void
    {
        $signed = HttpSignature::sign("POST", "https://example.test/inbox", "{}", "k", self::$private);
        $parsed = HttpSignature::parse($signed['Signature']);
        $this->assertSame(array("(request-target)", "host", "date", "digest", "content-type"), $parsed['headers']);
        $this->assertSame("SHA-256=" . base64_encode(hash("sha256", "{}", true)), $signed['Digest']);
    }

    public function testChangedBodyFails(): void
    {
        $signed = HttpSignature::sign("POST", "https://example.test/inbox", '{"a":1}', "k", self::$private);
        $this->expectExceptionMessage("Digest");
        $this->verify("POST", "/inbox", $this->headers($signed), '{"a":2}');
    }

    public function testDifferentPathFails(): void
    {
        $signed = HttpSignature::sign("POST", "https://example.test/user/a/inbox", "{}", "k", self::$private);
        $this->expectExceptionMessage("doesn't verify");
        $this->verify("POST", "/user/b/inbox", $this->headers($signed), "{}");
    }

    public function testOldDateFails(): void
    {
        $signed = HttpSignature::sign("POST", "https://example.test/inbox", "{}", "k", self::$private, time() - 86400);
        $this->expectExceptionMessage("date");
        $this->verify("POST", "/inbox", $this->headers($signed), "{}");
    }

    public function testPostMustSignDigest(): void
    {
        $signed = HttpSignature::sign("GET", "https://example.test/inbox", null, "k", self::$private);
        $this->expectExceptionMessage("digest");
        $this->verify("POST", "/inbox", $this->headers($signed) + array("digest" => "SHA-256=" . base64_encode(hash("sha256", "", true))), "");
    }

    public function testSignedGetVerifies(): void
    {
        $signed = HttpSignature::sign("GET", "https://example.test/user/a?x=1", null, "k", self::$private);
        $this->assertSame("k", $this->verify("GET", "/user/a?x=1", $this->headers($signed), ""));
    }
}
