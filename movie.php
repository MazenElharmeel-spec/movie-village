<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    http_response_code(404);
    $movie = null;
} else {
    $query = db()->prepare('SELECT * FROM movies WHERE id = ? LIMIT 1');
    $query->execute([$id]);
    $movie = $query->fetch() ?: null;
}
$user = current_user();
if (!$movie || (!(bool) $movie['published'] && (!$user || ($user['role'] ?? '') !== 'admin'))) {
    http_response_code(404);
    $pageTitle = 'Movie not found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><h1>Movie not found</h1><p>This title may have been removed or is still a draft.</p><a class="button button-primary" href="' . h(url('index.php')) . '">Back to the collection</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}
$pageTitle = $movie['title'];
$savedMovieIds = [];
if ($user) {
    $saved = db()->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND movie_id = ?');
    $saved->execute([(int) $user['id'], (int) $movie['id']]);
    if ($saved->fetchColumn()) $savedMovieIds[] = (int) $movie['id'];
}
$isSaved = in_array((int) $movie['id'], $savedMovieIds, true);
$castQuery = db()->prepare('SELECT name, photo_path FROM movie_cast WHERE movie_id = ? ORDER BY position, id');
$castQuery->execute([(int) $movie['id']]);
$cast = $castQuery->fetchAll();
if (!$cast) {
    $cast = array_map(static fn(string $name): array => ['name' => $name, 'photo_path' => null], array_values(array_filter(array_map('trim', explode(',', (string) $movie['cast_names'])))));
}
$videoSource = (string) ($movie['video_path'] ?? '');
$isExternalVideo = filter_var($videoSource, FILTER_VALIDATE_URL) !== false;
$trailerUrl = (string) ($movie['trailer_url'] ?? '');
$validTrailer = filter_var($trailerUrl, FILTER_VALIDATE_URL) !== false && strtolower((string) parse_url($trailerUrl, PHP_URL_SCHEME)) === 'https';
$returnPath = movie_path((int) $movie['id']);
require __DIR__ . '/includes/header.php';
?>
<article class="detail-page">
  <section class="detail-hero" style="--detail-image: url('<?= h(asset_url($movie['backdrop_path'] ?: $movie['poster_path'])) ?>')">
    <div class="detail-copy"><a class="back-link" href="<?= h(url('index.php#movies')) ?>">← Back to movies</a>
      <span class="eyebrow">MOVIE #<?= (int) $movie['id'] ?></span>
      <h1><?= h($movie['title']) ?></h1>
      <div class="detail-meta"><span><?= h($movie['genre']) ?></span><?php if ($movie['release_year']): ?><span><?= (int) $movie['release_year'] ?></span><?php endif; ?><?php if ($movie['rating'] !== null): ?><span class="rating">★ <?= h(number_format((float) $movie['rating'], 1)) ?>/10</span><?php endif; ?></div>
      <p><?= nl2br(h($movie['synopsis'])) ?></p>
      <div class="detail-actions">
        <?php if ($videoSource !== ''): ?><a class="button button-primary" href="#movie-player"><span aria-hidden="true">▶</span> Watch movie</a>
        <?php elseif ($validTrailer): ?><a class="button button-primary" href="<?= h($trailerUrl) ?>" target="_blank" rel="noopener noreferrer">Watch trailer ↗</a>
        <?php else: ?><span class="button button-disabled" aria-disabled="true">Video not added yet</span><?php endif; ?>
        <?php if ($validTrailer && $videoSource !== ''): ?><a class="button button-secondary" href="<?= h($trailerUrl) ?>" target="_blank" rel="noopener noreferrer">Watch trailer ↗</a><?php endif; ?>
        <?php if ($user): ?>
          <form method="post" action="<?= h(url('favorite.php')) ?>" class="inline-form"><?php csrf_field(); ?><input type="hidden" name="movie_id" value="<?= (int) $movie['id'] ?>"><input type="hidden" name="return_to" value="<?= h($returnPath) ?>"><button class="button button-secondary" type="submit"><?= $isSaved ? '♥ Saved to favourites' : '♡ Add to favourites' ?></button></form>
        <?php else: ?><a class="button button-secondary" href="<?= h(url('account.php?next=' . rawurlencode($returnPath))) ?>">♡ Sign in to save</a><?php endif; ?>
      </div>
    </div>
  </section>
  <?php if ($videoSource !== ''): ?>
    <section class="player-section" id="movie-player"><div class="section-heading"><div><span class="eyebrow">NOW PLAYING</span><h2><?= h($movie['title']) ?></h2></div><?php if (!$isExternalVideo): ?><a class="button button-secondary" href="<?= h(asset_url($videoSource)) ?>" download>↓ Download video</a><?php endif; ?></div>
      <?php if ($isExternalVideo): ?><video class="movie-player" controls playsinline preload="metadata" poster="<?= h(asset_url($movie['poster_path'])) ?>"><source src="<?= h($videoSource) ?>">Your browser cannot play this video. <a href="<?= h($videoSource) ?>">Open the video</a>.</video>
      <?php else: ?><video class="movie-player" controls playsinline preload="metadata" poster="<?= h(asset_url($movie['poster_path'])) ?>"><source src="<?= h(asset_url($videoSource)) ?>">Your browser cannot play this video.</video><?php endif; ?>
    </section>
  <?php endif; ?>
  <?php if ($cast): ?><section class="content-section"><div class="section-heading"><div><span class="eyebrow">THE PEOPLE</span><h2>Cast</h2></div></div><div class="cast-list"><?php foreach ($cast as $person): ?><article class="cast-card"><?php if ($person['photo_path']): ?><img src="<?= h(asset_url($person['photo_path'])) ?>" alt="Portrait of <?= h($person['name']) ?>" loading="lazy"><?php else: ?><span class="cast-avatar" aria-hidden="true"><?= h(strtoupper(substr($person['name'], 0, 1))) ?></span><?php endif; ?><span><?= h($person['name']) ?></span></article><?php endforeach; ?></div></section><?php endif; ?>
</article>
<?php require __DIR__ . '/includes/footer.php'; ?>
