<?php
require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

try {
    CSRF_validate($_POST['csrf'] ?? null);

    Post\Garment::delete_garment($_SESSION['user_id'], $_POST['garment_id'] ?? "");

    header('Location: /wardrobe?deleted=garment');
    exit();
} catch (Exception $e) {
    echo json_encode(array(
        "state" => "brokey",
        "reason" => $e->getMessage()
    ));
}
