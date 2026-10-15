<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
verify_csrf();
$movieId = filter_input(INPUT_POST, 'movie_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$returnPath = safe_return_path($_POST['return_to'] ?? 'index.php');
$user = require_login($returnPath);
if ($movieId) {
    $pdo = db();
    $exists = $pdo->prepare('SELECT id FROM movies WHERE id = ? AND published = 1');
    $exists->execute([$movieId]);
    if ($exists->fetchColumn()) {
        $check = $pdo->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND movie_id = ?');
        $check->execute([(int) $user['id'], $movieId]);
        if ($check->fetchColumn()) {
            $change = $pdo->prepare('DELETE FROM favorites WHERE user_id = ? AND movie_id = ?');
            $change->execute([(int) $user['id'], $movieId]);
            flash('Removed from your favourites.');
        } else {
            $change = $pdo->prepare('INSERT IGNORE INTO favorites (user_id, movie_id) VALUES (?, ?)');
            $change->execute([(int) $user['id'], $movieId]);
            flash('Added to your favourites.');
        }
    }
}
redirect($returnPath);
