<?php
$pageTitle = $pageTitle ?? 'Movies Village';
$user = current_user();
$notice = flash();
$searchValue = isset($_GET['q']) && is_string($_GET['q']) ? $_GET['q'] : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#111116">
  <title><?= h($pageTitle) ?> · Movies Village</title>
  <link rel="icon" href="<?= h(asset_url('img/fav-icon.png')) ?>">
  <link rel="stylesheet" href="<?= h(asset_url('style.css') . '?v=' . (string) filemtime(dirname(__DIR__) . '/style.css')) ?>">
  <script src="<?= h(asset_url('main.js')) ?>" defer></script>
</head>
<body>
<header class="site-header">
  <div class="header-inner">
    <a class="logo" href="<?= h(url('index.php')) ?>">Movies<span>Village</span></a>
    <form class="search-form" action="<?= h(url('index.php')) ?>" method="get" role="search">
      <label class="visually-hidden" for="site-search">Search movies</label>
      <input id="site-search" type="search" name="q" value="<?= h($searchValue) ?>" placeholder="Search movies, genres, cast…">
      <button class="icon-button" type="submit" aria-label="Search">⌕</button>
    </form>
    <div class="account-links">
      <?php if ($user): ?>
        <span class="welcome">Hi, <?= h($user['name']) ?></span>
        <?php if (($user['role'] ?? '') === 'admin'): ?><a class="small-link" href="<?= h(url('admin/index.php')) ?>">Manage</a><?php endif; ?>
        <form method="post" action="<?= h(url('logout.php')) ?>" class="inline-form"><?php csrf_field(); ?><button class="text-button" type="submit">Sign out</button></form>
      <?php else: ?>
        <a class="small-link" href="<?= h(url('account.php')) ?>">Sign in</a>
      <?php endif; ?>
      <span class="avatar" aria-hidden="true">MV</span>
    </div>
  </div>
</header>
<nav class="site-nav" aria-label="Main navigation">
  <a href="<?= h(url('index.php#home')) ?>"><span aria-hidden="true">⌂</span><span>Home</span></a>
  <a href="<?= h(url('index.php?view=trending#popular')) ?>"><span aria-hidden="true">↗</span><span>Trending</span></a>
  <a href="<?= h(url('index.php#movies')) ?>"><span aria-hidden="true">▦</span><span>Explore</span></a>
  <a href="<?= h(url('favorites.php')) ?>"><span aria-hidden="true">♡</span><span>Favourites</span></a>
</nav>
<main class="page-shell">
  <?php if ($notice): ?><div class="notice notice-<?= h($notice['type']) ?>" role="status"><?= h($notice['message']) ?></div><?php endif; ?>
