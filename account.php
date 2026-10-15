<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

if (current_user()) {
    redirect('index.php');
}
$mode = ($_GET['mode'] ?? 'signin') === 'signup' ? 'signup' : 'signin';
$next = safe_return_path($_GET['next'] ?? 'index.php');
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $mode = ($_POST['mode'] ?? '') === 'signup' ? 'signup' : 'signin';
    $next = safe_return_path($_POST['next'] ?? 'index.php');
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if ($mode === 'signup') {
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '' || strlen($name) > 100) $errors[] = 'Enter a name up to 100 characters.';
        if (strlen($password) < 10) $errors[] = 'Use a password with at least 10 characters.';
        if (!$errors) {
            try {
                $create = db()->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, \'member\')');
                $create->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                session_regenerate_id(true);
                $_SESSION['user'] = ['id' => (int) db()->lastInsertId(), 'name' => $name, 'email' => $email, 'role' => 'member'];
                flash('Your account is ready. Welcome to Movies Village.');
                redirect($next);
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') $errors[] = 'An account already exists for that email.';
                else $errors[] = 'The account could not be created. Try again in a moment.';
            }
        }
    } elseif (!$errors) {
        $find = db()->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1');
        $find->execute([$email]);
        $account = $find->fetch();
        if (!$account || !password_verify($password, $account['password_hash'])) {
            $errors[] = 'Email or password is incorrect.';
        } else {
            if (password_needs_rehash($account['password_hash'], PASSWORD_DEFAULT)) {
                $rehash = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $rehash->execute([password_hash($password, PASSWORD_DEFAULT), (int) $account['id']]);
            }
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int) $account['id'], 'name' => $account['name'], 'email' => $account['email'], 'role' => $account['role']];
            flash('Welcome back, ' . $account['name'] . '.');
            redirect($next);
        }
    }
}
$pageTitle = $mode === 'signup' ? 'Create an account' : 'Sign in';
require __DIR__ . '/includes/header.php';
?>
<section class="account-page">
  <div class="account-art" aria-label="Movie poster collage">
    <img src="<?= h(asset_url('login-image.png')) ?>" alt="A collage of movie posters" fetchpriority="high">
    <div><span class="eyebrow">MOVIES VILLAGE</span><h2>Your next favourite<br>is waiting.</h2><p>Discover films. Keep the ones you love close.</p></div>
  </div>
  <div class="account-panel">
    <a class="back-link" href="<?= h(url('index.php')) ?>">← Back to movies</a>
    <span class="eyebrow">YOUR MOVIES VILLAGE</span>
    <h1><?= $mode === 'signup' ? 'Create your account' : 'Welcome back' ?></h1>
    <p class="muted"><?= $mode === 'signup' ? 'Save favourites and keep your picks in one place.' : 'Sign in to see your saved movies.' ?></p>
    <?php if ($errors): ?><div class="notice notice-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" action="<?= h(url('account.php')) ?>" class="stack-form">
      <?php csrf_field(); ?><input type="hidden" name="mode" value="<?= h($mode) ?>"><input type="hidden" name="next" value="<?= h($next) ?>">
      <?php if ($mode === 'signup'): ?><label>Your name<input name="name" autocomplete="name" required maxlength="100" value="<?= h($_POST['name'] ?? '') ?>"></label><?php endif; ?>
      <label>Email address<input type="email" name="email" autocomplete="email" required maxlength="190" value="<?= h($_POST['email'] ?? '') ?>"></label>
      <label>Password<input type="password" name="password" autocomplete="<?= $mode === 'signup' ? 'new-password' : 'current-password' ?>" required minlength="<?= $mode === 'signup' ? '10' : '1' ?>"></label>
      <button class="button button-primary button-wide" type="submit"><?= $mode === 'signup' ? 'Create account' : 'Sign in' ?></button>
    </form>
    <p class="switch-account"><?= $mode === 'signup' ? 'Already have an account?' : 'New here?' ?> <a href="<?= h(url('account.php?mode=' . ($mode === 'signup' ? 'signin' : 'signup') . '&next=' . rawurlencode($next))) ?>"><?= $mode === 'signup' ? 'Sign in' : 'Create an account' ?></a></p>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
