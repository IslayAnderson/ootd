<?php ?>

<form action="/API/admin/users.php" method="post">
    <?php CSRF_input(); ?>
    <input type="hidden" name="action" value="kill_all_sessions">
    <button type="submit">Kill All Sessions</button>
</form>
<br>
<table>
    <tbody>
        <tr>
            <th>id</th>
            <th>username</th>
            <th>email</th>
            <th>first_name</th>
            <th>last_name</th>
            <th>permission</th>
            <th>session</th>
        </tr>
        <?php foreach (User::all_users() as $row_user): ?>
            <tr>
                <td><?= escape($row_user->user_id) ?></td>
                <td><a href="/user/<?= escape($row_user->username) ?>"><?= escape($row_user->username) ?></a></td>
                <td><?= escape($row_user->email) ?></td>
                <td><?= escape($row_user->first_name) ?></td>
                <td><?= escape($row_user->last_name) ?></td>
                <td>
                    <?php if ($row_user->user_id == $_SESSION['user_id']): ?>
                        <?= escape(ucfirst((string)$row_user->permission_name)) ?>
                    <?php else: ?>
                        <form action="/API/admin/users.php" method="post">
                            <?php CSRF_input(); ?>
                            <input type="hidden" name="action" value="set_permission">
                            <input type="hidden" name="user_id" value="<?= escape($row_user->user_id) ?>">
                            <select name="permission">
                                <option value="<?= PERMISSION_ADMIN ?>" <?= $row_user->permission == PERMISSION_ADMIN ? "selected" : "" ?>>Admin</option>
                                <option value="<?= PERMISSION_MODERATOR ?>" <?= $row_user->permission == PERMISSION_MODERATOR ? "selected" : "" ?>>Moderator</option>
                                <option value="<?= PERMISSION_USER ?>" <?= $row_user->permission == PERMISSION_USER ? "selected" : "" ?>>User</option>
                            </select>
                            <button type="submit">Save</button>
                        </form>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($row_user->has_session): ?>
                        <form action="/API/admin/users.php" method="post">
                            <?php CSRF_input(); ?>
                            <input type="hidden" name="action" value="reset_session">
                            <input type="hidden" name="user_id" value="<?= escape($row_user->user_id) ?>">
                            <button type="submit">Reset Session</button>
                        </form>
                    <?php else: ?>
                        &nbsp;
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
