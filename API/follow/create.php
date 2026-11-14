<?php
require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

try {
    CSRF_validate($_POST['csrf'] ?? null);

    $username = (string)($_POST['username'] ?? "");
    $followed_id = (new User())->get_user_id_from_username($username);
    if (empty($followed_id)) {
        throw new Exception('Account not found');
    }
    Follow::follow($_SESSION['user_id'], $followed_id);

    header('Location: /user/' . rawurlencode($username) . '');
    exit();
} catch (Exception $e) {
    echo json_encode(array(
        "state" => "brokey",
        "reason" => $e->getMessage()
    ));
}
