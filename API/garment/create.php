<?php
require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

try {
    CSRF_validate($_POST['csrf'] ?? null);

    $garment = new Post\Garment();

    $request = array(
        "user_id"       =>  $_SESSION['user_id'],
        "garment_name"  =>  $_POST['garment_name'] ?? "",
        "description"   =>  $_POST['description'] ?? "",
        "tags"          =>  $_POST['tags'] ?? "",
        "brand"         =>  $_POST['brand'] ?? "",
        "category"      =>  $_POST['category'] ?? "",
        "media_id"      =>  Media::store_upload($_FILES['image'] ?? null, $_SESSION['user_id'], $_POST['alt_text'] ?? "", false),
        "visibility"    =>  $_POST['visibility'] ?? ""
    );

    $garment->create_garment($request);

    header('Location: /account?posted=garment');
    exit();
} catch (Exception $e) {
    echo json_encode(array(
        "state" => "brokey",
        "reason" => $e->getMessage()
    ));
}
