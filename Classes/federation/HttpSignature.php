<?php

/**
 * HTTP Signatures (draft-cavage-http-signatures-12, rsa-sha256), which Mastodon and most of the
 * Fediverse use to authenticate server-to-server requests.
 */
class HttpSignature
{
    //how old a signed request may be, and how far in the future (clocks drift)
    const MAX_AGE = 43200;
    const MAX_FUTURE = 3600;

    /**
     * Headers to send with a signed request
     *
     * @param string $method GET or POST
     * @param string $url
     * @param string|null $body
     * @param string $key_id e.g. https://host/user/name#main-key
     * @param string $private_pem
     * @param int|null $time for tests
     * @return array name => value, including Signature
     */
    public static function sign($method, $url, $body, $key_id, $private_pem, $time = null): array
    {
        $parts = parse_url($url);
        $target = strtolower($method) . " " . ($parts['path'] ?? "/") . (isset($parts['query']) ? "?" . $parts['query'] : "");
        $headers = array(
            "Host" => $parts['host'] . (isset($parts['port']) ? ":" . $parts['port'] : ""),
            "Date" => gmdate("D, d M Y H:i:s", $time ?? time()) . " GMT"
        );
        $signed = array("(request-target)" => $target, "host" => $headers['Host'], "date" => $headers['Date']);
        if ($body !== null) {
            $headers['Digest'] = "SHA-256=" . base64_encode(hash("sha256", $body, true));
            $headers['Content-Type'] = "application/activity+json";
            $signed['digest'] = $headers['Digest'];
            $signed['content-type'] = $headers['Content-Type'];
        }
        $string = self::signing_string($signed);
        if (!openssl_sign($string, $signature, $private_pem, OPENSSL_ALGO_SHA256)) {
            throw new Exception("Couldn't sign the request");
        }
        $headers['Signature'] = 'keyId="' . $key_id . '",algorithm="rsa-sha256",headers="' . implode(" ", array_keys($signed)) . '",signature="' . base64_encode($signature) . '"';
        return $headers;
    }

    /**
     * @param array $signed header name (lower case) => value, in order
     * @return string
     */
    public static function signing_string($signed): string
    {
        $lines = array();
        foreach ($signed as $name => $value) {
            $lines[] = $name . ": " . $value;
        }
        return implode("\n", $lines);
    }

    /**
     * @param string $header the Signature header
     * @return array keyId, algorithm, headers (array), signature (binary)
     * @throws Exception
     */
    public static function parse($header): array
    {
        preg_match_all('/(\w+)="([^"]*)"/', (string)$header, $matches, PREG_SET_ORDER);
        $params = array();
        foreach ($matches as $match) {
            $params[$match[1]] = $match[2];
        }
        if (empty($params['keyId']) || empty($params['signature'])) {
            throw new Exception("Signature header incomplete");
        }
        return array(
            "keyId" => $params['keyId'],
            "algorithm" => strtolower($params['algorithm'] ?? "rsa-sha256"),
            "headers" => explode(" ", strtolower($params['headers'] ?? "date")),
            "signature" => base64_decode($params['signature'], true)
        );
    }

    /**
     * Checks an incoming request's signature, digest and date. Doesn't decide who the signer is
     * allowed to be; the caller compares the key's owner with the activity's actor.
     *
     * @param string $method
     * @param string $target path and query, as requested
     * @param array $headers lower-case name => value
     * @param string $body
     * @param callable $public_key function ($key_id, $refresh) returning the PEM for a keyId
     * @param int|null $now for tests
     * @return string the keyId that signed it
     * @throws Exception when it doesn't verify
     */
    public static function verify($method, $target, $headers, $body, $public_key, $now = null): string
    {
        $signature = self::parse($headers['signature'] ?? "");
        if (!in_array($signature['algorithm'], array("rsa-sha256", "hs2019"), true)) {
            throw new Exception("Unsupported signature algorithm");
        }
        $required = strtoupper($method) == "POST" ? array("(request-target)", "host", "date", "digest") : array("(request-target)", "host", "date");
        foreach ($required as $name) {
            if (!in_array($name, $signature['headers'], true)) {
                throw new Exception("Signature doesn't cover " . $name);
            }
        }

        $date = strtotime($headers['date'] ?? "");
        $now = $now ?? time();
        if ($date === false || $date < $now - self::MAX_AGE || $date > $now + self::MAX_FUTURE) {
            throw new Exception("Signed date out of range");
        }
        if (strtoupper($method) == "POST") {
            $digest = $headers['digest'] ?? "";
            $expected = "SHA-256=" . base64_encode(hash("sha256", $body, true));
            $found = false;
            foreach (explode(",", $digest) as $part) {
                if (hash_equals($expected, trim($part))) {
                    $found = true;
                }
            }
            if (!$found) {
                throw new Exception("Digest doesn't match the body");
            }
        }

        $signed = array();
        foreach ($signature['headers'] as $name) {
            if ($name == "(request-target)") {
                $signed[$name] = strtolower($method) . " " . $target;
            } elseif (array_key_exists($name, $headers)) {
                $signed[$name] = $headers[$name];
            } else {
                throw new Exception("Signed header " . $name . " missing");
            }
        }
        $string = self::signing_string($signed);

        foreach (array(false, true) as $refresh) {
            $pem = $public_key($signature['keyId'], $refresh);
            if ($pem && openssl_verify($string, $signature['signature'], $pem, OPENSSL_ALGO_SHA256) === 1) {
                return $signature['keyId'];
            }
        }
        throw new Exception("Signature doesn't verify");
    }
}
