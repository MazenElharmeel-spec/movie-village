<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';

$pdo = db();
$adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
if ($adminCount > 0) {
    flash('Administrator setup is already complete. Sign in to manage the site.', 'info');
    redirect('account.php');
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    if ($name === '' || strlen($name) > 100) $errors[] = 'Enter a name up to 100 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (strlen($password) < 12) $errors[] = 'Use a password with at least 12 characters.';
    if (!$errors && (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn() > 0) {
        $errors[] = 'Administrator setup has already been completed.';
    }
    if (!$errors) {
        try {
            $create = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
            $create->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int) $pdo->lastInsertId(), 'name' => $name, 'email' => $email, 'role' => 'admin'];
            flash('Administrator account created. You can now add movies.');
            redirect('admin/index.php');
        } catch (PDOException $exception) {
            $errors[] = $exception->getCode() === '23000' ? 'That email address is already in use.' : 'The account could not be created.';
        }
    }
}
$pageTitle = 'Create administrator';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="account-page"><div class="account-panel">
  <a class="back-link" href="<?= h(url('index.php')) ?>">← Back to Movies Village</a><span class="eyebrow">ONE-TIME SETUP</span><h1>Create the site administrator</h1>
  <p class="muted">This page closes after the first administrator is created.</p>
  <?php if ($errors): ?><div class="notice notice-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <form method="post" class="stack-form"><?php csrf_field(); ?>
    <label>Your name<input name="name" required maxlength="100" autocomplete="name" value="<?= h($_POST['name'] ?? '') ?>"></label>
    <label>Administrator email<input type="email" name="email" required maxlength="190" autocomplete="email" value="<?= h($_POST['email'] ?? '') ?>"></label>
    <label>Password<input type="password" name="password" required minlength="12" autocomplete="new-password"><small>Use at least 12 characters.</small></label>
    <button class="button button-primary button-wide" type="submit">Create administrator</button>
  </form>
</div></section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
