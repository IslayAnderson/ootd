<?php
require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

try {
    CSRF_validate($_POST['csrf'] ?? null);

    $user = new User($_SESSION['user_id']);
    $user->change_password($_POST['current_password'] ?? "", $_POST['new_password'] ?? "");

    header('Location: /account?posted=password');
    exit();
} catch (Exception $e) {
    echo json_encode(array(
        "state" => "brokey",
        "reason" => $e->getMessage()
    ));
}
