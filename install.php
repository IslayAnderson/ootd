<?php
require $_SERVER['DOCUMENT_ROOT'] . '/partials/head.php';
?>

<?php
if (($_GET['install'] ?? "") != "complete" && file_exists(__DIR__ . '/.env') && filesize(__DIR__ . '/.env') > 0):
    ?>
    <div class="login">
        <form action="/install_actions.php" method="post">
            <h2>OOTD is installed</h2>
            <p>Log in as an admin first, then run the upgrade to bring the database up to date.</p>
            <button type="submit">Upgrade database</button>
        </form>
    </div>

<?php
elseif (($_GET['install'] ?? "") != "complete"):
    ?>
    <div class="login">
        <form action="/install_actions.php" method="post">
            <label for="host">Database Host(ip/hostname)</label>
            <input type="text" placeholder="Enter Database Host" name="host" required>
            <label for="schema">Database Name</label>
            <input type="text" placeholder="Enter Database Name" name="schema" required>
            <label for="user">Database User</label>
            <input type="text" placeholder="Enter Database User" name="user" required>
            <label for="password">Password</label>
            <input type="password" placeholder="Enter Password" name="password" required>
            <label for="app_url">Site address</label>
            <input type="url" placeholder="https://ootd.example" name="app_url" id="app_url">
            <script>document.getElementById("app_url").value = window.location.origin;</script>

            <button type="submit">Install</button>
        </form>
    </div>

<?php
else:
    $_SESSION['installing'] = "complete";
    ?>

    <div class="login">
        <h2 style="color: darkred">Install complete</h2>
        <h3>Page will redirect to /login in 3 seconds</h3>
        <script>
            setTimeout(function () {
                window.location = "/login"
            }, 3000);
        </script>
    </div>

<?php
endif;
require $_SERVER['DOCUMENT_ROOT'] . '/partials/foot.php';