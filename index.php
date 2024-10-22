<?php
include("./init.php");

//check if init has been run (set it global memory I guess) and run init 
//what will be in init?
//the data store and all that jazz :)

$request = $_SERVER['REQUEST_URI'];

switch ($request) {
    case '/' /*|| '/index.php' */:
        require __DIR__ . '/pages/index.php';
        break;
    case '/login' :
        require __DIR__ . '/pages/login.php';
        break;
    case '/signup' :
        require __DIR__ . '/pages/signup.php';
        break;
    default:
        http_response_code(404);
        require __DIR__ . '/pages/404.php';
        break;
}