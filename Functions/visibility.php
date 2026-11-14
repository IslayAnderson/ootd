<?php
/**
 * Who can see a garment, outfit or blog. Stored in each table's `visibility` column.
 * public: anyone, including people who aren't signed in (and, later, other servers)
 * members: anyone signed in to this instance, which is how every post behaved before 0.7.0
 * followers: the owner's accepted followers (0.9.0)
 * private: only the owner
 */
const VISIBILITY_PUBLIC = "public";
const VISIBILITY_MEMBERS = "members";
const VISIBILITY_FOLLOWERS = "followers";
const VISIBILITY_PRIVATE = "private";

/**
 * @return array value => label, in the order the picker shows them
 */
function visibility_options(): array
{
    return array(
        VISIBILITY_PUBLIC => "Public",
        VISIBILITY_MEMBERS => "Members",
        VISIBILITY_FOLLOWERS => "Followers",
        VISIBILITY_PRIVATE => "Only me"
    );
}

/**
 * @param $value
 * @return string a valid visibility, members when the value isn't one
 */
function visibility_normalise($value): string
{
    return array_key_exists((string)$value, visibility_options()) ? (string)$value : VISIBILITY_MEMBERS;
}

/**
 * @param $owner_id user_id of the item's owner
 * @param $visibility
 * @return bool can the current visitor see it
 */
function can_view($owner_id, $visibility): bool
{
    $viewer = $_SESSION['user_id'] ?? null;
    if (!empty($viewer) && $viewer == $owner_id) {
        return true;
    }
    if ($visibility == VISIBILITY_PUBLIC) {
        return true;
    }
    if (empty($viewer)) {
        return false;
    }
    if ($visibility == VISIBILITY_FOLLOWERS) {
        return Follow::state($viewer, $owner_id) === Follow::ACCEPTED;
    }
    return $visibility == VISIBILITY_MEMBERS;
}

/**
 * SQL condition matching rows the current visitor can see, deleted rows never match
 *
 * @param string $table table name or alias
 * @param string $owner_column column holding the owner's user_id
 * @return string
 */
function visibility_where($table, $owner_column): string
{
    $viewer = (int)($_SESSION['user_id'] ?? 0);
    $visible = array("'" . VISIBILITY_PUBLIC . "'");
    if ($viewer > 0) {
        $visible[] = "'" . VISIBILITY_MEMBERS . "'";
    }
    $or = "";
    if ($viewer > 0) {
        $or = " OR " . $table . ".`" . $owner_column . "` = " . $viewer
            . " OR (" . $table . ".`visibility` = '" . VISIBILITY_FOLLOWERS . "' AND " . $table . ".`" . $owner_column . "` IN"
            . " (SELECT `followed_id` FROM `follows` WHERE `follower_id` = " . $viewer . " AND `state` = 'accepted'))";
    }
    return "(" . $table . ".`deleted_at` IS NULL AND (" . $table . ".`visibility` IN (" . implode(", ", $visible) . ")" . $or . "))";
}

/**
 * Visibility picker for the post and edit forms
 *
 * @param string $selected
 * @return void
 */
function visibility_select($selected = null): void
{
    if ($selected === null) {
        $selected = visibility_normalise(setting_get("default_visibility", VISIBILITY_MEMBERS));
    }
    ?>
    <label>
        Who can see this
        <select name="visibility">
            <?php foreach (visibility_options() as $value => $label): ?>
                <option value="<?= escape($value) ?>"<?= $value == $selected ? " selected" : "" ?>><?= escape($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php
}
