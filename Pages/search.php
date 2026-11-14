<?php
//find someone here by username, or on another blog by "name from Blog", name@host or the address of their profile
title_update("search");
$asked = trim((string)($_GET['q'] ?? ""));
$query = $asked;
$message = null;
if ($asked !== "") {
    //"mia from Mia's Closet" becomes mia@closet.example when that blog is known here
    $query = RemoteActor::from_display($query) ?? $query;
    if ($query === $asked && preg_match('/^@?\S+\s+from\s+(.+)$/iu', $asked, $m)) {
        $message = "Couldn't tell which blog " . trim($m[1]) . " is. Search for them by their address instead: their name @ their blog's domain.";
        $query = "";
    }
    $local = ltrim($query, "@");
    $handle = RemoteActor::parse_handle($query);
    if ($handle && Federation::is_own_domain($handle[1])) {
        $local = $handle[0];
        $handle = null;
    }
    if ($message) {
        //nothing to look up
    } elseif (!$handle && !preg_match('#^https?://#i', $query)) {
        if ((new User())->get_user_id_from_username($local)) {
            header("location: /user/" . rawurlencode($local));
            exit();
        }
        $message = "Nobody here is called " . $local . ".";
    } elseif (!Federation::enabled()) {
        $message = "Following people on other blogs is turned off here.";
    } elseif (($actor = RemoteActor::find($query))) {
        header("location: " . RemoteActor::local_path($actor));
        exit();
    } else {
        $message = "Couldn't find " . $asked . ". Check it, or try again later if their blog is down.";
    }
}
?>
<section class="header">
    <p class="kicker">Search</p>
    <h1>Find <em>people</em></h1>
    <form action="/search" method="get" class="search">
        <label for="q">Username, someone on another blog like &ldquo;mia from Mia&rsquo;s Closet&rdquo;, or their address like <!--email_off-->mia@closet.example<!--/email_off--></label>
        <input type="search" id="q" name="q" value="<?= escape($asked) ?>" placeholder="name from blog, or name@blog address" autocomplete="off" required>
        <button type="submit">Search</button>
    </form>
    <?php if ($message): ?>
        <!--email_off--><p class="notice"><?= escape($message) ?></p><!--/email_off-->
    <?php endif; ?>
</section>
