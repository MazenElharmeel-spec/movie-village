<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_admin();
$movies = db()->query('SELECT id, title, poster_path, release_year, published, trending, updated_at FROM movies ORDER BY updated_at DESC, id DESC')->fetchAll();
$publishedCount = 0;
foreach ($movies as $item) $publishedCount += (int) $item['published'];
$pageTitle = 'Movie manager';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="admin-page">
  <div class="admin-welcome"><div><span class="eyebrow">SITE MANAGEMENT</span><h1>Movie manager</h1><p><?= count($movies) ?> titles · <?= $publishedCount ?> published</p></div><a class="button button-primary" href="<?= h(url('admin/movie-form.php')) ?>">＋ Add movie</a></div>
  <div class="admin-tips"><strong>Adding a title is simple:</strong> enter its details, pick a poster, and save. Movies Village assigns its ID and builds its page automatically.</div>
  <?php if ($movies): ?><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Movie</th><th>Status</th><th>Updated</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ($movies as $movie): ?><tr>
      <td><div class="admin-movie"><img src="<?= h(asset_url($movie['poster_path'])) ?>" alt=""><div><strong><?= h($movie['title']) ?></strong><small>ID <?= (int) $movie['id'] ?><?= $movie['release_year'] ? ' · ' . (int) $movie['release_year'] : '' ?></small></div></div></td>
      <td><span class="status-pill<?= $movie['published'] ? ' status-live' : ' status-draft' ?>"><?= $movie['published'] ? 'Published' : 'Draft' ?></span><?= $movie['trending'] ? ' <span class="status-pill">Trending</span>' : '' ?></td>
      <td><?= h(date('M j, Y', strtotime($movie['updated_at']))) ?></td>
      <td class="admin-actions"><a class="button button-small button-secondary" href="<?= h(url('admin/movie-form.php?id=' . (int) $movie['id'])) ?>">Edit</a><a class="button button-small button-secondary" href="<?= h(url(movie_path((int) $movie['id']))) ?>"><?= $movie['published'] ? 'View' : 'Preview' ?></a>
        <form method="post" action="<?= h(url('admin/delete.php')) ?>" class="inline-form" onsubmit="return confirm('Delete this movie? This also removes its favourites.');"><?php csrf_field(); ?><input type="hidden" name="movie_id" value="<?= (int) $movie['id'] ?>"><button class="button button-small button-danger" type="submit">Delete</button></form>
      </td>
    </tr><?php endforeach; ?>
  </tbody></table></div><?php else: ?><div class="empty-state"><h2>No movies yet</h2><p>Add your first movie to start the catalog.</p><a class="button button-primary" href="<?= h(url('admin/movie-form.php')) ?>">Add a movie</a></div><?php endif; ?>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
