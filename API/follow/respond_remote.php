<?php
require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

try {
    CSRF_validate($_POST['csrf'] ?? null);
    Follow::respond_remote($_SESSION['user_id'], (int)($_POST['actor'] ?? 0), ($_POST['answer'] ?? "") == "accept");
    header('Location: /account#followers');
    exit();
} catch (Exception $e) {
    echo json_encode(array(
        "state" => "brokey",
        "reason" => $e->getMessage()
    ));
}
