<?php
// included from Pages/user.php, which has already loaded $user and $path
$blog = new Post\Blog($user->user_id, empty($path[4]) ? "-" : $path[4], 1);

if (count($blog->blogs) == 0) {
    http_response_code(404);
    require $_SERVER['DOCUMENT_ROOT'] . '/Pages/404.php';
    return;
}

title_update($blog->blogs[0]['blog_title']);
description_update($blog->blogs[0]['blog_excerpt']);
?>
<section class="blog">
    <?php $blog->render_blog(); ?>
</section>
