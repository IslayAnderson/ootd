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
        case "delete_content":
        case "delete_account":
            //typing the username is the confirmation
            $db = new Mysql();
            $found = $db->Fetch("SELECT `username` FROM `users` WHERE `user_id` = :a AND `deleted_at` IS NULL", array(":a" => (int)($_POST['user_id'] ?? 0)));
            $target = $found[0] != '00000' ? (string)$found[0]->username : "";
            if ($target === "" || trim((string)($_POST['confirm'] ?? "")) !== $target) {
                throw new Exception("Type the username to confirm");
            }
            if ($_POST['action'] == "delete_content") {
                UserRemoval::content((int)$_POST['user_id']);
            } else {
                UserRemoval::account((int)$_POST['user_id'], (int)$_SESSION['user_id']);
            }
            header('Location: /admin?removed=' . ($_POST['action'] == "delete_content" ? "content" : "account") . '#users');
            exit();
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
