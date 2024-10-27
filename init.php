<?php
include($_SERVER['DOCUMENT_ROOT']."/Functions/functions.php");
include($_SERVER['DOCUMENT_ROOT']."/Classes/classes.php");
include($_SERVER['DOCUMENT_ROOT']."/vendor/autoload.php");

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();