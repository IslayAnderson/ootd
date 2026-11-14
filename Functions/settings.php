<?php
/**
 * @param $setting
 * @param $default
 * @return mixed value from the `settings` table, $default when it isn't set
 */
function setting_get($setting, $default = null)
{
    $params = array(':a' => $setting);
    $sql = "SELECT `value` FROM `settings` WHERE `setting` = :a";

    $db = new Mysql();
    $row = $db->Fetch($sql, $params);

    if ($row[0] == '00000') {
        return $default;
    }

    return $row[0]->value;
}

/**
 * @param $setting
 * @param $value
 * @param $type
 * @return void
 */
function setting_set($setting, $value, $type = "string")
{
    $db = new Mysql();

    if (setting_get($setting) === null) {
        $params = array(':a' => $setting, ':b' => $type, ':c' => $value);
        $sql = "INSERT INTO `settings` (`setting`, `type`, `value`) VALUES (:a, :b, :c)";
    } else {
        $params = array(':a' => $setting, ':c' => $value);
        $sql = "UPDATE `settings` SET `value` = :c WHERE `setting` = :a";
    }

    $db->Fetch($sql, $params);
}

/**
 * @return array settings rows an admin can change, internal ones are hidden
 */
function settings_editable(): array
{
    $sql = "SELECT * FROM `settings` WHERE `setting` NOT IN ('installed', 'schema_version') ORDER BY `setting_num`";

    $db = new Mysql();
    $row = $db->Fetch($sql, null);

    return $row[0] != '00000' ? $row : array();
}
