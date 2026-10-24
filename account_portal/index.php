<?php
require_once __DIR__ . '/includes/functions.php';
if (!empty($_SESSION['user_id'])) redirect('/dashboard.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $_SESSION['fails'] = $_SESSION['fails'] ?? 0;
    if (($_SESSION['locked_until'] ?? 0) > time()) {
        flash('error', 'Too many failed attempts. Wait a minute and try again.');
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $st = $conn->prepare('SELECT user_id, username, password_hash FROM users WHERE username = ?');
        $st->bind_param('s', $username);
        $st->execute();
        $u = $st->get_result()->fetch_assoc();
        if ($u && password_verify($password, $u['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']  = (int)$u['user_id'];
            $_SESSION['username'] = $u['username'];
            $_SESSION['fails'] = 0;
            redirect('/dashboard.php');
        }
        $_SESSION['fails']++;
        if ($_SESSION['fails'] >= 5) { $_SESSION['locked_until'] = time() + 60; $_SESSION['fails'] = 0; }
        flash('error', 'Wrong username or password.');
    }
    redirect('/index.php');
}

$pageTitle = 'Log in';
include __DIR__ . '/includes/header.php';
?>
<section class="glass card narrow">
    <h1>Log in</h1>
    <p class="muted">Keep records of your game accounts and sell them on the market.</p>
    <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <label>Username
            <input type="text" name="username" required maxlength="50" autofocus>
        </label>
        <label>Password
            <input type="password" name="password" required>
        </label>
        <button class="btn btn-block">Log in</button>
    </form>
    <p class="muted center">No account yet? <a href="<?= BASE_URL ?>/register.php">Register</a></p>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
