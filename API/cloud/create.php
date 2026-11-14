<?php
require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

//the form's answers come back with an error, so nobody has to type them again
$keep = array();
foreach (array("subdomain", "blog_name", "username", "email") as $field) {
    $keep[$field] = mb_substr((string)($_POST[$field] ?? ""), 0, 128);
}
try {
    if (($_SERVER['REQUEST_METHOD'] ?? "GET") !== "POST") {
        throw new Exception("Use the form.");
    }
    if (!empty($_POST['website'])) {
        //a bot: act as if it worked
        header('Location: /create-blog?sent=1');
        exit();
    }
    if (empty($_POST['agree'])) {
        throw new Exception("Tick the box to say you've read the privacy page and rules.");
    }
    CloudBlog::request($_POST, (string)($_SERVER['REMOTE_ADDR'] ?? ""));
    header('Location: /create-blog?sent=1');
    exit();
} catch (Exception $e) {
    header('Location: /create-blog?' . http_build_query(array_merge($keep, array("error" => $e->getMessage()))));
    exit();
}
