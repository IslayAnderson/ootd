<?php
title_update("admin");
?>
<section class="header">
    <p class="kicker">Behind the scenes</p>
    <h1>Admin</h1>
</section>
<div class="accordion">
    <div class="accordion__section">
        <div class="accordion__section-header">
            <h2 class="accordion__section-heading">
                <span class="accordion__section-button" id="users">
                    Users
                </span>
            </h2>
        </div>
        <div class="accordion__section-content scroll">
            <?php require $_SERVER['DOCUMENT_ROOT']."/partials/user_table.php"; ?>
        </div>
    </div>

    <div class="accordion__section">
        <div class="accordion__section-header">
            <h2 class="accordion__section-heading">
                <span class="accordion__section-button" id="moderation">
                    Moderation
                </span>
            </h2>
        </div>
        <div class="accordion__section-content scroll">
            <?php require $_SERVER['DOCUMENT_ROOT']."/partials/moderation.php"; ?>
        </div>
    </div>

    <div class="accordion__section">
        <div class="accordion__section-header">
            <h2 class="accordion__section-heading">
                <span class="accordion__section-button" id="jobs">
                    Jobs
                </span>
            </h2>
        </div>
        <div class="accordion__section-content">
            <?php require $_SERVER['DOCUMENT_ROOT']."/partials/jobs.php"; ?>
        </div>
    </div>

    <div class="accordion__section">
        <div class="accordion__section-header">
            <h2 class="accordion__section-heading">
                <span class="accordion__section-button" id="cloud">
                    Cloud blogs
                </span>
            </h2>
        </div>
        <div class="accordion__section-content">
            <?php require $_SERVER['DOCUMENT_ROOT']."/partials/cloud.php"; ?>
        </div>
    </div>

    <div class="accordion__section">
        <div class="accordion__section-header">
            <h2 class="accordion__section-heading">
                <span class="accordion__section-button" id="theme">
                    Theme
                </span>
            </h2>
        </div>
        <div class="accordion__section-content">
            <?php $theme_owner = "blog"; require $_SERVER['DOCUMENT_ROOT']."/partials/theme_form.php"; ?>
        </div>
    </div>

    <div class="accordion__section">
        <div class="accordion__section-header">
            <h2 class="accordion__section-heading">
                <span class="accordion__section-button" id="settings">
                    Settings
                </span>
            </h2>
        </div>
        <div class="accordion__section-content">
            <?php require $_SERVER['DOCUMENT_ROOT']."/partials/settings.php"; ?>
        </div>
    </div>
</div>
