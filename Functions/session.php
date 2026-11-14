<?php
/**
 * A logged in session is only valid while it matches users.session, which is cleared by logging out
 * and by the admin "Reset Session" / "Kill All Sessions" buttons
 *
 * @return bool
 */
function session_valid(): bool
{
    if (empty($_SESSION['user_id'])) {
        return false;
    }

    $params = array(':a' => $_SESSION['user_id']);
    $sql = "SELECT `session` FROM `users` WHERE `user_id` = :a";

    $db = new Mysql();
    $row = $db->Fetch($sql, $params);
    if ($row[0] == '00000' || empty($row[0]->session)) {
        return false;
    }
    $stored = (string)$row[0]->session;

    //sessions from before 0.15.1 have no key: the column held their session id, which now serves as
    //the key for every device
    if (empty($_SESSION['session_key']) && hash_equals($stored, session_id())) {
        $_SESSION['session_key'] = $stored;
    }
    return !empty($_SESSION['session_key']) && hash_equals($stored, (string)$_SESSION['session_key']);
}

/**
 * The key all of a person's signed-in sessions share (users.session), made when there isn't one.
 *
 * @param int $user_id
 * @return string
 */
function session_key_for($user_id): string
{
    $db = new Mysql();
    $db->Fetch("UPDATE `users` SET `session` = :a WHERE `user_id` = :b AND (`session` IS NULL OR `session` = '')",
        array(":a" => bin2hex(random_bytes(32)), ":b" => (int)$user_id));
    $row = $db->Fetch("SELECT `session` FROM `users` WHERE `user_id` = :a", array(":a" => (int)$user_id));
    return $row[0] != '00000' ? (string)$row[0]->session : "";
}
