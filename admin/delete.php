<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
verify_csrf();
$id = filter_input(INPUT_POST, 'movie_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    flash('Choose a valid movie to delete.', 'error');
    redirect('admin/index.php');
}
$pdo = db();
$query = $pdo->prepare('SELECT poster_path, backdrop_path, video_path FROM movies WHERE id = ?');
$query->execute([$id]);
$movie = $query->fetch();
if ($movie) {
    $delete = $pdo->prepare('DELETE FROM movies WHERE id = ?');
    $delete->execute([$id]);
    foreach (['poster_path', 'backdrop_path', 'video_path'] as $column) remove_owned_upload($movie[$column]);
    flash('Movie deleted.');
} else {
    flash('That movie was already removed.', 'info');
}
redirect('admin/index.php');
