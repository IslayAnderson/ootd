<?php
require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

try {
    CSRF_validate($_POST['csrf'] ?? null);

    $params = array(":a" => isset($_POST['approve_followers']) ? 1 : 0, ":b" => $_SESSION['user_id']);
    $db = new Mysql();
    $db->Fetch("UPDATE `users` SET `approve_followers` = :a WHERE `user_id` = :b", $params);

    header('Location: /account?posted=setting#followers');
    exit();
} catch (Exception $e) {
    echo json_encode(array(
        "state" => "brokey",
        "reason" => $e->getMessage()
    ));
}
