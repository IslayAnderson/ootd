<?php
session_save_path($_SERVER['DOCUMENT_ROOT'].'/tmp');
ini_set('session.gc_max_lifetime', 1440);
session_start();

include($_SERVER['DOCUMENT_ROOT']."/Functions/functions.php");
include($_SERVER['DOCUMENT_ROOT']."/Classes/classes.php");
include($_SERVER['DOCUMENT_ROOT']."/vendor/autoload.php");

CSRF_genrate();

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable($_SERVER['DOCUMENT_ROOT']);
$dotenv->load();

if(!isset($_SESSION['user_id'])){
    
}else{
    
}