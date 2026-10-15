<?php
$movieUrl = url(movie_path((int) $movie['id']));
$poster = $movie['poster_path'] ?: 'img/home-background.png';
$saved = isset($savedMovieIds) && in_array((int) $movie['id'], $savedMovieIds, true);
?>
<article class="movie-card">
  <a class="poster-link" href="<?= h($movieUrl) ?>" aria-label="View <?= h($movie['title']) ?>">
    <img class="movie-poster" src="<?= h(asset_url($poster)) ?>" alt="<?= h($movie['title']) ?> poster" loading="lazy">
    <span class="poster-overlay"><span class="round-play" aria-hidden="true">▶</span></span>
  </a>
  <div class="card-details">
    <div class="card-title-line"><h3><a href="<?= h($movieUrl) ?>"><?= h($movie['title']) ?></a></h3>
      <?php if (current_user()): ?>
        <form method="post" action="<?= h(url('favorite.php')) ?>" class="inline-form">
          <?php csrf_field(); ?><input type="hidden" name="movie_id" value="<?= (int) $movie['id'] ?>">
          <input type="hidden" name="return_to" value="<?= h($returnPath ?? 'index.php') ?>">
          <button class="favorite-button<?= $saved ? ' is-saved' : '' ?>" type="submit" aria-label="<?= $saved ? 'Remove from' : 'Add to' ?> favourites" title="<?= $saved ? 'Remove from' : 'Add to' ?> favourites"><?= $saved ? '♥' : '♡' ?></button>
        </form>
      <?php else: ?>
        <a class="favorite-button" href="<?= h(url('account.php?next=' . rawurlencode($returnPath ?? 'index.php'))) ?>" aria-label="Sign in to save this movie" title="Sign in to save">♡</a>
      <?php endif; ?>
    </div>
    <p class="card-meta"><?= h($movie['genre']) ?><?= $movie['release_year'] ? ' · ' . (int) $movie['release_year'] : '' ?></p>
    <?php if ($movie['rating'] !== null): ?><p class="card-rating"><span aria-hidden="true">★</span> <?= h(number_format((float) $movie['rating'], 1)) ?>/10</p><?php endif; ?>
  </div>
</article>
