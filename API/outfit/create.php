<?php
require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

try {
    CSRF_validate($_POST['csrf'] ?? null);

    $outfit = new Post\Outfit();

    $request = array(
        "user_id"       =>  $_SESSION['user_id'],
        "description"   =>  $_POST['description'] ?? "",
        "tags"          =>  $_POST['tags'] ?? "",
        "garments"      =>  $_POST['garments'] ?? array(),
        "image"         =>  image_upload($_FILES['image'] ?? null)
    );

    $outfit->create_outfit($request);

    header('Location: /account?posted=outfit');
    exit();
} catch (Exception $e) {
    echo json_encode(array(
        "state" => "brokey",
        "reason" => $e->getMessage()
    ));
}
