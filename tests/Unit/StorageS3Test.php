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
}
