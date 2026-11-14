<?php
require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

$invite = new Invite();

try {
    if (registration_mode() != "invite") {
        throw new Exception("Invite requests are off");
    }
    $invite->request_invite(trim($_POST['email'] ?? ""));
    header('Location: /signup?requested=1');
} catch (Exception $e) {
    header('Location: /signup?error=1');
}
exit();
