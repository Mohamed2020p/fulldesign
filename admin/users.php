<?php
/**
 * GodsForum - Admin: member management.
 */

declare(strict_types=1);

require_once __DIR__ . '/layout.php';

$staff = require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_post_csrf();

    $action = post_string('action');
    $userId = post_int('id');

    if ($userId > 0) {
        $target = db_one('SELECT id, username, role, status FROM users WHERE id = :id', ['id' => $userId]);

        if ($target === null) {
            flash('error', 'That member no longer exists.');
        } elseif ((int) $target['id'] === (int) $staff['id']) {
            flash('error', 'You cannot apply staff actions to your own account.');
        } elseif ($target['role'] === 'admin' && !is_super_admin()) {
            flash('error', 'Only an administrator may act on another administrator.');
        } else {
            switch ($action) {
                case 'ban':
                    db_query('UPDATE users SET status = "banned" WHERE id = :id', ['id' => $userId]);
                    log_admin_action((int) $staff['id'], 'ban_user', 'Member ' . (string) $target['username'] . ' suspended.');
                    flash('success', (string) $target['username'] . ' has been suspended.');
                    break;

                case 'unban':
                    db_query('UPDATE users SET status = "active" WHERE id = :id', ['id' => $userId]);
                    log_admin_action((int) $staff['id'], 'unban_user', 'Member ' . (string) $target['username'] . ' reinstated.');
                    flash('success', (string) $target['username'] . ' has been reinstated.');
                    break;

                case 'role':
                    if (!is_super_admin()) {
                        flash('error', 'Only an administrator may change roles.');
                        break;
                    }
                    $role = post_string('role');
                    if (!in_array($role, ['member', 'moderator', 'admin'], true)) {
                        flash('error', 'That role does not exist.');
                        break;
                    }
                    db_query('UPDATE users SET role = :r WHERE id = :id', ['r' => $role, 'id' => $userId]);
                    log_admin_action((int) $staff['id'], 'change_role', (string) $target['username'] . ' set to ' . $role . '.');
                    flash('success', 'The role has been updated.');
                    break;

                case 'delete':
                    if (!is_super_admin()) {
                        flash('error', 'Only an administrator may delete accounts.');
                        break;
                    }
                    db_query('DELETE FROM users WHERE id = :id', ['id' => $userId]);
                    log_admin_action((int) $staff['id'], 'delete_user', 'Member ' . (string) $target['username'] . ' deleted.');
                    flash('success', 'The account has been deleted. Their posts remain, marked as departed.');
                    break;
            }
        }
    }

    redirect('admin/users.php');
}

$search = mb_substr(param_string('q'), 0, 60);
$where  = '';
$params = [];

if ($search !== '') {
    $where = 'WHERE username LIKE :like OR email LIKE :like';
    $params['like'] = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
}

$perPage    = 20;
$total      = (int) db_value('SELECT COUNT(*) FROM users ' . $where, $params, 0);
$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min(max(1, param_int('page', 1)), $totalPages);
$offset     = ($page - 1) * $perPage;

$members = db_all(
    'SELECT id, username, email, role, status, post_count, created_at, last_login_at
       FROM users ' . $where . '
      ORDER BY created_at DESC
      LIMIT :limit OFFSET :offset',
    $params + ['limit' => $perPage, 'offset' => $offset]
);

admin_header('Members', 'Roles, suspensions and account removal.');
?>

<form method="get" action="<?= e(url('admin/users.php')) ?>" class="panel mb-5 flex flex-wrap items-end gap-3 p-4">
    <div class="min-w-[16rem] flex-1">
        <label class="field-label" for="q">Search by username or email</label>
        <input class="field-input" type="search" id="q" name="q" maxlength="60" value="<?= e($search) ?>" placeholder="hermes">
    </div>
    <button type="submit" class="btn btn-primary">Search</button>
    <?php if ($search !== ''): ?>
        <a class="btn btn-ghost" href="<?= e(url('admin/users.php')) ?>">Clear</a>
    <?php endif; ?>
