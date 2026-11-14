<?php
//find someone here by username, or elsewhere by @name@host or the address of their profile
title_update("search");
$query = trim((string)($_GET['q'] ?? ""));
$message = null;
if ($query !== "") {
    $local = ltrim($query, "@");
    $handle = RemoteActor::parse_handle($query);
    if ($handle && strtolower($handle[1]) === strtolower(Federation::domain())) {
        $local = $handle[0];
        $handle = null;
    }
    if (!$handle && !preg_match('#^https?://#i', $query)) {
        if ((new User())->get_user_id_from_username($local)) {
            header("location: /user/" . rawurlencode($local));
            exit();
        }
        $message = "Nobody here is called " . $local . ".";
    } elseif (!Federation::enabled()) {
        $message = "Following people on other servers is turned off here.";
    } elseif (($actor = RemoteActor::find($query))) {
        header("location: " . RemoteActor::local_path($actor));
        exit();
    } else {
        $message = "Couldn't find " . $query . ". Check it, or try again later if their server is down.";
    }
}
?>
<section class="header">
    <p class="kicker">Search</p>
    <h1>Find <em>people</em></h1>
    <form action="/search" method="get" class="search">
        <label for="q">Username, or a handle elsewhere like @name@mastodon.social</label>
        <input type="search" id="q" name="q" value="<?= escape($query) ?>" placeholder="@name@server" autocomplete="off" required>
        <button type="submit">Search</button>
    </form>
    <?php if ($message): ?>
        <p class="notice"><?= escape($message) ?></p>
    <?php endif; ?>
</section>
