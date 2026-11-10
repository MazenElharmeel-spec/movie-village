<?php
declare(strict_types=1);

$baseUrl = rtrim(getenv("MOVIES_VILLAGE_URL") ?: "http://127.0.0.1", "/");
$memberCookies = tempnam(sys_get_temp_dir(), "mv-member-");
$adminCookies = tempnam(sys_get_temp_dir(), "mv-admin-");
$checks = 0;

function request(string $baseUrl, string $path, string $cookieFile, ?array $fields = null): array
{
    $handle = curl_init($baseUrl . $path);
    if ($handle === false) {
        throw new RuntimeException("Could not initialize the HTTP client.");
    }
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 20,
    ]);
    if ($fields !== null) {
        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_POSTFIELDS, $fields);
    }
    $body = curl_exec($handle);
    if ($body === false) {
        $message = curl_error($handle);
        curl_close($handle);
        throw new RuntimeException("Request failed: " . $message);
    }
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);
    return [$status, (string) $body];
}

function check(bool $passed, string $description): void
{
    global $checks;
    if (!$passed) {
        throw new RuntimeException("FAILED: " . $description);
    }
    $checks++;
}

function csrf(string $html): string
{
    if (!preg_match('/name="csrf_token" value="([^"]+)"/', $html, $match)) {
        throw new RuntimeException("The form did not contain a CSRF token.");
    }
    return $match[1];
}

try {
    [$status, $body] = request($baseUrl, "/", $memberCookies);
    check($status === 200 && str_contains($body, "index.php"), "root page opens the PHP catalog");

    [$status, $body] = request($baseUrl, "/index.php", $memberCookies);
    check($status === 200 && str_contains($body, "Jumanji: Welcome to the Jungle"), "catalog renders seeded movies");

    [$status, $body] = request($baseUrl, "/index.php?q=Oppenheimer", $memberCookies);
    check($status === 200 && str_contains($body, "Oppenheimer"), "search finds a matching title");

    [$status, $body] = request($baseUrl, "/index.php?q=%27%20OR%201%3D1--", $memberCookies);
    check($status === 200 && !str_contains($body, "Fatal error"), "search handles SQL-like input safely");

    [$status, $body] = request($baseUrl, "/movie.php?id=1", $memberCookies);
    check($status === 200 && str_contains($body, "Jumanji: Welcome to the Jungle") && str_contains($body, "Dwayne Johnson"), "movie details and cast render");

    [$status] = request($baseUrl, "/movie.php?id=9999999", $memberCookies);
    check($status === 404, "unknown movie returns 404");

    [$status] = request($baseUrl, "/admin/index.php", $memberCookies);
    check($status === 303, "anonymous admin access redirects");

    [$status, $body] = request($baseUrl, "/account.php?mode=signup", $memberCookies);
    $memberToken = csrf($body);
    $email = "mv-smoke-" . bin2hex(random_bytes(5)) . "@example.test";
    [$status] = request($baseUrl, "/account.php", $memberCookies, [
        "csrf_token" => $memberToken,
        "mode" => "signup",
        "next" => "index.php",
        "name" => "Movie Test User",
        "email" => $email,
        "password" => "SmokeTestPassword123!",
    ]);
    check($status === 303, "member signup succeeds");

    [$status] = request($baseUrl, "/admin/index.php", $memberCookies);
    check($status === 303, "member cannot enter the admin area");

    [$status, $body] = request($baseUrl, "/favorites.php", $memberCookies);
    check($status === 200 && str_contains($body, "Your list is ready"), "member favorites page loads");

    [$status] = request($baseUrl, "/favorite.php", $memberCookies, [
        "csrf_token" => $memberToken,
        "movie_id" => "1",
        "return_to" => "index.php",
    ]);
    check($status === 303, "member can add a favorite");
    [, $body] = request($baseUrl, "/favorites.php", $memberCookies);
    check(str_contains($body, "Jumanji: Welcome to the Jungle"), "saved favorite appears in the list");

    [$status] = request($baseUrl, "/favorite.php", $memberCookies, [
        "csrf_token" => $memberToken,
        "movie_id" => "1",
        "return_to" => "index.php",
    ]);
    [, $body] = request($baseUrl, "/favorites.php", $memberCookies);
    check($status === 303 && str_contains($body, "Your list is ready"), "member can remove a favorite");

    [$status] = request($baseUrl, "/favorite.php", $memberCookies, [
        "movie_id" => "1",
        "return_to" => "index.php",
    ]);
    check($status === 403, "invalid CSRF request is forbidden");

    [$status, $body] = request($baseUrl, "/admin/setup.php", $adminCookies);
    $adminToken = csrf($body);
    [$status] = request($baseUrl, "/admin/setup.php", $adminCookies, [
        "csrf_token" => $adminToken,
        "name" => "Smoke Admin",
        "email" => "mv-admin-" . bin2hex(random_bytes(5)) . "@example.test",
        "password" => "SmokeAdminPassword123!",
    ]);
    check($status === 303, "first administrator can be created");

    [$status, $body] = request($baseUrl, "/admin/index.php", $adminCookies);
    check($status === 200 && str_contains($body, "Movie manager"), "admin manager renders");

    [$status, $body] = request($baseUrl, "/admin/movie-form.php", $adminCookies);
    $adminToken = csrf($body);
    $posterPath = __DIR__ . "/../img/movie-1.jpg";
    $poster = new CURLFile($posterPath, "image/jpeg", "smoke.jpg");
    $movieTitle = "Backend Smoke " . bin2hex(random_bytes(4));
    [$status] = request($baseUrl, "/admin/movie-form.php", $adminCookies, [
        "csrf_token" => $adminToken,
        "title" => $movieTitle,
        "genre" => "Test",
        "release_year" => "2026",
        "rating" => "8.2",
        "synopsis" => "A disposable backend integration test title.",
        "cast_members[0][name]" => "Test Actor",
        "published" => "1",
        "featured" => "0",
        "trending" => "0",
        "video_url" => "",
        "trailer_url" => "",
        "poster" => $poster,
    ]);
    check($status === 303, "admin can add a movie with a poster upload");

    [$status, $body] = request($baseUrl, "/index.php?q=" . rawurlencode($movieTitle), $adminCookies);
    check($status === 200 && str_contains($body, $movieTitle), "new movie is searchable");
    if (!preg_match('/href="[^"]*movie\.php\?id=(\d+)"/', $body, $match)) {
        throw new RuntimeException("The saved movie page link was missing.");
    }
    $movieId = $match[1];

    [$status, $body] = request($baseUrl, "/movie.php?id=" . $movieId, $adminCookies);
    check($status === 200 && str_contains($body, $movieTitle) && str_contains($body, "Test Actor"), "new movie page and cast render");

    [$status, $body] = request($baseUrl, "/admin/index.php", $adminCookies);
    $adminToken = csrf($body);
    [$status] = request($baseUrl, "/admin/delete.php", $adminCookies, [
        "csrf_token" => $adminToken,
        "movie_id" => $movieId,
    ]);
    check($status === 303, "admin can delete the test movie");
    [$status] = request($baseUrl, "/movie.php?id=" . $movieId, $adminCookies);
    check($status === 404, "deleted movie is no longer available");

    echo "PASS: {$checks} Movies Village backend smoke checks.\n";
} finally {
    @unlink($memberCookies);
    @unlink($adminCookies);
}
