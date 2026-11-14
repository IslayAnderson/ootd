<?php

/**
 * The A record that points a cloud blog's address at this server.
 *
 * - Cloudflare (CLOUDFLARE_API_TOKEN and CLOUDFLARE_ZONE_ID set): a proxied record in that zone, so
 *   Cloudflare's certificate gives the blog https at once.
 * - Otherwise Plesk: CLOUD_DOMAIN is a subscription of the same reseller and Plesk is its name
 *   server; the record goes in that subscription's zone, and the blog's own certificate comes from
 *   Let's Encrypt (SSL It! "Keep websites secured").
 *
 * Record ids are stored as "plesk:<id>" for Plesk, plain for Cloudflare (0.14.0 stored those).
 */
class CloudDns
{
    public static function cloudflare(): bool
    {
        return trim((string)($_ENV['CLOUDFLARE_API_TOKEN'] ?? "")) !== "" && trim((string)($_ENV['CLOUDFLARE_ZONE_ID'] ?? "")) !== "";
    }

    /**
     * @param string $domain the blog's full address
     * @param string $ip
     * @return string record id, existing or new
     * @throws Exception
     */
    public static function point($domain, $ip): string
    {
        if (self::cloudflare()) {
            return CloudflareDns::find_a($domain) ?? CloudflareDns::add_a($domain, $ip);
        }
        $zone = CloudBlog::domain();
        $site = PleskApi::webspace_id($zone);
        foreach (PleskApi::dns_records($site) as $record) {
            if ($record['type'] == "A" && $record['host'] == strtolower($domain)) {
                return "plesk:" . $record['id'];
            }
        }
        $host = substr($domain, 0, -strlen("." . $zone));
        return "plesk:" . PleskApi::add_dns_a($site, $host, $ip);
    }

    public static function remove($record_id): void
    {
        if (strpos($record_id, "plesk:") === 0) {
            PleskApi::delete_dns((int)substr($record_id, 6));
        } else {
            CloudflareDns::delete($record_id);
        }
    }
}
