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

    return $row[0] != '00000' && !empty($row[0]->session) && hash_equals($row[0]->session, session_id());
}
