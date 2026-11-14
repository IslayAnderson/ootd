<?php ?>
<?php if (($_GET['removed'] ?? "") == "content"): ?>
    <p class="notice">Their content has been deleted.</p>
<?php elseif (($_GET['removed'] ?? "") == "account"): ?>
    <p class="notice">The user and their content have been deleted.</p>
<?php endif; ?>

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
            <th>delete</th>
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
                            <button type="submit">Sign out everywhere</button>
                        </form>
                    <?php else: ?>
                        &nbsp;
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($row_user->user_id != $_SESSION['user_id']): ?>
                        <details class="danger">
                            <summary>Delete&hellip;</summary>
                            <form action="/API/admin/users.php" method="post">
                                <?php CSRF_input(); ?>
                                <input type="hidden" name="user_id" value="<?= escape($row_user->user_id) ?>">
                                <label>
                                    Type <strong><?= escape($row_user->username) ?></strong> to confirm
                                    <input type="text" name="confirm" autocomplete="off" required>
                                </label>
                                <p>Content: their outfits, garments, articles, comments, wears and likes. Other blogs are asked to remove what they have. The account stays.</p>
                                <button type="submit" name="action" value="delete_content" class="secondary">Delete content</button>
                                <p>User: their content, then the account. They can&rsquo;t log in again and the username stays taken. This can&rsquo;t be undone.</p>
                                <button type="submit" name="action" value="delete_account">Delete user</button>
                            </form>
                        </details>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
