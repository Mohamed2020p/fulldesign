<?php
/**
 * GodsForum - Admin: member management.
 *
 * Roles, suspensions (permanent or timed, with a reason kept on record) and
 * account removal. Every write goes through a prepared statement and every
 * action is written to the staff log.
 */

declare(strict_types=1);

// This page is a route target, not a public address. It is reached only
// through the front controller, which has already resolved the request.
if (!defined('GF_ROUTER')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/layout.php';

$staff = require_admin();

/** Suspension lengths offered in the interface, in hours. 0 means permanent. */
$banDurations = [
    '0'    => 'Permanent',
    '24'   => '1 day',
    '72'   => '3 days',
    '168'  => '1 week',
    '720'  => '30 days',
    '2160' => '90 days',
];

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
                    $reason = mb_substr(trim(post_string('reason')), 0, 255);
                    $hours  = post_string('duration');

                    if (!array_key_exists($hours, $banDurations)) {
                        flash('error', 'That suspension length is not offered.');
                        break;
                    }

                    $expires = (int) $hours > 0
                        ? date('Y-m-d H:i:s', time() + ((int) $hours * 3600))
                        : null;

                    db_query(
                        'UPDATE users
                            SET status = "banned", ban_reason = :reason, banned_until = :until, banned_by = :staff
                          WHERE id = :id',
                        [
                            'reason' => $reason,
                            'until'  => $expires,
                            'staff'  => (int) $staff['id'],
                            'id'     => $userId,
                        ]
                    );

                    db_query(
                        'INSERT INTO bans (user_id, staff_id, reason, expires_at) VALUES (:u, :s, :r, :e)',
                        ['u' => $userId, 's' => (int) $staff['id'], 'r' => $reason, 'e' => $expires]
                    );

                    log_admin_action(
                        (int) $staff['id'],
                        'ban_user',
                        'Member ' . (string) $target['username'] . ' suspended ('
                            . ($expires === null ? 'permanent' : 'until ' . $expires) . ').'
                    );
                    flash('success', (string) $target['username'] . ' has been suspended.');
                    break;

                case 'unban':
                    db_query(
                        'UPDATE users SET status = "active", ban_reason = "", banned_until = NULL, banned_by = NULL
                          WHERE id = :id',
                        ['id' => $userId]
                    );
                    db_query(
                        'UPDATE bans SET lifted_at = NOW() WHERE user_id = :id AND lifted_at IS NULL',
                        ['id' => $userId]
                    );
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

    redirect(url('admin/members'));
}

$search = mb_substr(param_string('q'), 0, 60);
$filter = param_string('show');

$conditions = [];
$params     = [];

if ($search !== '') {
    $conditions[] = '(username LIKE :like OR email LIKE :like)';
    $params['like'] = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
}

// The filter is mapped through a whitelist, never concatenated from input.
$filterSql = [
    'banned' => 'status = "banned"',
    'staff'  => 'role IN ("admin", "moderator")',
];

if (isset($filterSql[$filter])) {
    $conditions[] = $filterSql[$filter];
} else {
    $filter = '';
}

$where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

$perPage    = 20;
$total      = (int) db_value('SELECT COUNT(*) FROM users' . $where, $params, 0);
$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min(max(1, param_int('page', 1)), $totalPages);
$offset     = ($page - 1) * $perPage;

$members = db_all(
    'SELECT id, username, email, role, status, post_count, created_at, last_login_at,
            ban_reason, banned_until
       FROM users' . $where . '
      ORDER BY created_at DESC
      LIMIT :limit OFFSET :offset',
    $params + ['limit' => $perPage, 'offset' => $offset]
);

$bannedCount = (int) db_value('SELECT COUNT(*) FROM users WHERE status = "banned"', [], 0);

admin_header('Members', 'Roles, suspensions and account removal.');
?>

<div class="mb-5 flex flex-wrap items-end gap-3">
    <form method="get" action="<?= e(url('admin/members')) ?>" class="panel flex flex-1 flex-wrap items-end gap-3 p-4">
        <div class="min-w-[15rem] flex-1">
            <label class="field-label" for="q">Search by username or email</label>
            <input class="field-input" type="search" id="q" name="q" maxlength="60" value="<?= e($search) ?>" placeholder="hermes">
        </div>
        <div>
            <label class="field-label" for="show">Show</label>
            <select class="field-input w-40" id="show" name="show">
                <option value="">Everyone</option>
                <option value="banned" <?= $filter === 'banned' ? 'selected' : '' ?>>Suspended only</option>
                <option value="staff"  <?= $filter === 'staff'  ? 'selected' : '' ?>>Staff only</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Apply</button>
        <?php if ($search !== '' || $filter !== ''): ?>
            <a class="btn btn-ghost" href="<?= e(url('admin/members')) ?>">Clear</a>
        <?php endif; ?>
    </form>

    <div class="stat-card min-w-[12rem]">
        <span class="stat-icon"><span class="material-icons-outlined" aria-hidden="true">block</span></span>
        <span>
            <span class="stat-value"><?= e(number_format($bannedCount)) ?></span>
            <span class="stat-label block">currently suspended</span>
        </span>
    </div>
