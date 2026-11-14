<?php
require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

try {
    CSRF_validate($_POST['csrf'] ?? null);

    $user = new User($_SESSION['user_id']);
    $user->set_image(image_upload($_FILES['image'] ?? null));

    header('Location: /account?posted=avatar');
    exit();
} catch (Exception $e) {
    echo json_encode(array(
        "state" => "brokey",
        "reason" => $e->getMessage()
    ));
}
