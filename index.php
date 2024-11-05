<?php
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
    case '/dashboard':
        require $_SERVER['DOCUMENT_ROOT'] . '/pages/dashboard.php';
        break;
    default:
        http_response_code(404);
        require $_SERVER['DOCUMENT_ROOT'] . '/pages/404.php';
        break;
}

require $_SERVER['DOCUMENT_ROOT'] . '/partials/foot.php';