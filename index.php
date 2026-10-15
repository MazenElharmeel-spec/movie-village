<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$pdo = db();
$q = isset($_GET['q']) && is_string($_GET['q']) ? trim(mb_substr($_GET['q'], 0, 100)) : '';
$view = ($_GET['view'] ?? '') === 'trending' ? 'trending' : 'all';
$page = max(1, (int) ($_GET['page'] ?? 1));
$pageSize = 12;
$conditions = ['published = 1'];
$params = [];
if ($view === 'trending') {
    $conditions[] = 'trending = 1';
}
if ($q !== '') {
    $conditions[] = '(title LIKE :title_q OR genre LIKE :genre_q OR synopsis LIKE :synopsis_q OR cast_names LIKE :cast_q)';
    $term = '%' . $q . '%';
    $params = [':title_q' => $term, ':genre_q' => $term, ':synopsis_q' => $term, ':cast_q' => $term];
}
$where = implode(' AND ', $conditions);
$countQuery = $pdo->prepare("SELECT COUNT(*) FROM movies WHERE {$where}");
$countQuery->execute($params);
$total = (int) $countQuery->fetchColumn();
$pages = max(1, (int) ceil($total / $pageSize));
$page = min($page, $pages);
$movieQuery = $pdo->prepare("SELECT * FROM movies WHERE {$where} ORDER BY created_at DESC, id DESC LIMIT :page_size OFFSET :offset");
foreach ($params as $key => $value) {
    $movieQuery->bindValue($key, $value, PDO::PARAM_STR);
}
$movieQuery->bindValue(':page_size', $pageSize, PDO::PARAM_INT);
$movieQuery->bindValue(':offset', ($page - 1) * $pageSize, PDO::PARAM_INT);
$movieQuery->execute();
$movies = $movieQuery->fetchAll();

$featuredQuery = $pdo->query("SELECT * FROM movies WHERE published = 1 ORDER BY featured DESC, trending DESC, created_at DESC, id DESC LIMIT 1");
$featured = $featuredQuery->fetch() ?: null;
$trending = $pdo->query('SELECT * FROM movies WHERE published = 1 AND trending = 1 ORDER BY created_at DESC, id DESC LIMIT 12')->fetchAll();
$savedMovieIds = [];
if ($user = current_user()) {
    $savedQuery = $pdo->prepare('SELECT movie_id FROM favorites WHERE user_id = ?');
    $savedQuery->execute([(int) $user['id']]);
    $savedMovieIds = array_map('intval', array_column($savedQuery->fetchAll(), 'movie_id'));
}
$returnPath = 'index.php' . ($q !== '' ? '?q=' . rawurlencode($q) : '') . '#movies';
$pageTitle = $q !== '' ? 'Search: ' . $q : ($view === 'trending' ? 'Trending' : 'Discover');
require __DIR__ . '/includes/header.php';
?>
<?php if ($featured): ?>
<section class="hero" id="home" style="--hero-image: url('<?= h(asset_url($featured['backdrop_path'] ?: $featured['poster_path'])) ?>')">
  <div class="hero-copy">
    <span class="eyebrow">MOVIES VILLAGE PICK</span>
    <h1><?= h($featured['title']) ?></h1>
    <p class="hero-meta"><?= h($featured['genre']) ?><?= $featured['release_year'] ? ' · ' . (int) $featured['release_year'] : '' ?><?= $featured['rating'] !== null ? ' · ★ ' . h(number_format((float) $featured['rating'], 1)) : '' ?></p>
    <p class="hero-description"><?= h(mb_strimwidth($featured['synopsis'], 0, 190, '…')) ?></p>
    <a class="button button-primary" href="<?= h(url(movie_path((int) $featured['id']))) ?>"><span aria-hidden="true">▶</span> View movie</a>
  </div>
</section>
<?php else: ?>
<section class="empty-hero" id="home"><span class="eyebrow">MOVIES VILLAGE</span><h1>Your next movie night starts here.</h1><p>Add your first title from the movie manager.</p></section>
<?php endif; ?>

<?php if ($trending): ?>
<section class="content-section" id="popular">
  <div class="section-heading"><div><span class="eyebrow">WHAT'S HOT</span><h2>Trending now</h2></div>
    <div class="carousel-controls"><button type="button" class="icon-button" data-scroll="left" data-target="trending-list" aria-label="Scroll trending movies left">‹</button><button type="button" class="icon-button" data-scroll="right" data-target="trending-list" aria-label="Scroll trending movies right">›</button></div>
  </div>
  <div class="movie-row" id="trending-list">
    <?php foreach ($trending as $movie): ?>
      <div class="row-item"><?php include __DIR__ . '/includes/movie-card.php'; ?></div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="content-section" id="movies">
  <div class="section-heading"><div><span class="eyebrow"><?= $q !== '' ? 'YOUR SEARCH' : ($view === 'trending' ? 'POPULAR PICKS' : 'THE COLLECTION') ?></span><h2><?= $q !== '' ? 'Results for “' . h($q) . '”' : ($view === 'trending' ? 'Trending movies' : 'Explore movies') ?></h2></div><p class="result-count"><?= $total ?> <?= $total === 1 ? 'title' : 'titles' ?></p></div>
  <?php if ($movies): ?>
    <div class="movie-grid">
      <?php foreach ($movies as $movie): ?><?php include __DIR__ . '/includes/movie-card.php'; ?><?php endforeach; ?>
    </div>
    <?php if ($pages > 1): ?>
      <nav class="pagination" aria-label="Movie pages">
        <?php if ($page > 1): ?><a class="button button-secondary" href="<?= h(url('index.php?' . http_build_query(array_filter(['q' => $q ?: null, 'view' => $view === 'trending' ? 'trending' : null, 'page' => $page - 1])) . '#movies')) ?>">← Previous</a><?php endif; ?>
        <span>Page <?= $page ?> of <?= $pages ?></span>
        <?php if ($page < $pages): ?><a class="button button-secondary" href="<?= h(url('index.php?' . http_build_query(array_filter(['q' => $q ?: null, 'view' => $view === 'trending' ? 'trending' : null, 'page' => $page + 1])) . '#movies')) ?>">Next →</a><?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php else: ?>
    <div class="empty-state"><span class="empty-icon">⌕</span><h3>No titles found</h3><p>Try another title, genre, or cast member.</p><a class="button button-secondary" href="<?= h(url('index.php#movies')) ?>">Browse all movies</a></div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
