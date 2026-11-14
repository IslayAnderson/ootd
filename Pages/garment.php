<?php
// /garment/<garment_id>
$garment_id = explode("/", parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))[2] ?? "";
$garment = new Post\Garment(null, $garment_id == "" ? "-" : $garment_id);

if (count($garment->garments) == 0) {
    http_response_code(404);
    require $_SERVER['DOCUMENT_ROOT'] . '/Pages/404.php';
    return;
}

$item = $garment->garments[0];
title_update($item['garment_name']);
?>
<section class="garment">
    <?php if (!empty($_GET['saved'])): ?>
        <p class="notice">Your changes have been saved.</p>
    <?php endif; ?>
    <?php $garment->render_garment(); ?>
</section>
<?php if ($item['user'] == ($_SESSION['user_id'] ?? null)): ?>
    <section class="edit">
        <details>
            <summary>Edit garment</summary>
            <form action="/API/garment/update.php" method="post" enctype="multipart/form-data">
                <?php CSRF_input(); ?>
                <input type="hidden" name="garment_id" value="<?= escape($item['garment_id']) ?>">
                <label>
                    Name
                    <input type="text" name="garment_name" maxlength="128" value="<?= escape($item['garment_name']) ?>" required>
                </label>
                <label>
                    Description
                    <textarea name="description" maxlength="1024"><?= escape($item['garment_description']) ?></textarea>
                </label>
                <label>
                    Brand
                    <input type="text" name="brand" list="brands" value="<?= escape($item['brand_name']) ?>">
                    <datalist id="brands">
                        <?php foreach (brands_list() as $brand): ?>
                            <option value="<?= escape($brand) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </label>
                <label>
                    Category
                    <select name="category">
                        <option value="">Pick a category</option>
                        <?php foreach (categories_list() as $category_id => $category): ?>
                            <option value="<?= escape($category_id) ?>"<?= $category_id == $item['category'] ? " selected" : "" ?>><?= $category['level'] > 0 ? "&nbsp;&nbsp;" : "" ?><?= escape($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Tags
                    <input type="text" name="tags" value="<?= escape(tags_display($item['garment_tags'])) ?>">
                </label>
                <label>
                    <?= empty($item['media_id']) ? "Add a photo" : "Replace photo" ?>
                    <input type="file" name="image" accept="image/*">
                </label>
                <label>
                    Describe the photo
                    <input type="text" name="alt_text" maxlength="1500" value="<?= escape($item['alt_text']) ?>">
                </label>
                <?php visibility_select($item['visibility']); ?>
                <button type="submit">Save changes</button>
            </form>
        </details>
        <form action="/API/garment/delete.php" method="post" class="delete" data-confirm="Delete this garment? Outfits that include it will stop showing it.">
            <?php CSRF_input(); ?>
            <input type="hidden" name="garment_id" value="<?= escape($item['garment_id']) ?>">
            <button type="submit" class="link-button">Delete garment</button>
        </form>
    </section>
<?php endif; ?>
<section class="outfits">
    <h2>Worn in</h2>
    <?php
    $outfits = Post\Garment::outfits_with($item['garment_num']);
    if (count($outfits) > 0) {
        foreach ($outfits as $outfit_num) {
            $post = new Post\Outfit($outfit_num);
            $post->render_outfit();
        }
    } else {
        ?>
        <p class="empty">Nothing to be found</p>
        <?php
    }
    ?>
</section>
