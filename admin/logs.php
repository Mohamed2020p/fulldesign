<?php
/**
 * GodsForum - Admin: staff action log (administrators only).
 */

declare(strict_types=1);

require_once __DIR__ . '/layout.php';

$staff = require_admin();

if (!is_super_admin()) {
    http_response_code(403);
    exit('Only an administrator may read the staff log.');
}

$perPage    = 30;
$total      = (int) db_value('SELECT COUNT(*) FROM admin_log', [], 0);
$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min(max(1, param_int('page', 1)), $totalPages);
$offset     = ($page - 1) * $perPage;

$entries = db_all(
    'SELECT l.id, l.action, l.details, l.ip_address, l.created_at, u.id AS admin_id, u.username
       FROM admin_log l
       LEFT JOIN users u ON u.id = l.admin_id
      ORDER BY l.created_at DESC, l.id DESC
      LIMIT :limit OFFSET :offset',
    ['limit' => $perPage, 'offset' => $offset]
);

$attempts = db_all(
    'SELECT identifier, ip_address, success, attempted_at
       FROM login_attempts
      ORDER BY attempted_at DESC
      LIMIT 15'
);

admin_header('Staff log', 'Every moderation action, with who did it and when.');
?>

<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
    <section class="panel overflow-x-auto">
        <table class="w-full min-w-[42rem] border-collapse text-sm">
            <thead>
                <tr class="bg-ink text-left text-[11px] uppercase tracking-[0.16em] text-parchment">
                    <th scope="col" class="px-4 py-2.5 font-medium">When</th>
                    <th scope="col" class="px-4 py-2.5 font-medium">Staff</th>
                    <th scope="col" class="px-4 py-2.5 font-medium">Action</th>
                    <th scope="col" class="px-4 py-2.5 font-medium">Details</th>
                </tr>
            </thead>
            <tbody class="divide-rule">
                <?php foreach ($entries as $entry): ?>
                    <tr class="align-top transition-colors hover:bg-parchment-dark/40">
                        <td class="whitespace-nowrap px-4 py-2.5 text-xs text-ink-soft"><?= e(full_date((string) $entry['created_at'])) ?></td>
                        <td class="px-4 py-2.5">
                            <?php if ($entry['admin_id'] !== null): ?>
                                <a class="row-link text-xs" href="<?= e(url('profile.php?id=' . (int) $entry['admin_id'])) ?>"><?= e((string) $entry['username']) ?></a>
                            <?php else: ?>
                                <span class="text-xs italic text-ink-soft">removed account</span>
                            <?php endif; ?>
                            <p class="text-[11px] text-ink-soft"><?= e((string) $entry['ip_address']) ?></p>
                        </td>
                        <td class="px-4 py-2.5"><span class="tag"><?= e((string) $entry['action']) ?></span></td>
                        <td class="px-4 py-2.5 text-xs text-ink-soft"><?= e((string) $entry['details']) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if ($entries === []): ?>
                    <tr><td colspan="4" class="px-4 py-8 text-center text-sm italic text-ink-soft">No staff actions recorded yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="panel h-fit">
        <h2 class="panel-head">
            <span class="material-icons-outlined text-[18px]" aria-hidden="true">vpn_key</span>
            Recent sign in attempts
        </h2>
        <ul class="divide-rule text-xs">
            <?php foreach ($attempts as $attempt): ?>
                <li class="flex items-center justify-between gap-2 px-4 py-2">
                    <div class="min-w-0">
                        <p class="truncate font-medium text-ink"><?= e((string) $attempt['identifier']) ?></p>
                        <p class="text-ink-soft"><?= e((string) $attempt['ip_address']) ?> &middot; <?= e(time_ago((string) $attempt['attempted_at'])) ?></p>
                    </div>
                    <span class="<?= (int) $attempt['success'] === 1 ? 'tag tag-forest' : 'tag tag-crimson' ?>">
                        <?= (int) $attempt['success'] === 1 ? 'Success' : 'Failed' ?>
                    </span>
                </li>
            <?php endforeach; ?>
            <?php if ($attempts === []): ?>
                <li class="px-4 py-6 text-center italic text-ink-soft">No attempts recorded.</li>
            <?php endif; ?>
        </ul>
    </section>
</div>

<?php if ($totalPages > 1): ?>
    <nav aria-label="Pagination" class="mt-5 flex flex-wrap items-center justify-center gap-1">
        <?php foreach (page_window($page, $totalPages, 3) as $p): ?>
            <a class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('admin/logs.php?page=' . $p)) ?>"><?= e((string) $p) ?></a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>

<?php admin_footer(); ?>
