<?php

/**
 * Outgoing HTTP for federation. Every request is to an address someone else chose, so:
 * https only, the host must resolve to public IP addresses (no loopback, private, link-local or
 * reserved ranges) and the connection is pinned to the address that was checked (no DNS
 * rebinding), redirects are followed by hand and re-checked (3 at most), responses are capped at
 * 1 MB (more for photos) and requests time out.
 *
 * For local testing only, AP_ALLOW_HTTP=1 allows http:// and AP_ALLOW_PRIVATE=1 allows private
 * addresses. Never set them on a public server.
 */
class SafeHttp
{
    const MAX_BYTES = 1048576;
    /** photos from other servers */
    const MAX_MEDIA_BYTES = 16777216;
    const MAX_REDIRECTS = 3;
    const TIMEOUT = 10;

    /**
     * @param string $url
     * @param array $headers name => value
     * @param int $max_bytes
     * @return array [status, body, headers (lower-case name => value)]
     * @throws Exception when the address isn't allowed or the request fails
     */
    public static function get($url, $headers = array(), $max_bytes = self::MAX_BYTES): array
    {
        return self::request("GET", $url, $headers, null, self::MAX_REDIRECTS, $max_bytes);
    }

    /**
     * @param string $url
     * @param array $headers
     * @param string $body
     * @return array [status, body, headers]
     * @throws Exception
     */
    public static function post($url, $headers, $body): array
    {
        //a POST is never redirected, the signature covers the original target
        return self::request("POST", $url, $headers, $body, 0);
    }

    /**
     * @param string $url
     * @return array [host, port, ip] after checking the address
     * @throws Exception
     */
    public static function check($url): array
    {
        $parts = parse_url((string)$url);
        $scheme = strtolower($parts['scheme'] ?? "");
        if ($scheme != "https" && !($scheme == "http" && !empty($_ENV['AP_ALLOW_HTTP']))) {
            throw new Exception("Only https addresses can be fetched");
        }
        $host = strtolower($parts['host'] ?? "");
        if ($host === "" || isset($parts['user']) || isset($parts['pass'])) {
            throw new Exception("Bad address");
        }
        $port = (int)($parts['port'] ?? ($scheme == "https" ? 443 : 80));

        $host_ip = trim($host, "[]");
        if (filter_var($host_ip, FILTER_VALIDATE_IP)) {
            $ips = array($host_ip);
        } else {
            $ips = array();
            foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: array() as $record) {
                $ips[] = $record['ip'] ?? $record['ipv6'] ?? null;
            }
            $ips = array_values(array_filter($ips));
            if (count($ips) == 0) {
                $ips = gethostbynamel($host) ?: array();
            }
        }
        if (count($ips) == 0) {
            throw new Exception("Host not found");
        }
        foreach ($ips as $ip) {
            if (!self::public_ip($ip) && empty($_ENV['AP_ALLOW_PRIVATE'])) {
                throw new Exception("Address not allowed");
            }
        }
        return array($host, $port, $ips[0]);
    }

    /**
     * @param string $ip
     * @return bool is it a public internet address
     */
    public static function public_ip($ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        //ranges PHP's filter doesn't cover: carrier-grade NAT, IPv4-mapped IPv6, unique local and NAT64
        foreach (array("100.64.0.0/10", "192.0.0.0/24", "198.18.0.0/15", "::ffff:0:0/96", "fc00::/7", "fe80::/10", "64:ff9b::/96") as $range) {
            if (self::in_range($ip, $range)) {
                return false;
            }
        }
        return true;
    }

    private static function in_range($ip, $range): bool
    {
        list($subnet, $bits) = explode("/", $range);
        $ip_bin = @inet_pton($ip);
        $subnet_bin = @inet_pton($subnet);
        if ($ip_bin === false || $subnet_bin === false || strlen($ip_bin) != strlen($subnet_bin)) {
            return false;
        }
        $bytes = intdiv((int)$bits, 8);
        $rest = (int)$bits % 8;
        if (substr($ip_bin, 0, $bytes) !== substr($subnet_bin, 0, $bytes)) {
            return false;
        }
        if ($rest == 0) {
            return true;
        }
        $mask = (0xff << (8 - $rest)) & 0xff;
        return (ord($ip_bin[$bytes]) & $mask) === (ord($subnet_bin[$bytes]) & $mask);
    }

    private static function request($method, $url, $headers, $body, $redirects = self::MAX_REDIRECTS, $max_bytes = self::MAX_BYTES): array
    {
        list($host, $port, $ip) = self::check($url);

        $header_lines = array("User-Agent: OOTD/" . OOTD_VERSION . " (+" . app_url() . ")");
        foreach ($headers as $name => $value) {
            $header_lines[] = $name . ": " . $value;
        }
        $received = "";
        $response_headers = array();
        $curl = curl_init($url);
        curl_setopt_array($curl, array(
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $header_lines,
            CURLOPT_RESOLVE => array($host . ":" . $port . ":" . (strpos($ip, ":") !== false ? "[" . $ip . "]" : $ip)),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_HEADERFUNCTION => function ($curl, $line) use (&$response_headers) {
                $parts = explode(":", $line, 2);
                if (count($parts) == 2) {
                    $response_headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => function ($curl, $chunk) use (&$received, $max_bytes) {
                $received .= $chunk;
                //returning less than the chunk's length aborts the transfer
                return strlen($received) > $max_bytes ? 0 : strlen($chunk);
            }
        ));
        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }
        $ok = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if (strlen($received) > $max_bytes) {
            throw new Exception("Response too large");
        }
        if ($ok === false) {
            throw new Exception("Request failed: " . $error);
        }
        if (in_array($status, array(301, 302, 303, 307, 308), true) && !empty($response_headers['location'])) {
            if ($redirects <= 0) {
                throw new Exception("Too many redirects");
            }
            $next = self::resolve_url($url, $response_headers['location']);
            return self::request($method, $next, $headers, $body, $redirects - 1, $max_bytes);
        }
        return array($status, $received, $response_headers);
    }

    private static function resolve_url($base, $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $parts = parse_url($base);
        $origin = $parts['scheme'] . "://" . $parts['host'] . (isset($parts['port']) ? ":" . $parts['port'] : "");
        return $origin . "/" . ltrim($location, "/");
    }
}
