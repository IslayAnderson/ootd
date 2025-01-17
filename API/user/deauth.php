<?php
require $_SERVER['DOCUMENT_ROOT']."/init.php";

if(!isset($_SESSION['user_id'])){
    throw new Exception('Session not found');
}else{
    $user = new user($_SESSION['user_id']);
    $user->deauthenticate();
}