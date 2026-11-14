<?php
require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

try {
    CSRF_validate($_POST['csrf'] ?? null);

    $follower_id = (new User())->get_user_id_from_username((string)($_POST['username'] ?? ""));
    if (empty($follower_id)) {
        throw new Exception('Account not found');
    }
    Follow::respond($_SESSION['user_id'], $follower_id, ($_POST['answer'] ?? "") == "accept");

    header('Location: /account#followers');
    exit();
} catch (Exception $e) {
    echo json_encode(array(
        "state" => "brokey",
        "reason" => $e->getMessage()
    ));
}
