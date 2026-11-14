<?php
require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

try {
    CSRF_validate($_POST['csrf'] ?? null);

    $blog = new Post\Blog();

    $request = array(
        "user_id"       =>  $_SESSION['user_id'],
        "title"         =>  $_POST['title'] ?? "",
        "excerpt"       =>  $_POST['excerpt'] ?? "",
        "content"       =>  $_POST['blog'] ?? "",
        "outfits"       =>  $_POST['outfits'] ?? array(),
        "visibility"    =>  $_POST['visibility'] ?? ""
    );

    $blog->create_blog($request);

    header('Location: /account?posted=journal');
    exit();
} catch (Exception $e) {
    echo json_encode(array(
        "state" => "brokey",
        "reason" => $e->getMessage()
    ));
}
