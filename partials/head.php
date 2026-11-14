<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>{instance_placeholder} - {title_placeholder}</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <meta name="theme-color" content="#ffffff" />
  <meta name="description" content="{description_placeholder}" />
  <meta content="<?= $_SESSION['CSRF']['token'] ?>" name="csrf-token" />
  <link rel="preload" href="/Assets/fonts/newsreader-latin-opsz-normal.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="/Assets/fonts/inter-tight-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" type="text/css" href="<?= asset_url("/Assets/stylesheets/css/main.css") ?>" />
  <link rel="icon" href="<?= asset_url("/Assets/images/favicon.png") ?>">
</head>
<body>