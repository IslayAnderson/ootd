<?php
/**
 * @return bool
 * @throws \Random\RandomException
 */
function CSRF_generate(): bool
{
    if (!isset($_SESSION['CSRF']['expiry'])) {
        $_SESSION['CSRF'] = array();
        $_SESSION['CSRF']['expiry'] = time();
    }
    if (time() < $_SESSION['CSRF']['expiry'] && !empty($_SESSION['CSRF']['token'])) {
        return false;
    }

    $CSRF = id_gen();

    if (isset($_SESSION['user_id'])) {
        $params = array(':a' => $CSRF, ':b' => $_SESSION['user_id']);
        $sql = "UPDATE `users` SET `csrf`= :a WHERE `user_id` = :b";

        $db = new Mysql();
        $db->Fetch($sql, $params);
    }

    $_SESSION['CSRF']["token"] = $CSRF;
    $_SESSION['CSRF']["expiry"] = strtotime("+1 hour");

    return true;
}

/**
 * @throws Exception
 */
function CSRF_validate($input = null): true
{
    if (empty($input) || empty($_SESSION['user_id'])) {
        throw new Exception('CSRF token missing');
    }

    $params = array(':a' => $_SESSION['user_id']);
    $sql = "SELECT `csrf` FROM `users` WHERE `user_id` = :a";
    $db = new Mysql();
    $row = $db->Fetch($sql, $params);

    $CSRF = $row[0]->csrf;

    if (!empty($CSRF) && hash_equals($CSRF, (string)$input)) {
        return true;
    } else {
        //only the session that sent the bad token is ended, not everyone's
        $user = new User($_SESSION['user_id']);
        $user->deauthenticate();
        throw new Exception('CSRF token mismatch');
    }
}