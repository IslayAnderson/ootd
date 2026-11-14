<?php
require $_SERVER['DOCUMENT_ROOT']."/init.php";

$user = new User();
$validate = new Validate();
$invite = new Invite();

$request = array(
    "username"      =>  trim($_POST['username'] ?? ""),
    "email"         =>  trim($_POST['email'] ?? ""),
    "password"      =>  $_POST['password'] ?? "",
    "first_name"    =>  trim($_POST['first_name'] ?? ""),
    "last_name"     =>  trim($_POST['last_name'] ?? "")
);

//signups need an approved invite, and the account takes the invited email
$code = $_POST['code'] ?? "";
$open_invite = $invite->find_by_code($code);
if (!$open_invite) {
    header('Location: /invite?error=' . urlencode("This invite isn't valid any more"));
    exit();
}
$request['email'] = $open_invite->email;

$state = $validate->signup($request);

if ($state['state']) {
    try {
        $user->create_user($request);
        $invite->mark_used($code, $user->get_user_id($request['email']));
        header('Location: /login?created=1');
    } catch (Exception $e) {
        header('Location: /invite?code=' . urlencode($code) . '&error=' . urlencode($e->getMessage()));
    }
} else {
    header('Location: /invite?code=' . urlencode($code) . '&error=' . urlencode($state['reason']));
}
exit();
