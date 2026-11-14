<?php
title_update("wardrobe");
$user = unserialize($_SESSION['user']);
$filters = array(
    "category" => $_GET['category'] ?? "",
    "tag" => $_GET['tag'] ?? "",
    "search" => $_GET['search'] ?? ""
);
$wardrobe = new \Post\Wardrobe($user->get_user_id(), 200, $filters);
?>
<form class="filters" method="get" action="/wardrobe">
    <label class="filter">
        <select name="category">
            <option value="">Categories</option>
            <?php foreach (categories_list() as $category_id => $category): ?>
                <option value="<?= escape($category_id) ?>" <?= $filters['category'] == $category_id ? "selected" : "" ?>>
                    <?= $category['level'] > 0 ? "&nbsp;&nbsp;" : "" ?><?= escape($category['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <svg width="24px" height="24px" xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd"><path d="M23.245 4l-11.245 14.374-11.219-14.374-.781.619 12 15.381 12-15.391-.755-.609z"/></svg>
    </label>
    <label class="filter">
        <select name="tag">
            <option value="">Tags</option>
            <?php foreach (\Post\Wardrobe::tags($user->get_user_id()) as $tag): ?>
                <option value="<?= escape($tag) ?>" <?= $filters['tag'] == $tag ? "selected" : "" ?>>#<?= escape($tag) ?></option>
            <?php endforeach; ?>
        </select>
        <svg width="24px" height="24px" xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd"><path d="M23.245 4l-11.245 14.374-11.219-14.374-.781.619 12 15.381 12-15.391-.755-.609z"/></svg>
    </label>
    <div class="search">
        <input value="<?= escape($filters['search']) ?>" name="search" placeholder="Start typing..." />
    </div>
</form>
<div class="wardrobe">
    <div>
        <?php
        if (count($wardrobe->wardrobe) > 0) {
            $wardrobe->render_wardrobe_detailed();
        } else {
            ?>
            <p>Nothing to be found</p>
            <?php
        }
        ?>
    </div>
</div>
