<?php
require $_SERVER['DOCUMENT_ROOT'] . '/partials/head.php';
?>

<?php
if (!$_GET['install'] == "complete"):
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
            <input type="text" placeholder="Enter Password" name="password" required>

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