</form>

<section class="panel overflow-x-auto">
    <table class="w-full min-w-[52rem] border-collapse text-sm">
        <thead>
            <tr class="bg-ink text-left text-[11px] uppercase tracking-[0.16em] text-parchment">
                <th scope="col" class="px-4 py-2.5 font-medium">Member</th>
                <th scope="col" class="px-4 py-2.5 font-medium">Role</th>
                <th scope="col" class="px-4 py-2.5 font-medium">Posts</th>
                <th scope="col" class="px-4 py-2.5 font-medium">Joined</th>
                <th scope="col" class="px-4 py-2.5 text-right font-medium">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-rule">
            <?php foreach ($members as $member): ?>
                <tr class="align-top transition-colors hover:bg-parchment-dark/40">
                    <td class="px-4 py-3">
                        <a class="row-link" href="<?= e(url('profile.php?id=' . (int) $member['id'])) ?>"><?= e((string) $member['username']) ?></a>
                        <p class="text-[11px] text-ink-soft"><?= e((string) $member['email']) ?></p>
                        <?php if ($member['status'] === 'banned'): ?>
                            <span class="tag tag-crimson mt-1">Suspended</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <?php if (is_super_admin()): ?>
                            <form method="post" action="<?= e(url('admin/users.php')) ?>" class="flex items-center gap-1">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="role">
                                <input type="hidden" name="id" value="<?= e((string) (int) $member['id']) ?>">
                                <label class="sr-only" for="role-<?= e((string) (int) $member['id']) ?>">Role</label>
                                <select class="field-input w-32 py-1 text-xs" id="role-<?= e((string) (int) $member['id']) ?>" name="role">
                                    <?php foreach (['member', 'moderator', 'admin'] as $role): ?>
                                        <option value="<?= e($role) ?>" <?= (string) $member['role'] === $role ? 'selected' : '' ?>><?= e(role_label($role)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-ghost btn-sm">Set</button>
                            </form>
                        <?php else: ?>
                            <span class="tag"><?= e(role_label((string) $member['role'])) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 font-semibold text-ink"><?= e(number_format((int) $member['post_count'])) ?></td>
                    <td class="px-4 py-3 text-xs text-ink-soft">
                        <?= e(date('j M Y', (int) strtotime((string) $member['created_at']))) ?>
                        <p>last login <?= e(time_ago(isset($member['last_login_at']) ? (string) $member['last_login_at'] : null)) ?></p>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex flex-wrap justify-end gap-1">
                            <?php if ($member['status'] === 'active'): ?>
                                <form method="post" action="<?= e(url('admin/users.php')) ?>" onsubmit="return confirm('Suspend this member?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="ban">
                                    <input type="hidden" name="id" value="<?= e((string) (int) $member['id']) ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Suspend</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="<?= e(url('admin/users.php')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="unban">
                                    <input type="hidden" name="id" value="<?= e((string) (int) $member['id']) ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm">Reinstate</button>
                                </form>
                            <?php endif; ?>

                            <?php if (is_super_admin()): ?>
                                <form method="post" action="<?= e(url('admin/users.php')) ?>" onsubmit="return confirm('Permanently delete this account?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= e((string) (int) $member['id']) ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if ($members === []): ?>
                <tr><td colspan="5" class="px-4 py-8 text-center text-sm italic text-ink-soft">No members match.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>

<?php if ($totalPages > 1): ?>
    <?php $base = 'admin/users.php?' . ($search !== '' ? 'q=' . rawurlencode($search) . '&' : ''); ?>
    <nav aria-label="Pagination" class="mt-5 flex flex-wrap items-center justify-center gap-1">
        <?php foreach (page_window($page, $totalPages, 3) as $p): ?>
            <a class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url($base . 'page=' . $p)) ?>"><?= e((string) $p) ?></a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>

<?php admin_footer(); ?>
