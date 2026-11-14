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

//signups need an approved invite, and the account takes the invited email, unless registration is open
$code = $_POST['code'] ?? "";
$open_invite = $code !== "" ? $invite->find_by_code($code) : false;
$open_signup = !$open_invite && $code === "" && registration_mode() == "open";
$back = $open_signup ? '/signup?error=' : '/invite?code=' . urlencode($code) . '&error=';
if (!$open_invite && !$open_signup) {
    header('Location: /invite?error=' . urlencode("This invite isn't valid any more"));
    exit();
}
if ($open_invite) {
    $request['email'] = $open_invite->email;
}

$state = $validate->signup($request);

if ($state['state']) {
    try {
        $user->create_user($request);
        if ($open_invite) {
            $invite->mark_used($code, $user->get_user_id($request['email']));
        }
        header('Location: /login?created=1');
    } catch (Exception $e) {
        header('Location: ' . $back . urlencode($e->getMessage()));
    }
} else {
    header('Location: ' . $back . urlencode($state['reason']));
}
exit();
