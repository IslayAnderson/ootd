<?php
title_update("insights");
$user = unserialize($_SESSION['user']);
$insights = new Insights($user->get_user_id());
$per_month = $insights->wears_per_month(12);
?>
<section class="insights">
    <p class="kicker">Insights</p>
    <h1>What you <em>actually</em> wear</h1>
    <?php $insights->render_daily_insight(); ?>
</section>
<section class="insights">
    <h2>Outfits worn per month</h2>
    <?php if (array_sum($per_month['series']) == 0): ?>
        <p>Press "Wore this today" on one of your <a href="/outfits">outfits</a> to start tracking.</p>
    <?php else: ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/chartist/0.11.4/chartist.min.js"
                integrity="sha512-9rxMbTkN9JcgG5euudGbdIbhFZ7KGyAuVomdQDI9qXfPply9BJh0iqA7E/moLCatH2JD4xBGHwV6ezBkCpnjRQ=="
                crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/chartist/0.11.4/chartist.min.css"
              integrity="sha512-V0+DPzYyLzIiMiWCg3nNdY+NyIiK9bED/T1xNBj08CaIUyK3sXRpB26OUCIzujMevxY9TRJFHQIxTwgzb0jVLg=="
              crossorigin="anonymous" referrerpolicy="no-referrer"/>
        <div class="ct-chart ct-golden-section"></div>
        <script>
            new Chartist.Line('.ct-chart', {
                labels: <?= json_encode($per_month['labels']) ?>,
                series: [
                    <?= json_encode($per_month['series']) ?>
                ]
            }, {
                low: 0,
                fullWidth: true,
                // As this is axis specific we need to tell Chartist to use whole numbers only on the concerned axis
                axisY: {
                    onlyInteger: true,
                    offset: 20
                }
            });
        </script>
    <?php endif; ?>
</section>
<section class="insights">
    <h2>Most worn</h2>
    <?php $most_worn = $insights->most_worn(5); ?>
    <?php if (count($most_worn) == 0): ?>
        <p class="empty">Nothing to be found</p>
    <?php else: ?>
        <ol>
            <?php foreach ($most_worn as $garment): ?>
                <li>
                    <span>
                        <a href="/garment/<?= escape($garment->garment_id) ?>"><?= escape($garment->garment_name) ?></a>,
                        worn <?= escape($garment->worn) ?> <?= $garment->worn == 1 ? "time" : "times" ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>
<section class="insights">
    <h2>Gathering dust</h2>
    <?php $least_worn = $insights->least_recently_worn(5); ?>
    <?php if (count($least_worn) == 0): ?>
        <p class="empty">Nothing to be found</p>
    <?php else: ?>
        <ol>
            <?php foreach ($least_worn as $garment): ?>
                <li>
                    <span>
                        <a href="/garment/<?= escape($garment->garment_id) ?>"><?= escape($garment->garment_name) ?></a>,
                        <?= $garment->last_worn ? "last worn " . escape(date_display($garment->last_worn)) : "never worn" ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>
