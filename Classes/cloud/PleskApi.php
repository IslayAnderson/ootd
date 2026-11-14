<?php

/**
 * Plesk's XML API (https://<server>:8443/enterprise/control/agent.php), used by cloud blogs to make
 * a customer, a subscription (webspace) and a database for each new blog. It authenticates with a
 * secret key belonging to the reseller the blogs are created under (PLESK_API_KEY), so OOTD can
 * only touch that reseller's customers, never the server's own sites.
 */
class PleskApi
{
    /**
     * @param string $operator e.g. customer, webspace, database
     * @param string $operation e.g. add, del, set, add-db
     * @param string $inner XML inside the operation
     * @return SimpleXMLElement the <result>
     * @throws Exception when Plesk refuses it (the message is Plesk's errtext)
     */
    public static function call($operator, $operation, $inner): SimpleXMLElement
    {
        $body = '<?xml version="1.0" encoding="UTF-8"?><packet><' . $operator . '><' . $operation . '>' . $inner
            . '</' . $operation . '></' . $operator . '></packet>';
        $curl = curl_init((string)($_ENV['PLESK_API_URL'] ?? ""));
        curl_setopt_array($curl, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => array("Content-Type: text/xml", "HTTP_PRETTY_PRINT: TRUE", "KEY: " . ($_ENV['PLESK_API_KEY'] ?? "")),
            //Plesk's panel on this same server often has a certificate for another name
            CURLOPT_SSL_VERIFYPEER => (string)($_ENV['PLESK_VERIFY_TLS'] ?? "1") !== "0",
            CURLOPT_SSL_VERIFYHOST => (string)($_ENV['PLESK_VERIFY_TLS'] ?? "1") !== "0" ? 2 : 0
        ));
        $response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($response === false || $status != 200) {
            throw new Exception("Plesk didn't answer (" . ($error ?: "HTTP " . $status) . ")");
        }
        $xml = @simplexml_load_string((string)$response);
        if ($xml === false) {
            throw new Exception("Plesk sent something that isn't XML");
        }
        if (isset($xml->system)) {
            throw new Exception("Plesk: " . trim((string)$xml->system->errtext));
        }
        $result = $xml->{$operator}->{$operation}->result ?? null;
        if (!$result) {
            throw new Exception("Plesk sent no result");
        }
        if ((string)$result->status !== "ok") {
            throw new Exception("Plesk: " . trim((string)$result->errtext) . " (" . (string)$result->errcode . ")");
        }
        return $result;
    }

    public static function x($value): string
    {
        return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, "UTF-8");
    }

    /**
     * @return int customer id
     */
    public static function add_customer($name, $login, $password, $email): int
    {
        $result = self::call("customer", "add", "<gen_info><pname>" . self::x($name) . "</pname><login>" . self::x($login) . "</login>"
            . "<passwd>" . self::x($password) . "</passwd><email>" . self::x($email) . "</email></gen_info>");
        return (int)$result->id;
    }

    /**
     * @return int webspace (subscription) id
     */
    public static function add_webspace($domain, $customer_id, $ip, $plan, $ftp_login, $ftp_password): int
    {
        $result = self::call("webspace", "add", "<gen_setup><name>" . self::x($domain) . "</name><owner-id>" . (int)$customer_id . "</owner-id>"
            . "<htype>vrt_hst</htype><ip_address>" . self::x($ip) . "</ip_address><status>0</status></gen_setup>"
            . "<hosting><vrt_hst><property><name>ftp_login</name><value>" . self::x($ftp_login) . "</value></property>"
            . "<property><name>ftp_password</name><value>" . self::x($ftp_password) . "</value></property>"
            . "<ip_address>" . self::x($ip) . "</ip_address></vrt_hst></hosting>"
            . "<plan-name>" . self::x($plan) . "</plan-name>");
        return (int)$result->id;
    }

    /**
     * @return int database id
     */
    public static function add_database($webspace_id, $name): int
    {
        $result = self::call("database", "add-db", "<webspace-id>" . (int)$webspace_id . "</webspace-id><name>" . self::x($name) . "</name><type>mysql</type>");
        return (int)$result->id;
    }

    public static function add_database_user($db_id, $login, $password): void
    {
        self::call("database", "add-db-user", "<db-id>" . (int)$db_id . "</db-id><login>" . self::x($login) . "</login><password>" . self::x($password) . "</password>");
    }

    /**
     * @param int $webspace_id
     * @param bool $active false suspends it (the site stops answering)
     */
    public static function set_webspace_active($webspace_id, $active): void
    {
        self::call("webspace", "set", "<filter><id>" . (int)$webspace_id . "</id></filter><values><gen_setup><status>" . ($active ? 0 : 16) . "</status></gen_setup></values>");
    }

    public static function delete_webspace($webspace_id): void
    {
        self::call("webspace", "del", "<filter><id>" . (int)$webspace_id . "</id></filter>");
    }

    public static function delete_customer($customer_id): void
    {
        self::call("customer", "del", "<filter><id>" . (int)$customer_id . "</id></filter>");
    }
}
