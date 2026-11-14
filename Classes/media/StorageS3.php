<?php

/**
 * Media in an S3-compatible bucket (AWS S3, Cloudflare R2, Backblaze B2, MinIO...), signed with AWS Signature V4.
 * .env: S3_ENDPOINT (e.g. https://s3.eu-west-2.amazonaws.com), S3_REGION, S3_BUCKET, S3_KEY, S3_SECRET,
 * S3_PATH_STYLE=1 for endpoints that want https://endpoint/bucket/key instead of https://bucket.endpoint/key.
 * Cloudflare R2 (https://<account id>.r2.cloudflarestorage.com) defaults to region "auto" and path style.
 * The bucket stays private; files are still served through /media so visibility is checked.
 */
class StorageS3 implements Storage
{
    private $endpoint;
    private $region;
    private $bucket;
    private $key;
    private $secret;
    private $path_style;

    public function __construct($config = null)
    {
        $config = $config ?? $_ENV;
        $this->endpoint = rtrim((string)($config['S3_ENDPOINT'] ?? ""), "/");
        $r2 = self::is_r2($this->endpoint);
        $this->region = (string)(($config['S3_REGION'] ?? "") ?: ($r2 ? "auto" : "us-east-1"));
        $this->bucket = (string)($config['S3_BUCKET'] ?? "");
        $this->key = (string)($config['S3_KEY'] ?? "");
        $this->secret = (string)($config['S3_SECRET'] ?? "");
        $this->path_style = isset($config['S3_PATH_STYLE']) && $config['S3_PATH_STYLE'] !== ""
            ? !empty($config['S3_PATH_STYLE'])
            : $r2;
    }

    /**
     * @param string $endpoint
     * @return bool is it a Cloudflare R2 endpoint
     */
    public static function is_r2($endpoint): bool
    {
        $host = strtolower((string)parse_url((string)$endpoint, PHP_URL_HOST));
        return $host !== "" && str_ends_with($host, ".r2.cloudflarestorage.com");
    }

    /**
     * @return array region, path style, and the address a key would be sent to (for checks and tests)
     */
    public function describe($key = "check"): array
    {
        list($host, $path) = $this->location($key);
        return array("region" => $this->region, "path_style" => $this->path_style, "url" => $host . $path);
    }

    /**
     * @param string $key
     * @return array [host, path]
     */
    private function location($key): array
    {
        $parts = parse_url($this->endpoint);
        $host = $parts['host'] . (isset($parts['port']) ? ":" . $parts['port'] : "");
        $object = "/" . implode("/", array_map("rawurlencode", explode("/", $key)));
        if ($this->path_style) {
            return array($host, "/" . rawurlencode($this->bucket) . $object);
        }
        return array($this->bucket . "." . $host, $object);
    }

    /**
     * AWS Signature V4 Authorization header
     *
     * @param string $method
     * @param string $path url-encoded path
     * @param array $headers lower-case name => value, must include host, x-amz-date and x-amz-content-sha256
     * @param string $payload_hash
     * @return string
     */
    public function authorization($method, $path, $headers, $payload_hash): string
    {
        ksort($headers);
        $canonical_headers = "";
        foreach ($headers as $name => $value) {
            $canonical_headers .= $name . ":" . trim($value) . "\n";
        }
        $signed_headers = implode(";", array_keys($headers));
        $canonical_request = implode("\n", array($method, $path, "", $canonical_headers, $signed_headers, $payload_hash));

        $date_time = $headers['x-amz-date'];
        $date = substr($date_time, 0, 8);
        $scope = $date . "/" . $this->region . "/s3/aws4_request";
        $string_to_sign = implode("\n", array("AWS4-HMAC-SHA256", $date_time, $scope, hash("sha256", $canonical_request)));

        $signing_key = hash_hmac("sha256", "aws4_request",
            hash_hmac("sha256", "s3",
                hash_hmac("sha256", $this->region,
                    hash_hmac("sha256", $date, "AWS4" . $this->secret, true), true), true), true);
        $signature = hash_hmac("sha256", $string_to_sign, $signing_key);

        return "AWS4-HMAC-SHA256 Credential=" . $this->key . "/" . $scope
            . ",SignedHeaders=" . $signed_headers . ",Signature=" . $signature;
    }

    /**
     * @param string $method
     * @param string $key
     * @param string $body
     * @param array $extra extra headers to sign, lower-case names
     * @return array [status, body]
     * @throws Exception
     */
    private function request($method, $key, $body = "", $extra = array()): array
    {
        if ($this->endpoint == "" || $this->bucket == "") {
            throw new Exception('S3 storage is not configured');
        }
        list($host, $path) = $this->location($key);
        $payload_hash = hash("sha256", $body);
        $headers = array_merge(array(
            "host" => $host,
            "x-amz-content-sha256" => $payload_hash,
            "x-amz-date" => gmdate("Ymd\THis\Z")
        ), $extra);
        $headers["authorization"] = $this->authorization($method, $path, $headers, $payload_hash);

        $curl_headers = array();
        foreach ($headers as $name => $value) {
            $curl_headers[] = $name . ": " . $value;
        }
        $scheme = parse_url($this->endpoint, PHP_URL_SCHEME) ?: "https";
        $curl = curl_init($scheme . "://" . $host . $path);
        curl_setopt_array($curl, array(
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $curl_headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ));
        if ($method == "PUT") {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        if ($response === false) {
            throw new Exception('S3 request failed');
        }
        return array($status, $response);
    }

    public function put($key, $contents, $mime): void
    {
        list($status) = $this->request("PUT", $key, $contents, array("content-type" => $mime));
        if ($status < 200 || $status >= 300) {
            throw new Exception('S3 upload failed (' . $status . ')');
        }
    }

    public function get($key): ?string
    {
        try {
            list($status, $body) = $this->request("GET", $key);
        } catch (Exception $e) {
            return null;
        }
        return $status == 200 ? $body : null;
    }

    public function delete($key): void
    {
        try {
            $this->request("DELETE", $key);
        } catch (Exception $e) {
        }
    }
}
