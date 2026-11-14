<?php
require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

try {
    if (!is_admin()) {
        http_response_code(403);
        throw new Exception('Admins only');
    }
    CSRF_validate($_POST['csrf'] ?? null);

    $user = new User($_SESSION['user_id']);

    switch ($_POST['action'] ?? "") {
        case "reset_session":
            $user->reset_session((int)$_POST['user_id']);
            break;
        case "set_permission":
            if ((int)$_POST['user_id'] == $_SESSION['user_id']) {
                throw new Exception("You can't change your own permission");
            }
            $user->set_permission((int)$_POST['user_id'], (int)$_POST['permission']);
            break;
        case "kill_all_sessions":
            //logs everyone out, including you
            $user->kill_all_sessions();
            break;
        default:
            throw new Exception('Unknown action');
    }

    header('Location: /admin#users');
    exit();
} catch (Exception $e) {
    echo json_encode(array(
        "state" => "brokey",
        "reason" => $e->getMessage()
    ));
}
