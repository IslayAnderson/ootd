<?php
require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

try {
    CSRF_validate($_POST['csrf'] ?? null);

    $blog = new Post\Blog();

    $request = array(
        "user_id"       =>  $_SESSION['user_id'],
        "blog_id"       =>  $_POST['blog_id'] ?? "",
        "title"         =>  $_POST['title'] ?? "",
        "excerpt"       =>  $_POST['excerpt'] ?? "",
        "content"       =>  $_POST['blog'] ?? "",
        "outfits"       =>  $_POST['outfits'] ?? array(),
        "visibility"    =>  $_POST['visibility'] ?? ""
    );

    $blog->update_blog($request);

    header('Location: /user/' . rawurlencode(unserialize($_SESSION['user'])->username) . '/blog/' . rawurlencode((string)($_POST['blog_id'] ?? "")) . '?saved=1');
    exit();
} catch (Exception $e) {
    echo json_encode(array(
        "state" => "brokey",
        "reason" => $e->getMessage()
    ));
}
