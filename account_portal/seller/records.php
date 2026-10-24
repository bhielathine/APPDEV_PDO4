<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$uid = current_user_id();

/* ---------- actions: publish / unpublish / mark sold / reopen / delete ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['account_id'] ?? 0);

    // authorization: the record must belong to the logged-in seller
    $st = $conn->prepare('SELECT status FROM accounts WHERE account_id = ? AND seller_id = ?');
    $st->bind_param('ii', $id, $uid);
    $st->execute();
    $rec = $st->get_result()->fetch_assoc();

    if (!$rec) {
        flash('error', 'Record not found.');
    } elseif ($action === 'publish' && $rec['status'] === 'draft') {
        $st = $conn->prepare("UPDATE accounts SET status='available', trade_posted=NOW() WHERE account_id=? AND seller_id=?");
        $st->bind_param('ii', $id, $uid);
        $st->execute();
        flash('success', 'Published. The account is now on the market.');
    } elseif ($action === 'unpublish' && $rec['status'] === 'available') {
        $conn->begin_transaction();
        $st = $conn->prepare("UPDATE accounts SET status='draft', trade_posted=NULL WHERE account_id=? AND seller_id=?");
        $st->bind_param('ii', $id, $uid);
        $st->execute();
        $st = $conn->prepare("UPDATE offers SET status='declined' WHERE account_id=? AND status='pending'");
        $st->bind_param('i', $id);
        $st->execute();
        $conn->commit();
        flash('success', 'Removed from the market. Pending offers were declined.');
    } elseif ($action === 'mark_sold' && $rec['status'] === 'available') {
        $offerId = (int)($_POST['offer_id'] ?? 0);
        $st = $conn->prepare("SELECT 1 FROM offers WHERE offer_id=? AND account_id=? AND status='pending'");
        $st->bind_param('ii', $offerId, $id);
        $st->execute();
        if (!$st->get_result()->num_rows) {
            flash('error', 'You can only mark an account as sold after a buyer sends an offer.');
        } else {
            $conn->begin_transaction();
            $st = $conn->prepare("UPDATE offers SET status='accepted' WHERE offer_id=?");
            $st->bind_param('i', $offerId);
            $st->execute();
            $st = $conn->prepare("UPDATE offers SET status='declined' WHERE account_id=? AND status='pending'");
            $st->bind_param('i', $id);
            $st->execute();
            $st = $conn->prepare("UPDATE accounts SET status='sold' WHERE account_id=? AND seller_id=?");
            $st->bind_param('ii', $id, $uid);
            $st->execute();
            $conn->commit();
            flash('success', 'Marked as sold.');
        }
    } elseif ($action === 'reopen' && $rec['status'] === 'sold') {
        $conn->begin_transaction();
        $st = $conn->prepare("UPDATE offers SET status='declined' WHERE account_id=? AND status='accepted'");
        $st->bind_param('i', $id);
        $st->execute();
        $st = $conn->prepare("UPDATE accounts SET status='available' WHERE account_id=? AND seller_id=?");
        $st->bind_param('ii', $id, $uid);
        $st->execute();
        $conn->commit();
        flash('success', 'Marked as available again.');
    } elseif ($action === 'delete') {
        $st = $conn->prepare('DELETE FROM accounts WHERE account_id=? AND seller_id=?');
        $st->bind_param('ii', $id, $uid);
        $st->execute();
        flash('success', 'Record deleted.');
    } else {
        flash('error', 'That action is not available for this record.');
    }
    redirect('/seller/records.php');
}

/* ---------- load records (explicit columns, password is never listed) ---------- */
$st = $conn->prepare(
    'SELECT a.account_id, g.game_name, a.pseudonym, a.selling_price, a.account_level, a.rank_tier,
            a.status, a.trade_posted, a.last_updated
     FROM accounts a JOIN games g ON g.game_id = a.game_id
     WHERE a.seller_id = ? ORDER BY a.last_updated DESC'
);
$st->bind_param('i', $uid);
$st->execute();
$records = $st->get_result()->fetch_all(MYSQLI_ASSOC);

