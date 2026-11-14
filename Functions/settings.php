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

/**
 * @param string $type a setting's type, "choice:a|b|c" for a fixed list
 * @return array the allowed values for a choice setting, empty for any other type
 */
function setting_choices($type): array
{
    return strpos((string)$type, "choice:") === 0 ? explode("|", substr($type, 7)) : array();
}

/**
 * @param string $type
 * @param mixed $value as posted from /admin
 * @param mixed $current the stored value, kept when the posted one isn't allowed
 * @return string the value to store
 */
function setting_clean($type, $value, $current): string
{
    $choices = setting_choices($type);
    if (count($choices) > 0) {
        return in_array((string)$value, $choices, true) ? (string)$value : (string)$current;
    }
    $limit = $type == "text" ? 5000 : 100;
    return mb_substr(trim((string)$value), 0, $limit);
}

/**
 * @return string closed, invite or open
 */
function registration_mode(): string
{
    $mode = setting_get("registration", "invite");
    return in_array($mode, array("closed", "invite", "open"), true) ? $mode : "invite";
}

/**
 * @return string the instance's name, OOTD unless an admin changed it
 */
function instance_name(): string
{
    $name = setting_get("instance_name", "OOTD");
    return $name === null || $name === "" ? "OOTD" : $name;
}
