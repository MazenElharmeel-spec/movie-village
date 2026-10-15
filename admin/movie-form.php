<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_admin();

$pdo = db();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$movie = null;
if ($id) {
    $find = $pdo->prepare('SELECT * FROM movies WHERE id = ?');
    $find->execute([$id]);
    $movie = $find->fetch() ?: null;
    if (!$movie) {
        flash('That movie could not be found.', 'error');
        redirect('admin/index.php');
    }
}
$errors = [];
$values = $movie ?: ['title' => '', 'genre' => '', 'release_year' => '', 'rating' => '', 'synopsis' => '', 'cast_names' => '', 'poster_path' => '', 'backdrop_path' => '', 'video_path' => '', 'trailer_url' => '', 'featured' => 0, 'trending' => 0, 'published' => 1];
$storedCast = [];
if ($movie) {
    $findCast = $pdo->prepare('SELECT id, name, photo_path FROM movie_cast WHERE movie_id = ? ORDER BY position, id');
    $findCast->execute([(int) $movie['id']]);
    $storedCast = $findCast->fetchAll();
}
$castMembers = $storedCast;
if (!$castMembers && trim((string) $values['cast_names']) !== '') {
    $castMembers = array_map(static fn(string $name): array => ['id' => null, 'name' => $name, 'photo_path' => null], array_values(array_filter(array_map('trim', explode(',', (string) $values['cast_names'])))));
}
if (!$castMembers) $castMembers = [['id' => null, 'name' => '', 'photo_path' => null]];
$newFiles = [];
$castDrafts = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $values['title'] = trim((string) ($_POST['title'] ?? ''));
    $values['genre'] = trim((string) ($_POST['genre'] ?? ''));
    $values['release_year'] = trim((string) ($_POST['release_year'] ?? ''));
    $values['rating'] = trim((string) ($_POST['rating'] ?? ''));
    $values['synopsis'] = trim((string) ($_POST['synopsis'] ?? ''));
    $values['trailer_url'] = trim((string) ($_POST['trailer_url'] ?? ''));
    $values['featured'] = isset($_POST['featured']) ? 1 : 0;
    $values['trending'] = isset($_POST['trending']) ? 1 : 0;
    $values['published'] = isset($_POST['published']) ? 1 : 0;

    if ($values['title'] === '' || strlen($values['title']) > 180) $errors[] = 'Enter a title up to 180 characters.';
    if ($values['genre'] === '') $values['genre'] = 'Uncategorised';
    if (strlen($values['genre']) > 120) $errors[] = 'Keep the genre under 120 characters.';
    $year = $values['release_year'] === '' ? null : filter_var($values['release_year'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1880, 'max_range' => 2200]]);
    if ($values['release_year'] !== '' && $year === false) $errors[] = 'Enter a valid year between 1880 and 2200.';
    $rating = $values['rating'] === '' ? null : filter_var($values['rating'], FILTER_VALIDATE_FLOAT);
    if ($values['rating'] !== '' && ($rating === false || $rating < 0 || $rating > 10)) $errors[] = 'Rating must be a number from 0 to 10.';
    if (strlen($values['synopsis']) > 12000) $errors[] = 'Keep the synopsis under 12,000 characters.';
    $postedCast = $_POST['cast_members'] ?? [];
    if (!is_array($postedCast)) $postedCast = [];
    if (count($postedCast) > 30) $errors[] = 'Add no more than 30 cast members.';
    $knownCast = [];
    foreach ($storedCast as $storedPerson) $knownCast[(int) $storedPerson['id']] = $storedPerson;
    $castMembers = [];
    $castFiles = $_FILES['cast_photos'] ?? [];
    foreach (array_slice($postedCast, 0, 30, true) as $index => $person) {
        if (!is_array($person)) continue;
        $rawId = trim((string) ($person['id'] ?? ''));
        $castId = $rawId !== '' && ctype_digit($rawId) ? (int) $rawId : null;
        $existingPerson = $castId ? ($knownCast[$castId] ?? null) : null;
        if ($castId && !$existingPerson) {
            $errors[] = 'One of the cast entries is no longer available. Reload the form and try again.';
            continue;
        }
        $name = trim((string) ($person['name'] ?? ''));
        $removePhoto = isset($person['remove_photo']);
        $upload = [];
        foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $key) $upload[$key] = $castFiles[$key][$index] ?? ($key === 'error' ? UPLOAD_ERR_NO_FILE : '');
        $photoProvided = (int) $upload['error'] !== UPLOAD_ERR_NO_FILE;
        if ($photoProvided && (int) $upload['error'] !== UPLOAD_ERR_OK) $errors[] = 'A cast portrait did not finish uploading. Try a smaller image.';
        if ($name === '' && !$photoProvided) continue;
        if ($name === '' || strlen($name) > 150) $errors[] = 'Enter a cast member name up to 150 characters.';
        $photoPath = $removePhoto ? null : ($existingPerson['photo_path'] ?? null);
        $castMembers[] = ['id' => $castId, 'name' => $name, 'photo_path' => $photoPath];
        $castDrafts[] = ['id' => $castId, 'name' => $name, 'photo_path' => $photoPath, 'upload' => $photoProvided ? $upload : null];
    }
    $values['cast_names'] = implode(', ', array_column($castDrafts, 'name'));
    if ($values['trailer_url'] !== '' && (!filter_var($values['trailer_url'], FILTER_VALIDATE_URL) || strtolower((string) parse_url($values['trailer_url'], PHP_URL_SCHEME)) !== 'https')) $errors[] = 'Trailer link must be a valid HTTPS URL.';
    $videoUrl = trim((string) ($_POST['video_url'] ?? ''));
    $uploadedVideo = isset($_FILES['video']) && ($_FILES['video']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if ($videoUrl !== '' && (!filter_var($videoUrl, FILTER_VALIDATE_URL) || strtolower((string) parse_url($videoUrl, PHP_URL_SCHEME)) !== 'https')) $errors[] = 'Video link must be a valid HTTPS URL.';
    if ($videoUrl !== '' && $uploadedVideo && (!$movie || $videoUrl !== ($movie['video_path'] ?? ''))) $errors[] = 'Choose a video file or paste a video URL, not both.';
    $posterProvided = isset($_FILES['poster']) && ($_FILES['poster']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if (!$movie && !$posterProvided) $errors[] = 'Choose a poster image for the movie.';

    if (!$castMembers) $castMembers = [['id' => null, 'name' => '', 'photo_path' => null]];

    if (!$errors) {
        try {
            if ($posterProvided) {
                $path = store_upload($_FILES['poster'], 'poster');
                if ($path) $newFiles[] = $path;
                $values['poster_path'] = $path ?: $values['poster_path'];
            }
            if (isset($_FILES['backdrop']) && ($_FILES['backdrop']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $path = store_upload($_FILES['backdrop'], 'poster');
                if ($path) $newFiles[] = $path;
                $values['backdrop_path'] = $path ?: $values['backdrop_path'];
            } elseif ($movie && $posterProvided && $movie['backdrop_path'] === $movie['poster_path']) {
                $values['backdrop_path'] = $values['poster_path'];
            }
            if ($uploadedVideo) {
                $path = store_upload($_FILES['video'], 'video');
                if ($path) $newFiles[] = $path;
                $values['video_path'] = $path;
            } elseif (isset($_POST['remove_video'])) {
                $values['video_path'] = null;
            } elseif ($videoUrl !== '') {
                $values['video_path'] = $videoUrl;
            }
            if (!$movie && !$values['backdrop_path']) $values['backdrop_path'] = $values['poster_path'];

            foreach ($castDrafts as &$castDraft) {
                if ($castDraft['upload']) {
                    $path = store_upload($castDraft['upload'], 'cast');
                    if ($path) $newFiles[] = $path;
                    $castDraft['photo_path'] = $path ?: $castDraft['photo_path'];
                }
            }
            unset($castDraft);

            $data = [
                $values['title'], $values['genre'], $year === false ? null : $year,
                $rating === false ? null : $rating, $values['synopsis'], $values['cast_names'],
                $values['poster_path'], $values['backdrop_path'] ?: null, $values['video_path'] ?: null,
                $values['trailer_url'] ?: null, $values['featured'], $values['trending'], $values['published'],
            ];
            $removedPaths = [];
            $pdo->beginTransaction();
            if ($movie) {
                $data[] = (int) $movie['id'];
                $save = $pdo->prepare('UPDATE movies SET title=?, genre=?, release_year=?, rating=?, synopsis=?, cast_names=?, poster_path=?, backdrop_path=?, video_path=?, trailer_url=?, featured=?, trending=?, published=? WHERE id=?');
                $save->execute($data);
                foreach (['poster_path', 'backdrop_path', 'video_path'] as $column) {
                    if ($movie[$column] !== ($values[$column] ?: null)) $removedPaths[] = $movie[$column];
                }
                $savedMovieId = (int) $movie['id'];
            } else {
                $save = $pdo->prepare('INSERT INTO movies (title, genre, release_year, rating, synopsis, cast_names, poster_path, backdrop_path, video_path, trailer_url, featured, trending, published) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $save->execute($data);
                $savedMovieId = (int) $pdo->lastInsertId();
            }
            $oldCastPaths = array_column($storedCast, 'photo_path');
            $clearCast = $pdo->prepare('DELETE FROM movie_cast WHERE movie_id = ?');
            $clearCast->execute([$savedMovieId]);
            $saveCast = $pdo->prepare('INSERT INTO movie_cast (movie_id, name, photo_path, position) VALUES (?, ?, ?, ?)');
            foreach ($castDrafts as $position => $castDraft) {
                $saveCast->execute([$savedMovieId, $castDraft['name'], $castDraft['photo_path'], $position]);
            }
            $pdo->commit();
            $currentCastPaths = array_column($castDrafts, 'photo_path');
            foreach ($oldCastPaths as $path) {
                if ($path && !in_array($path, $currentCastPaths, true)) $removedPaths[] = $path;
            }
            foreach ($removedPaths as $path) remove_owned_upload($path);
            flash($movie ? 'Movie details saved.' : 'Movie added. Its page is ready.');
            redirect('admin/index.php');
        } catch (RuntimeException $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $exception->getMessage();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'The movie could not be saved. Check the database settings and try again.';
        }
        foreach ($newFiles as $path) remove_owned_upload($path);
    }
}
$pageTitle = $movie ? 'Edit ' . $movie['title'] : 'Add a movie';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="admin-page form-page">
  <a class="back-link" href="<?= h(url('admin/index.php')) ?>">← Back to movie manager</a>
  <div class="admin-welcome"><div><span class="eyebrow">MOVIE DETAILS</span><h1><?= $movie ? 'Edit movie' : 'Add a movie' ?></h1><p><?= $movie ? 'Update the details, artwork, or availability.' : 'Add the details and artwork. We’ll build its movie page for you.' ?></p></div></div>
  <?php if ($errors): ?><div class="notice notice-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data" class="movie-editor"><?php csrf_field(); ?>
    <div class="editor-grid">
      <label class="field-wide">Movie title <span class="required">*</span><input type="text" name="title" required maxlength="180" placeholder="e.g. The Grand Adventure" value="<?= h($values['title']) ?>"></label>
      <label>Genre<input type="text" name="genre" maxlength="120" placeholder="Action · Adventure" value="<?= h($values['genre']) ?>"></label>
      <label>Release year<input type="number" name="release_year" min="1880" max="2200" placeholder="2026" value="<?= h($values['release_year']) ?>"></label>
      <label>Rating <span class="muted">(out of 10)</span><input type="number" name="rating" min="0" max="10" step="0.1" placeholder="8.5" value="<?= h($values['rating']) ?>"></label>
      <label class="field-wide">Synopsis<textarea name="synopsis" rows="5" maxlength="12000" placeholder="What is this movie about?"><?= h($values['synopsis']) ?></textarea></label>
      <section class="cast-editor-section field-wide" aria-labelledby="cast-editor-title">
        <div class="cast-editor-heading"><div><h2 id="cast-editor-title">Cast members</h2><p>Add each actor’s name and an optional portrait. Portraits appear on the movie page.</p></div><button class="button button-secondary button-small" type="button" data-add-cast>＋ Add person</button></div>
        <div class="cast-editor-list" data-cast-list data-next-index="<?= count($castMembers) ?>">
          <?php foreach ($castMembers as $index => $person): ?><div class="cast-editor-row" data-cast-row>
            <input type="hidden" name="cast_members[<?= (int) $index ?>][id]" value="<?= h($person['id'] ?? '') ?>">
            <label>Cast member name<input type="text" name="cast_members[<?= (int) $index ?>][name]" maxlength="150" placeholder="e.g. Dwayne Johnson" value="<?= h($person['name'] ?? '') ?>"></label>
            <label>Portrait photo<input type="file" name="cast_photos[<?= (int) $index ?>]" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG, or WebP · up to 8 MB</small></label>
            <?php if (!empty($person['photo_path'])): ?><div class="cast-editor-current"><img src="<?= h(asset_url($person['photo_path'])) ?>" alt="Current portrait of <?= h($person['name']) ?>"><label class="check-field"><input type="checkbox" name="cast_members[<?= (int) $index ?>][remove_photo]" value="1"> Remove portrait</label></div><?php else: ?><img class="cast-editor-preview" data-cast-preview alt="Portrait preview" hidden><?php endif; ?>
            <button class="button button-secondary button-small cast-remove" type="button" data-remove-cast>Remove person</button>
          </div><?php endforeach; ?>
        </div>
        <template data-cast-template><div class="cast-editor-row" data-cast-row>
          <input type="hidden" name="cast_members[__INDEX__][id]" value="">
          <label>Cast member name<input type="text" name="cast_members[__INDEX__][name]" maxlength="150" placeholder="e.g. Dwayne Johnson"></label>
          <label>Portrait photo<input type="file" name="cast_photos[__INDEX__]" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG, or WebP · up to 8 MB</small></label>
          <img class="cast-editor-preview" data-cast-preview alt="Portrait preview" hidden>
          <button class="button button-secondary button-small cast-remove" type="button" data-remove-cast>Remove person</button>
        </div></template>
      </section>
      <label>Poster image <span class="<?= $movie ? 'muted' : 'required' ?>"><?= $movie ? '(optional to replace)' : '(required)' ?></span><input type="file" name="poster" accept="image/jpeg,image/png,image/webp" <?= $movie ? '' : 'required' ?>><small>JPG, PNG, or WebP · up to 8 MB</small><?php if ($movie): ?><img class="current-poster" src="<?= h(asset_url($movie['poster_path'])) ?>" alt="Current poster"><?php endif; ?></label>
      <label>Wide backdrop <span class="muted">(optional)</span><input type="file" name="backdrop" accept="image/jpeg,image/png,image/webp"><small>Same image types as the poster.</small><?php if ($movie && $movie['backdrop_path']): ?><img class="current-poster current-backdrop" src="<?= h(asset_url($movie['backdrop_path'])) ?>" alt="Current backdrop"><?php endif; ?></label>
      <label class="field-wide">Movie video file <span class="muted">(optional)</span><input type="file" name="video" accept="video/mp4,video/webm,video/quicktime"><small>MP4, WebM, or MOV. The server’s PHP upload limits also apply.</small></label>
      <label class="field-wide">Or direct video URL <span class="muted">(optional)</span><input type="url" name="video_url" inputmode="url" placeholder="https://example.com/movie.mp4" value="<?= h($movie && filter_var($movie['video_path'], FILTER_VALIDATE_URL) ? $movie['video_path'] : '') ?>"><small>Use a direct video file URL that browsers can play, not a watch-page link.</small></label>
      <?php if ($movie && $movie['video_path']): ?><label class="check-field field-wide"><input type="checkbox" name="remove_video" value="1"> Remove the current video when saving</label><?php endif; ?>
      <label class="field-wide">Trailer link <span class="muted">(optional)</span><input type="url" name="trailer_url" inputmode="url" placeholder="https://…" value="<?= h($values['trailer_url']) ?>"><small>This opens the trailer in a new tab.</small></label>
    </div>
    <fieldset class="publish-options"><legend>Collection options</legend>
      <label class="check-field"><input type="checkbox" name="published" value="1" <?= $values['published'] ? 'checked' : '' ?>> Published and visible to visitors</label>
      <label class="check-field"><input type="checkbox" name="featured" value="1" <?= $values['featured'] ? 'checked' : '' ?>> Feature on the home page</label>
      <label class="check-field"><input type="checkbox" name="trending" value="1" <?= $values['trending'] ? 'checked' : '' ?>> Show in Trending</label>
    </fieldset>
    <div class="form-actions"><a class="button button-secondary" href="<?= h(url('admin/index.php')) ?>">Cancel</a><button class="button button-primary" type="submit"><?= $movie ? 'Save changes' : 'Add movie' ?></button></div>
  </form>
</section>
<script>
(() => {
  const list = document.querySelector('[data-cast-list]');
  const template = document.querySelector('[data-cast-template]');
  if (!list || !template) return;
  let nextIndex = Number(list.dataset.nextIndex || 0);
  document.querySelector('[data-add-cast]')?.addEventListener('click', () => {
    const fragment = template.content.cloneNode(true);
    fragment.querySelectorAll('[name]').forEach((field) => { field.name = field.name.replaceAll('__INDEX__', String(nextIndex)); });
    nextIndex += 1;
    list.append(fragment);
  });
  list.addEventListener('click', (event) => {
    if (event.target.closest('[data-remove-cast]')) event.target.closest('[data-cast-row]')?.remove();
  });
  list.addEventListener('change', (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || input.type !== 'file' || !input.files?.[0]) return;
    const row = input.closest('[data-cast-row]');
    const preview = row?.querySelector('[data-cast-preview]');
    if (!preview) return;
    preview.src = URL.createObjectURL(input.files[0]);
    preview.hidden = false;
  });
})();
</script>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
