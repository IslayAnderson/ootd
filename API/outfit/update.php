<?php
require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

try {
    CSRF_validate($_POST['csrf'] ?? null);

    $outfit = new Post\Outfit();

    $request = array(
        "user_id"        =>  $_SESSION['user_id'],
        "outfit_id"      =>  $_POST['outfit_id'] ?? "",
        "description"    =>  $_POST['description'] ?? "",
        "tags"           =>  $_POST['tags'] ?? "",
        "garments"       =>  $_POST['garments'] ?? array(),
        "visibility"     =>  $_POST['visibility'] ?? "",
        "alt_text"       =>  $_POST['alt_text'] ?? null,
        "media_id"       =>  Media::store_upload($_FILES['image'] ?? null, $_SESSION['user_id'], $_POST['alt_text'] ?? "", false)
    );

    $outfit->update_outfit($request);

    header('Location: /outfit/' . rawurlencode((string)($_POST['outfit_id'] ?? "")) . '?saved=1');
    exit();
} catch (Exception $e) {
    echo json_encode(array(
        "state" => "brokey",
        "reason" => $e->getMessage()
    ));
}
