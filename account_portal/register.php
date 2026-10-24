<?php
require_once __DIR__ . '/includes/functions.php';
if (!empty($_SESSION['user_id'])) redirect('/dashboard.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';
    $err = null;

    if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) $err = 'Username must be 3-50 letters, numbers, or underscores.';
    elseif (strlen($password) < 8)                       $err = 'Password must be at least 8 characters.';
    elseif ($password !== $confirm)                      $err = 'Passwords do not match.';
    else {
        $st = $conn->prepare('SELECT 1 FROM users WHERE username = ?');
        $st->bind_param('s', $username);
        $st->execute();
        if ($st->get_result()->num_rows) $err = 'That username is taken.';
    }

    if ($err) { flash('error', $err); }
    else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $st = $conn->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
        $st->bind_param('ss', $username, $hash);
        $st->execute();
        flash('success', 'Account created. You can log in now.');
        redirect('/index.php');
    }
    redirect('/register.php');
}

$pageTitle = 'Register';
include __DIR__ . '/includes/header.php';
?>
<section class="glass card narrow">
    <h1>Create account</h1>
    <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <label>Username
            <input type="text" name="username" required minlength="3" maxlength="50" pattern="[A-Za-z0-9_]+">
        </label>
        <label>Password
            <input type="password" name="password" required minlength="8">
        </label>
        <label>Confirm password
            <input type="password" name="confirm" required minlength="8">
        </label>
        <button class="btn btn-block">Register</button>
    </form>
    <p class="muted center">Already registered? <a href="<?= BASE_URL ?>/index.php">Log in</a></p>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
