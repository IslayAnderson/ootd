<?php
require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

try {
    CSRF_validate($_POST['csrf'] ?? null);

    $db = new Mysql();
    $row = $db->Fetch("SELECT `ap_id` FROM `remote_actors` WHERE `actor_num` = :a", array(":a" => (int)($_POST['actor'] ?? 0)));
    if ($row[0] == '00000') {
        throw new Exception('Account not found');
    }
    $actor = RemoteActor::get($row[0]->ap_id);
    if (($_POST['action'] ?? "") == "follow") {
        RemoteFollow::follow($_SESSION['user_id'], $actor);
    } else {
        RemoteFollow::unfollow($_SESSION['user_id'], $actor);
    }

    header('Location: ' . RemoteActor::local_path($actor));
    exit();
} catch (Exception $e) {
    echo json_encode(array(
        "state" => "brokey",
        "reason" => $e->getMessage()
    ));
}
