<?php
require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

try {
    if (!is_admin()) {
        http_response_code(403);
        throw new Exception('Admins only');
    }
    CSRF_validate($_POST['csrf'] ?? null);

    $invite = new Invite();

    switch ($_POST['action'] ?? "") {
        case "approve":
            $invite->approve((int)$_POST['invite_num']);
            break;
        case "decline":
            $invite->decline((int)$_POST['invite_num']);
            break;
        case "create":
            $invite->create_approved(trim($_POST['email'] ?? ""));
            break;
        default:
            throw new Exception('Unknown action');
    }

    header('Location: /admin#moderation');
    exit();
} catch (Exception $e) {
    echo json_encode(array(
        "state" => "brokey",
        "reason" => $e->getMessage()
    ));
}
