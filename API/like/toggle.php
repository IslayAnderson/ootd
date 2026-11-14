<?php
require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

try {
    CSRF_validate($_POST['csrf'] ?? null);
    $kind = (string)($_POST['kind'] ?? "");
    if (!in_array($kind, Like::KINDS, true)) {
        throw new Exception('Post not found');
    }
    Like::toggle($_SESSION['user_id'], $kind, (string)($_POST['id'] ?? ""));

    header('Location: ' . back_path("/feed"));
    exit();
} catch (Exception $e) {
    echo json_encode(array(
        "state" => "brokey",
        "reason" => $e->getMessage()
    ));
}