</div>

<section class="panel overflow-x-auto">
    <table class="admin-table min-w-[60rem]">
        <thead>
            <tr>
                <th scope="col">Member</th>
                <th scope="col">Role</th>
                <th scope="col">Posts</th>
                <th scope="col">Joined</th>
                <th scope="col">Moderation</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($members as $member): ?>
                <?php $mid = (int) $member['id']; ?>
                <tr>
                    <td>
                        <a class="row-link" href="<?= e(member_url((string) $member['username'])) ?>"><?= e((string) $member['username']) ?></a>
                        <p class="text-[11px] text-soft"><?= e((string) $member['email']) ?></p>
                        <?php if ($member['status'] === 'banned'): ?>
                            <span class="tag tag-crimson mt-1">Suspended</span>
                            <p class="mt-1 max-w-[18rem] text-[11px] text-soft">
                                <?php if (trim((string) $member['ban_reason']) !== ''): ?>
                                    <?= e((string) $member['ban_reason']) ?><br>
                                <?php endif; ?>
                                <?= $member['banned_until'] === null
                                        ? 'Permanent'
                                        : e('Until ' . date('j M Y H:i', (int) strtotime((string) $member['banned_until']))) ?>
                            </p>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php if (is_super_admin()): ?>
                            <form method="post" action="<?= e(url('admin/members')) ?>" class="flex items-center gap-1">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="role">
                                <input type="hidden" name="id" value="<?= e((string) $mid) ?>">
                                <label class="sr-only" for="role-<?= e((string) $mid) ?>">Role</label>
                                <select class="field-input w-32 py-1 text-xs" id="role-<?= e((string) $mid) ?>" name="role">
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

                    <td class="font-semibold"><?= e(number_format((int) $member['post_count'])) ?></td>

                    <td class="text-xs text-soft">
                        <?= e(date('j M Y', (int) strtotime((string) $member['created_at']))) ?>
                        <p>last login <?= e(time_ago(isset($member['last_login_at']) ? (string) $member['last_login_at'] : null)) ?></p>
                    </td>

                    <td>
                        <?php if ($member['status'] === 'active'): ?>
                            <form method="post" action="<?= e(url('admin/members')) ?>" class="space-y-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="ban">
                                <input type="hidden" name="id" value="<?= e((string) $mid) ?>">

                                <div class="flex flex-wrap items-end gap-2">
                                    <div class="min-w-[11rem] flex-1">
                                        <label class="sr-only" for="reason-<?= e((string) $mid) ?>">Reason for suspending <?= e((string) $member['username']) ?></label>
                                        <input class="field-input py-1 text-xs" type="text" maxlength="255"
                                               id="reason-<?= e((string) $mid) ?>" name="reason" placeholder="Reason (shown to the member)">
                                    </div>
                                    <div>
                                        <label class="sr-only" for="duration-<?= e((string) $mid) ?>">Length</label>
                                        <select class="field-input w-28 py-1 text-xs" id="duration-<?= e((string) $mid) ?>" name="duration">
                                            <?php foreach ($banDurations as $hours => $label): ?>
                                                <option value="<?= e((string) $hours) ?>"><?= e($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-danger btn-sm">Suspend</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <form method="post" action="<?= e(url('admin/members')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="unban">
                                <input type="hidden" name="id" value="<?= e((string) $mid) ?>">
                                <button type="submit" class="btn btn-ghost btn-sm">Reinstate</button>
                            </form>
                        <?php endif; ?>

                        <?php if (is_super_admin()): ?>
                            <form method="post" action="<?= e(url('admin/members')) ?>" class="mt-2"
                                  onsubmit="return confirm('Permanently delete this account?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= e((string) $mid) ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete account</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if ($members === []): ?>
                <tr><td colspan="5" class="px-4 py-8 text-center text-sm italic text-soft">No members match.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>

<?php if ($totalPages > 1): ?>
    <?php
    $query = [];
    if ($search !== '') {
        $query['q'] = $search;
    }
    if ($filter !== '') {
        $query['show'] = $filter;
    }
    ?>
    <nav aria-label="Pagination" class="mt-5 flex flex-wrap items-center justify-center gap-1">
        <?php foreach (page_window($page, $totalPages, 3) as $p): ?>
            <a class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-ghost' ?>"
               href="<?= e(url('admin/members?' . http_build_query($query + ['page' => $p]))) ?>"><?= e((string) $p) ?></a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>

<?php admin_footer(); ?>
