<?php
require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

try {
    if (empty($_SESSION['user_id'])) {
        http_response_code(403);
        throw new Exception('Log in first');
    }
    CSRF_validate($_POST['csrf'] ?? null);

    $owner = ($_POST['owner'] ?? "") === "blog" ? "blog" : "me";
    if ($owner == "blog" && !is_admin()) {
        http_response_code(403);
        throw new Exception('Admins only');
    }
    $owner_id = $owner == "blog" ? THEME_BLOG : (int)$_SESSION['user_id'];

    if (!empty($_POST['reset'])) {
        theme_reset($owner_id);
        $problems = array();
    } else {
        $problems = theme_save($owner_id, $_POST, (int)$_SESSION['user_id']);
    }
    $_SESSION['theme_problems'][$owner] = $problems;

    header('Location: ' . ($owner == "blog" ? "/admin#theme" : "/account#theme"));
    exit();
} catch (Exception $e) {
    echo json_encode(array(
        "state" => "brokey",
        "reason" => $e->getMessage()
    ));
}
