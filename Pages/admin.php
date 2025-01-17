<?php ?>
<div class="accordion" >
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
        <div class="accordion__section-content">
            Nothing to moderate
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
