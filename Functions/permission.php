<?php
/**
 * Permission ids match the rows seeded into the `permissions` table by the installer
 */
const PERMISSION_ADMIN = 1;
const PERMISSION_MODERATOR = 2;
const PERMISSION_USER = 3;

/**
 * @return int|null permission id of the signed in user, null for guests
 */
function current_permission(): ?int
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $params = array(':a' => $_SESSION['user_id']);
    $sql = "SELECT `permission` FROM `users` WHERE `user_id` = :a";

    $db = new Mysql();
    $row = $db->Fetch($sql, $params);

    if ($row[0] == '00000') {
        return null;
    }

    return (int)$row[0]->permission;
}

/**
 * @return bool
 */
function is_admin(): bool
{
    return current_permission() === PERMISSION_ADMIN;
}

/**
 * @return bool
 */
function is_moderator(): bool
{
    return in_array(current_permission(), array(PERMISSION_ADMIN, PERMISSION_MODERATOR), true);
}
