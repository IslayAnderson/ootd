<?php

/**
 * DNS records for cloud blogs in the Cloudflare zone of CLOUD_DOMAIN (CLOUDFLARE_ZONE_ID), through
 * an API token that may only edit that zone's DNS (CLOUDFLARE_API_TOKEN). Records are proxied, so
 * Cloudflare's certificate gives every blog https without one per subscription.
 */
class CloudflareDns
{
    /**
     * @return string record id
     * @throws Exception
     */
    public static function add_a($name, $ip): string
    {
        $result = self::request("POST", "/dns_records", array("type" => "A", "name" => $name, "content" => $ip, "proxied" => true, "ttl" => 1,
            "comment" => "OOTD cloud blog"));
        return (string)($result['id'] ?? "");
    }

    /**
     * @return string|null the id of an A record that already has this name (a retry after a timeout)
     */
    public static function find_a($name): ?string
    {
        $result = self::request("GET", "/dns_records?type=A&name=" . rawurlencode($name), null);
        return isset($result[0]['id']) ? (string)$result[0]['id'] : null;
    }

    public static function delete($record_id): void
    {
        self::request("DELETE", "/dns_records/" . rawurlencode($record_id), null);
    }

    private static function request($method, $path, $body)
    {
        $base = rtrim((string)($_ENV['CLOUDFLARE_API'] ?? "https://api.cloudflare.com/client/v4"), "/");
        $curl = curl_init($base . "/zones/" . rawurlencode((string)($_ENV['CLOUDFLARE_ZONE_ID'] ?? "")) . $path);
        curl_setopt_array($curl, array(
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => array("Authorization: Bearer " . ($_ENV['CLOUDFLARE_API_TOKEN'] ?? ""), "Content-Type: application/json")
        ));
        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);
        $json = json_decode((string)$response, true);
        if (!is_array($json)) {
            throw new Exception("Cloudflare didn't answer (" . ($error ?: "no JSON") . ")");
        }
        if (empty($json['success'])) {
            throw new Exception("Cloudflare: " . ($json['errors'][0]['message'] ?? "refused"));
        }
        return $json['result'] ?? null;
    }
}
