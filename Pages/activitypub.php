<?php
/**
 * ActivityPub, WebFinger and NodeInfo. index.php sends these here before init.php, so federation
 * traffic never starts a session or compiles CSS. See Classes/federation.
 */
error_reporting(E_ALL & ~E_WARNING & ~E_DEPRECATED);
require $_SERVER['DOCUMENT_ROOT'] . "/Functions/functions.php";
require $_SERVER['DOCUMENT_ROOT'] . "/Classes/classes.php";
require $_SERVER['DOCUMENT_ROOT'] . "/vendor/autoload.php";
Dotenv\Dotenv::createMutable($_SERVER['DOCUMENT_ROOT'])->load();
error_reporting(E_ALL);
ini_set('display_errors', 'off');

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? "GET";

function ap_respond($data, $status = 200, $type = "application/activity+json")
{
    http_response_code($status);
    header("Content-Type: " . $type . "; charset=utf-8");
    header("Vary: Accept");
    header("Access-Control-Allow-Origin: *");
    header("Cache-Control: " . ($status == 200 ? "public, max-age=180" : "no-store"));
    if ($data !== null) {
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    exit();
}

function ap_not_found()
{
    ap_respond(array("error" => "Not found"), 404, "application/json");
}

function ap_request_headers(): array
{
    $headers = array();
    foreach ($_SERVER as $key => $value) {
        if (strpos($key, "HTTP_") === 0) {
            $headers[strtolower(str_replace("_", "-", substr($key, 5)))] = $value;
        }
    }
    if (isset($_SERVER['CONTENT_TYPE'])) {
        $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
    }
    return $headers;
}

function ap_inbox()
{
    global $method;
    if ($method != "POST") {
        ap_respond(array("error" => "POST activities here"), 405, "application/json");
    }
    $body = file_get_contents("php://input", false, null, 0, 1048577);
    if (strlen($body) > 1048576) {
        ap_respond(array("error" => "Too large"), 413, "application/json");
    }
    $status = Inbox::receive($_SERVER['REQUEST_URI'], ap_request_headers(), $body);
    ap_respond(null, $status, "application/json");
}

try {
    if (!Federation::enabled()) {
        ap_not_found();
    }

    // WebFinger: acct:name@host -> actor
    if ($path == "/.well-known/webfinger") {
        $resource = (string)($_GET['resource'] ?? "");
        $username = null;
        if (preg_match('/^acct:([^@]+)@(.+)$/i', $resource, $m) && Federation::is_own_domain($m[2])) {
            $username = $m[1];
        } elseif (strpos($resource, absolute_url("/user/")) === 0) {
            $username = rawurldecode(substr($resource, strlen(absolute_url("/user/"))));
        }
        $user = $username !== null ? Federation::local_user($username) : null;
        if (!$user) {
            ap_not_found();
        }
        $actor = Federation::actor_url($user->username);
        ap_respond(array(
            "subject" => "acct:" . $user->username . "@" . Federation::domain(),
            "aliases" => array($actor),
            "links" => array(
                array("rel" => "self", "type" => "application/activity+json", "href" => $actor),
                array("rel" => "http://webfinger.net/rel/profile-page", "type" => "text/html", "href" => $actor)
            )
        ), 200, "application/jrd+json");
    }

    if ($path == "/.well-known/host-meta") {
        http_response_code(200);
        header("Content-Type: application/xrd+xml; charset=utf-8");
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<XRD xmlns="http://docs.oasis-open.org/ns/xri/xrd-1.0"><Link rel="lrdd" template="'
            . escape(absolute_url("/.well-known/webfinger")) . '?resource={uri}"/></XRD>';
        exit();
    }

    if ($path == "/.well-known/nodeinfo") {
        ap_respond(array("links" => array(array(
            "rel" => "http://nodeinfo.diaspora.software/ns/schema/2.1",
            "href" => absolute_url("/nodeinfo/2.1")
        ))), 200, "application/json");
    }

    if ($path == "/nodeinfo/2.1") {
        $db = new Mysql();
        $counts = $db->Fetch("SELECT
            (SELECT COUNT(*) FROM `users`) AS users,
            (SELECT COUNT(DISTINCT `user`) FROM `outfits` WHERE `timestamp` > DATE_SUB(NOW(), INTERVAL 30 DAY)) AS month,
            (SELECT COUNT(DISTINCT `user`) FROM `outfits` WHERE `timestamp` > DATE_SUB(NOW(), INTERVAL 180 DAY)) AS halfyear,
            (SELECT COUNT(*) FROM `outfits` WHERE `deleted_at` IS NULL AND `visibility` = 'public') AS posts", null);
        ap_respond(array(
            "version" => "2.1",
            "software" => array("name" => "ootd", "version" => OOTD_VERSION),
            "protocols" => array("activitypub"),
            "services" => array("inbound" => array(), "outbound" => array()),
            "openRegistrations" => registration_mode() == "open",
            "usage" => array(
                "users" => array("total" => (int)$counts[0]->users, "activeMonth" => (int)$counts[0]->month, "activeHalfyear" => (int)$counts[0]->halfyear),
                "localPosts" => (int)$counts[0]->posts
            ),
            "metadata" => array("nodeName" => instance_name(), "nodeDescription" => (string)setting_get("instance_description", ""))
        ), 200, 'application/json; profile="http://nodeinfo.diaspora.software/ns/schema/2.1#"');
    }

    // the instance actor
    if ($path == "/actor") {
        ap_respond(Federation::instance_actor());
    }
    if ($path == "/actor/inbox" || $path == "/inbox") {
        ap_inbox();
    }
    if ($path == "/actor/outbox") {
        ap_respond(array("@context" => "https://www.w3.org/ns/activitystreams", "id" => absolute_url("/actor/outbox"), "type" => "OrderedCollection", "totalItems" => 0, "orderedItems" => array()));
    }

    //OOTD's vocabulary (garments), a JSON-LD context
    if ($path == "/ns") {
        ap_respond(Federation::vocabulary(), 200, "application/ld+json");
    }

    // /garment/<id>, public garments only
    if (preg_match('#^/garment/([^/]+)$#', $path, $m)) {
        $garment = Federation::garment(rawurldecode($m[1]));
        if (!$garment) {
            ap_not_found();
        }
        ap_respond($garment);
    }

    // /comment/<id>, comments on public posts only
    if (preg_match('#^/comment/([0-9a-f]+)$#', $path, $m)) {
        $comment = Comment::get($m[1]);
        $note = $comment ? Comment::note($comment) : null;
        if (!$note || !in_array(Federation::PUBLIC_AUDIENCE, $note['to'], true)) {
            ap_not_found();
        }
        ap_respond(array_merge(array("@context" => Federation::context()), $note));
    }

    // /outfit/<id>
    if (preg_match('#^/outfit/([^/]+)$#', $path, $m)) {
        $note = Federation::outfit_note(rawurldecode($m[1]));
        //followers-only posts are delivered to followers' servers, not served to anyone who asks
        if (!$note || !in_array(Federation::PUBLIC_AUDIENCE, $note['to'], true)) {
            ap_not_found();
        }
        ap_respond(array_merge(array("@context" => Federation::context()), $note));
    }

    // /user/<name>[/blog/<id>|/inbox|/outbox|/followers|/following|/wardrobe]
    if (preg_match('#^/user/([^/]+)(?:/(inbox|outbox|followers|following|wardrobe|blog/([^/]+)))?$#', $path, $m)) {
        $user = Federation::local_user(rawurldecode($m[1]));
        if (!$user) {
            if (empty($m[2]) && Federation::deleted_user(rawurldecode($m[1]))) {
                //a deleted account: other servers drop it on 410
                ap_respond(array("@context" => "https://www.w3.org/ns/activitystreams", "id" => Federation::actor_url(rawurldecode($m[1])),
                    "type" => "Tombstone"), 410);
            }
            ap_not_found();
        }
        $actor = Federation::actor_url($user->username);
        $part = $m[2] ?? "";

        if ($part === "") {
            ap_respond(Federation::person($user));
        }
        if ($part == "inbox") {
            ap_inbox();
        }
        if ($part == "wardrobe") {
            ap_respond(Federation::wardrobe($user));
        }
        if (strpos($part, "blog/") === 0) {
            $article = Federation::blog_article(rawurldecode($m[3]));
            if (!$article || $article['attributedTo'] !== $actor || !in_array(Federation::PUBLIC_AUDIENCE, $article['to'], true)) {
                ap_not_found();
            }
            ap_respond(array_merge(array("@context" => Federation::context()), $article));
        }
        if ($part == "followers" || $part == "following") {
            //counts only: who follows whom stays private (D18)
            list($followers, $following) = Follow::counts($user->user_id);
            ap_respond(array(
                "@context" => "https://www.w3.org/ns/activitystreams",
                "id" => $actor . "/" . $part,
                "type" => "OrderedCollection",
                "totalItems" => $part == "followers" ? $followers : $following
            ));
        }
        if ($part == "outbox") {
            $db = new Mysql();
            $total = $db->Fetch("SELECT
                (SELECT COUNT(*) FROM `outfits` WHERE `user` = :a AND `deleted_at` IS NULL AND `visibility` = 'public') +
                (SELECT COUNT(*) FROM `blogs` WHERE `user` = :b AND `deleted_at` IS NULL AND `visibility` = 'public') AS n",
                array(":a" => $user->user_id, ":b" => $user->user_id));
            $outbox = $actor . "/outbox";
            if (!isset($_GET['page'])) {
                ap_respond(array(
                    "@context" => "https://www.w3.org/ns/activitystreams",
                    "id" => $outbox,
                    "type" => "OrderedCollection",
                    "totalItems" => (int)$total[0]->n,
                    "first" => $outbox . "?page=1"
                ));
            }
            $page = max(1, (int)$_GET['page']);
            $rows = $db->Fetch("SELECT 'outfit' AS kind, `outfit_id` AS id, `timestamp` FROM `outfits` WHERE `user` = :a AND `deleted_at` IS NULL AND `visibility` = 'public'
                                UNION ALL
                                SELECT 'blog' AS kind, `blog_id` AS id, `timestamp` FROM `blogs` WHERE `user` = :b AND `deleted_at` IS NULL AND `visibility` = 'public'
                                ORDER BY `timestamp` DESC LIMIT " . Federation::PAGE_SIZE . " OFFSET " . (($page - 1) * Federation::PAGE_SIZE),
                array(":a" => $user->user_id, ":b" => $user->user_id));
            $items = array();
            foreach ($rows[0] != '00000' ? $rows : array() as $row) {
                $object = $row->kind == "blog" ? Federation::blog_article($row->id) : Federation::outfit_note($row->id);
                if ($object) {
                    $activity = Federation::wrap("Create", $object);
                    unset($activity['@context']);
                    $items[] = $activity;
                }
            }
            $result = array(
                "@context" => "https://www.w3.org/ns/activitystreams",
                "id" => $outbox . "?page=" . $page,
                "type" => "OrderedCollectionPage",
                "partOf" => $outbox,
                "orderedItems" => $items
            );
            if ($page * Federation::PAGE_SIZE < (int)$total[0]->n) {
                $result['next'] = $outbox . "?page=" . ($page + 1);
            }
            if ($page > 1) {
                $result['prev'] = $outbox . "?page=" . ($page - 1);
            }
            ap_respond($result);
        }
    }
    ap_not_found();
} catch (Throwable $e) {
    error_log("OOTD activitypub: " . $e->getMessage());
    ap_respond(array("error" => "Server error"), 500, "application/json");
}
