<?php
ini_set('output_handler', null);
ob_start();

require "./init.php";



//check if init has been run (set it global memory I guess) and run init 
//what will be in init?
//the data store and all that jazz :)

$request = $_SERVER['REQUEST_URI'];

// if(count(explode("API", $request)) > 1){
//     // do some authentication
//     require require $_SERVER['DOCUMENT_ROOT'] . $request . ".php";
//     exit();
// }

require $_SERVER['DOCUMENT_ROOT'] . '/partials/head.php';
require $_SERVER['DOCUMENT_ROOT'] . '/partials/navigation.php';

switch ($request) {
    case '/' /*|| '/index.php' */:
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
        require $_SERVER['DOCUMENT_ROOT'] . '/Pages/admin.php';
        break;
    case '/account':
        require $_SERVER['DOCUMENT_ROOT'] . '/Pages/account.php';
        break;
    case '/outfits':
        require $_SERVER['DOCUMENT_ROOT'] . '/Pages/outfits.php';
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