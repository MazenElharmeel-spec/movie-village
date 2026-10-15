<?php
declare(strict_types=1);

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $isHttps,
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

define('APP_BASE_URL', rtrim((string) (getenv('APP_BASE_URL') ?: ''), '/'));
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('MAX_POSTER_BYTES', 8 * 1024 * 1024);
define('MAX_VIDEO_BYTES', 2 * 1024 * 1024 * 1024);

function db(): PDO
{
    static $connection;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'movies_village';
    $user = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    $connection = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $connection;
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return APP_BASE_URL . '/' . ltrim($path, '/');
}

function asset_url(string $path): string
{
    return url($path);
}

function redirect(string $path): never
{
    header('Location: ' . url($path), true, 303);
    exit;
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): void
{
    echo '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Your session expired. Go back and try again.');
    }
}

function flash(?string $message = null, string $type = 'success'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
        return null;
    }
    $current = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($current) ? $current : null;
}

function current_user(): ?array
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

function require_login(string $returnTo = 'index.php'): array
{
    $user = current_user();
    if (!$user) {
        flash('Sign in to save movies to your favourites.', 'info');
        redirect('account.php?next=' . rawurlencode(safe_return_path($returnTo)));
    }
    return $user;
}

function require_admin(): array
{
    $user = current_user();
    if (!$user) {
        flash('Please sign in with an administrator account.', 'error');
        redirect('account.php?next=' . rawurlencode('admin/index.php'));
    }
    if (($user['role'] ?? '') !== 'admin') {
        flash('Your account does not have administrator access.', 'error');
        redirect('index.php');
    }
    return $user;
}

function safe_return_path(mixed $value): string
{
    if (!is_string($value)) {
        return 'index.php';
    }
    if (in_array($value, ['index.php', 'favorites.php', 'admin/index.php'], true)) {
        return $value;
    }
    if (preg_match('/^index\.php(?:\?q=[A-Za-z0-9%._~-]{1,300})?(?:#movies)?$/', $value)) {
        return $value;
    }
    if (preg_match('/^movie\.php\?id=[1-9][0-9]*$/', $value)) {
        return $value;
    }
    return 'index.php';
}

function store_upload(array $file, string $kind): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        throw new RuntimeException('The upload did not finish. Check the file size and try again.');
    }

    $isImage = in_array($kind, ['poster', 'cast'], true);
    $mimeTypes = $isImage
        ? ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp']
        : ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov'];
    $limit = $isImage ? MAX_POSTER_BYTES : MAX_VIDEO_BYTES;
    if (($file['size'] ?? 0) < 1 || $file['size'] > $limit) {
        throw new RuntimeException($isImage ? 'Images must be 8 MB or smaller.' : 'The movie file is too large for this server.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($mimeTypes[$mime])) {
        throw new RuntimeException($isImage
            ? 'Use a JPG, PNG, or WebP image.'
            : 'Use an MP4, WebM, or MOV video.');
    }
    if ($isImage && @getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('That file could not be read as an image.');
    }

    $uploadFolder = $kind === 'cast' ? 'cast' : ($isImage ? 'posters' : 'movies');
    $folder = UPLOAD_DIR . '/' . $uploadFolder;
    if (!is_dir($folder) && !mkdir($folder, 0755, true) && !is_dir($folder)) {
        throw new RuntimeException('The uploads folder is not writable.');
    }
    $relativeFolder = 'uploads/' . $uploadFolder;
    $filename = bin2hex(random_bytes(18)) . '.' . $mimeTypes[$mime];
    if (!move_uploaded_file($file['tmp_name'], $folder . '/' . $filename)) {
        throw new RuntimeException('The file could not be saved. Check uploads folder permissions.');
    }
    return $relativeFolder . '/' . $filename;
}

function remove_owned_upload(?string $path): void
{
    if (!$path || !str_starts_with($path, 'uploads/')) {
        return;
    }
    $fullPath = realpath(__DIR__ . '/' . $path);
    $uploadRoot = realpath(UPLOAD_DIR);
    if ($fullPath && $uploadRoot && str_starts_with($fullPath, $uploadRoot . DIRECTORY_SEPARATOR) && is_file($fullPath)) {
        unlink($fullPath);
    }
}

function movie_path(int $id): string
{
    return 'movie.php?id=' . $id;
}
