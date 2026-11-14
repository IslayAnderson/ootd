<?php
$invites = Invite::open_invites();
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? "https" : "http";
?>

<form action="/API/admin/invites.php" method="post" class="invite_create">
    <?php CSRF_input(); ?>
    <input type="hidden" name="action" value="create">
    <label for="invite_email">Invite someone</label>
    <input type="email" id="invite_email" name="email" placeholder="Enter email" required>
    <button type="submit">Create invite</button>
</form>
<br>
<?php if (count($invites) == 0): ?>
    Nothing to moderate
<?php else: ?>
    <table>
        <tbody>
            <tr>
                <th>email</th>
                <th>requested</th>
                <th>invite</th>
                <th>&nbsp;</th>
            </tr>
            <?php foreach ($invites as $invite): ?>
                <tr>
                    <td><?= escape($invite->email) ?></td>
                    <td><time datetime="<?= escape($invite->timestamp) ?>"><?= escape($invite->timestamp) ?></time></td>
                    <td>
                        <?php if ($invite->state == 'approved'): ?>
                            <input type="text" readonly
                                   value="<?= escape($scheme . "://" . $_SERVER['HTTP_HOST'] . "/invite?code=" . $invite->code) ?>">
                        <?php else: ?>
                            <form action="/API/admin/invites.php" method="post">
                                <?php CSRF_input(); ?>
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="invite_num" value="<?= escape($invite->invite_num) ?>">
                                <button type="submit">Approve</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form action="/API/admin/invites.php" method="post">
                            <?php CSRF_input(); ?>
                            <input type="hidden" name="action" value="decline">
                            <input type="hidden" name="invite_num" value="<?= escape($invite->invite_num) ?>">
                            <button type="submit"><?= $invite->state == 'approved' ? "Revoke" : "Decline" ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
