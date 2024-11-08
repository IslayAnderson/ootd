<?php
ini_set('output_handler', null);
ob_start();

include("./init.php");

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
        require $_SERVER['DOCUMENT_ROOT'] . '/pages/index.php';
        break;
    case '/login' :
        require $_SERVER['DOCUMENT_ROOT'] . '/pages/login.php';
        break;
    case '/signup' :
        require $_SERVER['DOCUMENT_ROOT'] . '/pages/signup.php';
        break;
    case '/feed':
        require $_SERVER['DOCUMENT_ROOT'] . '/pages/feed.php';
        break;
    default:
        http_response_code(404);
        require $_SERVER['DOCUMENT_ROOT'] . '/pages/404.php';
        break;
}

require $_SERVER['DOCUMENT_ROOT'] . '/partials/foot.php';

ob_end_flush();