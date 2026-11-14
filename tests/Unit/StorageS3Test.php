<?php
use PHPUnit\Framework\TestCase;

class StorageS3Test extends TestCase
{
    /**
     * AWS's published Signature V4 example for S3 (GET Object with a Range header)
     * https://docs.aws.amazon.com/AmazonS3/latest/API/sig-v4-header-based-auth.html
     */
    public function testMatchesAwsExample(): void
    {
        $s3 = new StorageS3(array(
            "S3_ENDPOINT" => "https://s3.amazonaws.com",
            "S3_REGION" => "us-east-1",
            "S3_BUCKET" => "examplebucket",
            "S3_KEY" => "AKIAIOSFODNN7EXAMPLE",
            "S3_SECRET" => "wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY"
        ));
        $empty = "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855";
        $header = $s3->authorization("GET", "/test.txt", array(
            "host" => "examplebucket.s3.amazonaws.com",
            "range" => "bytes=0-9",
            "x-amz-content-sha256" => $empty,
            "x-amz-date" => "20130524T000000Z"
        ), $empty);
        $this->assertSame("AWS4-HMAC-SHA256 Credential=AKIAIOSFODNN7EXAMPLE/20130524/us-east-1/s3/aws4_request,"
            . "SignedHeaders=host;range;x-amz-content-sha256;x-amz-date,"
            . "Signature=f0e8bdb87c964420e857bd35b5d6ed310bd44f0170aba48dd91039c6036bdb41", $header);
    }

    public function testR2DefaultsToAutoRegionAndPathStyle(): void
    {
        $s3 = new StorageS3(array(
            "S3_ENDPOINT" => "https://0123abcd.r2.cloudflarestorage.com",
            "S3_BUCKET" => "ootd-media"
        ));
        $this->assertSame(array(
            "region" => "auto",
            "path_style" => true,
            "url" => "0123abcd.r2.cloudflarestorage.com/ootd-media/media/a%20b.jpg"
        ), $s3->describe("media/a b.jpg"));
    }

    public function testExplicitSettingsWinOverR2Defaults(): void
    {
        $s3 = new StorageS3(array(
            "S3_ENDPOINT" => "https://0123abcd.eu.r2.cloudflarestorage.com",
            "S3_REGION" => "us-east-1",
            "S3_PATH_STYLE" => "0",
            "S3_BUCKET" => "ootd-media"
        ));
        $this->assertSame("us-east-1", $s3->describe()["region"]);
        $this->assertFalse($s3->describe()["path_style"]);
    }

    public function testOtherEndpointsKeepAwsDefaults(): void
    {
        $s3 = new StorageS3(array("S3_ENDPOINT" => "https://s3.eu-west-2.amazonaws.com", "S3_BUCKET" => "b"));
        $this->assertSame(array("region" => "us-east-1", "path_style" => false, "url" => "b.s3.eu-west-2.amazonaws.com/check"), $s3->describe());
        $this->assertFalse(StorageS3::is_r2("https://evil.com/.r2.cloudflarestorage.com"));
    }
}
