<?php
// /garment/<garment_id>
$garment_id = explode("/", parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))[2] ?? "";
$garment = new Post\Garment(null, $garment_id == "" ? "-" : $garment_id);

if (count($garment->garments) == 0) {
    http_response_code(404);
    require $_SERVER['DOCUMENT_ROOT'] . '/Pages/404.php';
    return;
}

title_update($garment->garments[0]['garment_name']);
?>
<section class="garment">
    <?php $garment->render_garment(); ?>
</section>
<section class="outfits">
    <h2>Worn in</h2>
    <?php
    $outfits = Post\Garment::outfits_with($garment->garments[0]['garment_num']);
    if (count($outfits) > 0) {
        foreach ($outfits as $outfit_num) {
            $post = new Post\Outfit($outfit_num);
            $post->render_outfit();
        }
    } else {
        ?>
        <p>Nothing to be found</p>
        <?php
    }
    ?>
</section>
