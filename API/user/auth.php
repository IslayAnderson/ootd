<?php
require $_SERVER['DOCUMENT_ROOT']."/init.php";

$user = new User();

$request = array(
    "email"         =>  $_POST['email'],
    "password"      =>  $_POST['password']
);



$state = $user->authenticate($request);

if($state){
    header('Location: /feed',200);
    exit();
}else{
    echo json_encode(array(
        "state" => "brokey"
    ));
}

