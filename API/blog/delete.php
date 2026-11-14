<?php
require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

try {
    CSRF_validate($_POST['csrf'] ?? null);

    Post\Blog::delete_blog($_SESSION['user_id'], $_POST['blog_id'] ?? "");

    header('Location: /account?deleted=journal');
    exit();
} catch (Exception $e) {
    echo json_encode(array(
        "state" => "brokey",
        "reason" => $e->getMessage()
    ));
}
