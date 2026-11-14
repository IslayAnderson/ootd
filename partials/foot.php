        <footer>
            <span>{instance_placeholder}</span>
            &middot; <a href="/about">About</a>
            &middot; <a href="/privacy">Privacy</a>
            <?php if (function_exists("setting_get") && setting_get("source_url", "")): ?>
                &middot; <a href="<?= escape(setting_get("source_url", "")) ?>">Source</a>
            <?php endif; ?>
        </footer>
        <img src="/API/csrf/static.php?token=<?= $_SESSION['CSRF']['token'] ?>" width="0" height="0" style="visibility: hidden"/>
        <script src="/Assets/javascript/main.js"></script>
    </body>
</html>