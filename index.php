<?php
ini_set('output_handler', null);

//ActivityPub, WebFinger and NodeInfo skip the session and page entirely: always for the federation-only
//addresses, and for profiles, outfits and blog posts when the client asks for ActivityStreams JSON
$early_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$wants_activity = (bool)preg_match('#application/(activity\+json|ld\+json)#', $_SERVER['HTTP_ACCEPT'] ?? "");
if (preg_match('#^/(\.well-known/(webfinger|host-meta|nodeinfo)|nodeinfo/2\.1|actor(/inbox|/outbox)?|inbox|user/[^/]+/(inbox|outbox|followers|following))$#', $early_path)
    || ($wants_activity && preg_match('#^/(user/[^/]+(/blog/[^/]+)?|outfit/[^/]+)$#', $early_path))) {
    require $_SERVER['DOCUMENT_ROOT'] . '/Pages/activitypub.php';
    exit();
}

require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

ob_start("meta_replace");


//check if init has been run (set it global memory I guess) and run init
//what will be in init?
//the data store and all that jazz :)

$request = explode("?", $_SERVER['REQUEST_URI']);

//images are served bare, without the page around them
if (preg_match('#^/media/[A-Za-z0-9]+/(display|thumb)$#', $request[0])) {
    ob_end_clean();
    require $_SERVER['DOCUMENT_ROOT'] . '/Pages/media.php';
}

//uptime monitors and container health checks; details stay on the command line (bin/ootd health)
if ($request[0] == '/health') {
    ob_end_clean();
    $health = health_check();
    http_response_code($health['ok'] ? 200 : 503);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    $summary = array();
    foreach ($health['checks'] as $name => $check) {
        $summary[$name] = $check['ok'] ? "ok" : "fail";
    }
    echo json_encode(array("status" => $health['ok'] ? "ok" : "fail", "checks" => $summary));
    exit();
}

// if(count(explode("API", $request)) > 1){
//     // do some authentication
//     require require $_SERVER['DOCUMENT_ROOT'] . $request . ".php";
//     exit();
// }

require $_SERVER['DOCUMENT_ROOT'] . '/partials/head.php';
require $_SERVER['DOCUMENT_ROOT'] . '/partials/navigation.php';

switch ($request[0]) {
    case '/' /*|| '/index.php' */
    :
        header("location: /feed");
        require $_SERVER['DOCUMENT_ROOT'] . '/Pages/index.php';
        break;
    case '/login' :
        require $_SERVER['DOCUMENT_ROOT'] . '/Pages/login.php';
        break;
    case '/signup' :
        require $_SERVER['DOCUMENT_ROOT'] . '/Pages/signup.php';
        break;
    case '/invite' :
        require $_SERVER['DOCUMENT_ROOT'] . '/Pages/invite.php';
        break;
    case '/feed':
        require $_SERVER['DOCUMENT_ROOT'] . '/Pages/feed.php';
        break;
    case '/wardrobe':
        require $_SERVER['DOCUMENT_ROOT'] . '/Pages/wardrobe.php';
        break;
    case '/admin':
        if (is_admin()) {
            require $_SERVER['DOCUMENT_ROOT'] . '/Pages/admin.php';
        } else {
            http_response_code(403);
            require $_SERVER['DOCUMENT_ROOT'] . '/Pages/403.php';
        }
        break;
    case '/account':
        require $_SERVER['DOCUMENT_ROOT'] . '/Pages/account.php';
        break;
    case (bool)preg_match('#^/user/#', $request[0]):
        require $_SERVER['DOCUMENT_ROOT'] . '/Pages/user.php';
        break;
    case (bool)preg_match('#^/@[^/@]+@[^/@]+$#', $request[0]):
        //someone on another server, /@name@host
        require $_SERVER['DOCUMENT_ROOT'] . '/Pages/remote_user.php';
        break;
    case '/search':
        require $_SERVER['DOCUMENT_ROOT'] . '/Pages/search.php';
        break;
    case (bool)preg_match('#^/@[^/]+$#', $request[0]):
        //short profile address, /@username
        header("location: /user/" . rawurlencode(rawurldecode(substr(parse_url($request[0], PHP_URL_PATH), 2))), true, 301);
        exit();
    case (bool)preg_match('#^/outfit/[^/]+$#', $request[0]):
        require $_SERVER['DOCUMENT_ROOT'] . '/Pages/outfit.php';
        break;
    case (bool)preg_match('#^/garment/[^/]+$#', $request[0]):
        require $_SERVER['DOCUMENT_ROOT'] . '/Pages/garment.php';
        break;
    case '/outfits':
        require $_SERVER['DOCUMENT_ROOT'] . '/Pages/outfits.php';
        break;
    case '/forgot':
        require $_SERVER['DOCUMENT_ROOT'] . '/Pages/forgot.php';
        break;
    case '/reset':
        require $_SERVER['DOCUMENT_ROOT'] . '/Pages/reset.php';
        break;
    case '/about':
        require $_SERVER['DOCUMENT_ROOT'] . '/Pages/about.php';
        break;
    case '/notifications':
        require $_SERVER['DOCUMENT_ROOT'] . '/Pages/notifications.php';
        break;
    case '/insights':
        require $_SERVER['DOCUMENT_ROOT'] . '/Pages/insights.php';
        break;
    case '/privacy':
        require $_SERVER['DOCUMENT_ROOT'] . '/Pages/privacy.php';
        break;
    default:
        http_response_code(404);
        require $_SERVER['DOCUMENT_ROOT'] . '/Pages/404.php';
        break;
}

require $_SERVER['DOCUMENT_ROOT'] . '/partials/foot.php';

ob_end_flush();