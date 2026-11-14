<?php
require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

$token = (string)($_POST['token'] ?? "");
try {
    PasswordReset::reset($token, $_POST['password'] ?? "");
    header('Location: /login?reset=1');
} catch (Exception $e) {
    header('Location: /reset?token=' . urlencode($token) . '&error=' . urlencode($e->getMessage()));
}
exit();
