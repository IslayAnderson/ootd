<?php
require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

try {
    PasswordReset::request($_POST['email'] ?? "");
} catch (Exception $e) {
    //same answer whatever happened, see PasswordReset::request
}
header('Location: /forgot?sent=1');
exit();
