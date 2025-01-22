<?php
$username = explode("/", $_SERVER['REQUEST_URI'])[2];
$user_id = unserialize($_SESSION['user'])->get_user_id_from_username($username);
$user = new User($user_id);
?>
<section class="header" style="border-top: unset;">
    <h1>Account</h1>
    <h2><?= $user->get_username($user->user_id) ?></h2>
    <h3><?= $user->first_name . " " . $user->last_name ?></h3>
</section>
<section class="blog">
    <h2>Blogs</h2>
    <?php
    $blogs = new Post\Blog($user->get_user_id());
    if (!$blogs) {
        $blogs->render_blogs_profile();
    } else {
        ?>
        <p>Nothing to be found</p>
        <?php
    }
    ?>
</section>
<section class="outfits">
    <h2>Outfits</h2>
    <?php
    $params = array(":a" => $user->get_user_id()); //todo: sanitize this
    $sql = "SELECT `outfit_num` FROM `outfits` 
                    join `users` on outfits.user = users.user_id
                    where users.user_id = :a order by timestamp limit 30";
    $db = new Mysql();
    $row = $db->Fetch($sql, $params);

    foreach ($row as $outfit) {
        $post = new Post\Outfit($outfit->outfit_num);
        $post->render_outfit(true);
    }
    ?>
</section>
<section class="wardrobe">
    <h2>Wardrobe</h2>
    <div>
        <?php
        $wardrobe = new \Post\Wardrobe($user->get_user_id(), 9);
        $wardrobe->render_wardrobe();
        ?>
    </div>
</section>
<section>
    <?php //just for demo
    $sql = "SELECT * FROM `garments` where garment_num = 8";
    $db = new Mysql();
    $jacket = $db->Fetch($sql, null);
    ?>
    <div style="float:right;position: relative;margin-right: 3rem;margin-top: 2rem;transform: rotate(-15deg);z-index: 4;width: 18%;">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" style="
    position: absolute;
    right: -12px;
    top: -12px;
">
            <path d="M6.166 16.943l1.4 1.4-4.622 4.657h-2.944l6.166-6.057zm11.768-6.012c2.322-2.322 4.482-.457 6.066-1.931l-8-8c-1.474 1.584.142 3.494-2.18 5.817-3.016 3.016-4.861-.625-10.228 4.742l9.6 9.6c5.367-5.367 1.725-7.211 4.742-10.228z"></path>
        </svg>
        <img loading="lazy"
             src="<?= image_bin_encode($jacket[0]->image) ?>"
             alt="<?= $jacket[0]->description ?>"
             class=""
        >
    </div>
    <h2>Stats</h2>
    <h3>Daily insights:</h3>
    <p>You haven't worn, "<?= $jacket[0]->garment_name ?>" in 6 months.</p>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/chartist/0.11.4/chartist.min.js"
            integrity="sha512-9rxMbTkN9JcgG5euudGbdIbhFZ7KGyAuVomdQDI9qXfPply9BJh0iqA7E/moLCatH2JD4xBGHwV6ezBkCpnjRQ=="
            crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/chartist/0.11.4/chartist.min.css"
          integrity="sha512-V0+DPzYyLzIiMiWCg3nNdY+NyIiK9bED/T1xNBj08CaIUyK3sXRpB26OUCIzujMevxY9TRJFHQIxTwgzb0jVLg=="
          crossorigin="anonymous" referrerpolicy="no-referrer"/>
    <div class="ct-chart ct-golden-section"></div>
    <script>
        new Chartist.Line('.ct-chart', {
            labels: ["February", "March", "April", "May", "June", "July", "August", 'September', "November", "December", "January"],
            series: [
                [1, 5, 1, 3, 12, 0, 0, 0, 0, 0, 0]
            ]
        }, {
            high: 20,
            low: -3,
            fullWidth: true,
            // As this is axis specific we need to tell Chartist to use whole numbers only on the concerned axis
            axisY: {
                onlyInteger: true,
                offset: 20
            }
        });

        setTimeout(
            function () {
                const path = document.querySelector('.ct-series-a path');
                const length = path.getTotalLength();
                console.log(length);
            },
            3000);
    </script>

</section>
