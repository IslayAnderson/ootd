<?php
require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

try {
    CSRF_validate($_POST['csrf'] ?? null);
    $comment = Comment::get((string)($_POST['comment_id'] ?? ""));
    if (!$comment) {
        throw new Exception('Comment not found');
    }
    $back = explode("#", Comment::link($comment))[0] . "#comments";
    Comment::delete($_SESSION['user_id'], $comment->comment_id);

    header('Location: ' . $back);
    exit();
} catch (Exception $e) {
    echo json_encode(array(
        "state" => "brokey",
        "reason" => $e->getMessage()
    ));
}
