<?php
require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

try {
    CSRF_validate($_POST['csrf'] ?? null);

    Post\Outfit::delete_outfit($_SESSION['user_id'], $_POST['outfit_id'] ?? "");

    header('Location: /outfits?deleted=outfit');
    exit();
} catch (Exception $e) {
    echo json_encode(array(
        "state" => "brokey",
        "reason" => $e->getMessage()
    ));
}