// pending + accepted offers for this seller's accounts
$st = $conn->prepare(
    "SELECT o.offer_id, o.account_id, o.status, u.username
     FROM offers o JOIN accounts a ON a.account_id = o.account_id JOIN users u ON u.user_id = o.buyer_id
     WHERE a.seller_id = ? AND o.status IN ('pending','accepted') ORDER BY o.created_at"
);
$st->bind_param('i', $uid);
$st->execute();
$offers = [];
foreach ($st->get_result() as $o) $offers[$o['account_id']][] = $o;

$pageTitle = 'My Records';
include __DIR__ . '/../includes/header.php';
?>
<div class="row between">
    <div>
        <h1>My records</h1>
        <p class="muted">Add as many accounts as you like, then publish the ones you want on the market.</p>
    </div>
    <a class="btn" href="<?= BASE_URL ?>/seller/record_form.php">Add record</a>
</div>

<?php if (!$records): ?>
    <div class="glass card center"><p>No records yet. Add your first account to start keeping track.</p></div>
<?php else: ?>
<div class="glass card table-wrap">
<table>
    <thead>
        <tr><th>Pseudonym</th><th>Game</th><th>Price</th><th>Level</th><th>Rank</th><th>Status</th><th>Offers</th><th>Last updated</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($records as $r):
        $meta = game_meta($r['game_name']);
        $list = $offers[$r['account_id']] ?? [];
        $pending = array_values(array_filter($list, fn($o) => $o['status'] === 'pending'));
        $accepted = array_values(array_filter($list, fn($o) => $o['status'] === 'accepted'));
    ?>
        <tr>
            <td><?= e($r['pseudonym']) ?></td>
            <td><?= e($r['game_name']) ?></td>
            <td><?= peso($r['selling_price']) ?></td>
            <td><?= e($meta['level_label'] ?? 'Level') ?> <?= (int)$r['account_level'] ?></td>
            <td><?= e($r['rank_tier'] ?? '-') ?></td>
            <td><span class="badge badge-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
            <td>
                <?php if ($r['status'] === 'sold' && $accepted): ?>
                    Sold to <?= e($accepted[0]['username']) ?>
                <?php elseif ($pending): ?>
                    <?= count($pending) ?> pending
                <?php else: ?>-<?php endif; ?>
            </td>
            <td><?= e($r['last_updated']) ?></td>
            <td class="actions">
                <a class="btn btn-sm btn-ghost" href="<?= BASE_URL ?>/seller/record_form.php?id=<?= (int)$r['account_id'] ?>">Edit</a>
                <?php if ($r['status'] === 'draft'): ?>
                    <?= post_button('publish', (int)$r['account_id'], 'Publish') ?>
                <?php elseif ($r['status'] === 'available'): ?>
                    <?php if ($pending): ?>
                        <form method="post" class="inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="mark_sold">
                            <input type="hidden" name="account_id" value="<?= (int)$r['account_id'] ?>">
                            <select name="offer_id" aria-label="Buyer">
                                <?php foreach ($pending as $o): ?>
                                    <option value="<?= (int)$o['offer_id'] ?>"><?= e($o['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-sm">Mark sold</button>
                        </form>
                    <?php endif; ?>
                    <?= post_button('unpublish', (int)$r['account_id'], 'Unpublish', 'btn btn-sm btn-ghost', 'Remove this listing from the market? Pending offers will be declined.') ?>
                <?php elseif ($r['status'] === 'sold'): ?>
                    <?= post_button('reopen', (int)$r['account_id'], 'Mark available', 'btn btn-sm btn-ghost') ?>
                <?php endif; ?>
                <?= post_button('delete', (int)$r['account_id'], 'Delete', 'btn btn-sm btn-danger', 'Delete this record permanently?') ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
