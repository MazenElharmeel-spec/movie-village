<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
$user = require_login('favorites.php');
$query = db()->prepare('SELECT m.* FROM favorites f JOIN movies m ON m.id = f.movie_id WHERE f.user_id = ? AND m.published = 1 ORDER BY f.created_at DESC');
$query->execute([(int) $user['id']]);
$movies = $query->fetchAll();
$savedMovieIds = array_map('intval', array_column($movies, 'id'));
$returnPath = 'favorites.php';
$pageTitle = 'Your favourites';
require __DIR__ . '/includes/header.php';
?>
<section class="content-section favorites-page"><div class="section-heading"><div><span class="eyebrow">YOUR SAVED LIST</span><h1>Favourites</h1></div><p class="result-count"><?= count($movies) ?> saved</p></div>
  <?php if ($movies): ?><div class="movie-grid"><?php foreach ($movies as $movie): ?><?php include __DIR__ . '/includes/movie-card.php'; ?><?php endforeach; ?></div>
  <?php else: ?><div class="empty-state"><span class="empty-icon">♡</span><h2>Your list is ready</h2><p>Save titles with the heart button and they’ll show up here.</p><a class="button button-primary" href="<?= h(url('index.php#movies')) ?>">Find a movie</a></div><?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
