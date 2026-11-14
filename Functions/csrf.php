<?php
/**
 * CSRF tokens belong to the session (0.15.1, AUDIT F27): each device has its own, so signing in
 * somewhere else doesn't invalidate forms here. A token lasts an hour; the one before it is still
 * accepted, so a form left open across the change still works.
 *
 * @return bool a new token was made
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

    $_SESSION['CSRF']['previous'] = $_SESSION['CSRF']['token'] ?? "";
    $_SESSION['CSRF']["token"] = id_gen();
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

    foreach (array($_SESSION['CSRF']['token'] ?? "", $_SESSION['CSRF']['previous'] ?? "") as $token) {
        if ($token !== "" && hash_equals((string)$token, (string)$input)) {
            return true;
        }
    }
    //only the session that sent the bad token is ended, not the person's others
    $user = new User($_SESSION['user_id']);
    $user->deauthenticate();
    throw new Exception('CSRF token mismatch');
}
/**
 * Hidden form field carrying the current CSRF token
 *
 * @return void
 */
function CSRF_input(): void
{
    ?>
    <input type="hidden" name="csrf" value="<?= escape($_SESSION['CSRF']['token'] ?? "") ?>">
    <?php
}
