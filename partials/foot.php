        <footer>
            <span>{instance_placeholder}</span>
            &middot; <a href="/about">About</a>
            &middot; <a href="/privacy">Privacy</a>
        </footer>
        <img src="/API/csrf/static.php?token=<?= $_SESSION['CSRF']['token'] ?>" width="0" height="0" style="visibility: hidden"/>
        <script src="<?= asset_url("/Assets/javascript/main.js") ?>"></script>
    </body>
</html>