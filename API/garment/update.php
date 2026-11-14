<?php
require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

try {
    CSRF_validate($_POST['csrf'] ?? null);

    $garment = new Post\Garment();

    $request = array(
        "user_id"         =>  $_SESSION['user_id'],
        "garment_id"      =>  $_POST['garment_id'] ?? "",
        "garment_name"    =>  $_POST['garment_name'] ?? "",
        "description"     =>  $_POST['description'] ?? "",
        "tags"            =>  $_POST['tags'] ?? "",
        "brand"           =>  $_POST['brand'] ?? "",
        "category"        =>  $_POST['category'] ?? "",
        "visibility"      =>  $_POST['visibility'] ?? ""
    );

    $garment->update_garment($request);

    header('Location: /garment/' . rawurlencode((string)($_POST['garment_id'] ?? "")) . '?saved=1');
    exit();
} catch (Exception $e) {
    echo json_encode(array(
        "state" => "brokey",
        "reason" => $e->getMessage()
    ));
}
