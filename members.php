<?php
/**
 * GodsForum - Member directory.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$search = mb_substr(param_string('q'), 0, 32);
$sort   = param_string('sort', 'posts');

$orderBy = match ($sort) {
    'name'   => 'u.username ASC',
    'newest' => 'u.created_at DESC',
    default  => 'u.post_count DESC, u.username ASC',
};

$where  = 'WHERE u.status = "active"';
$params = [];

if ($search !== '') {
    $where .= ' AND u.username LIKE :q';
    $params['q'] = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
}

$total      = (int) db_value('SELECT COUNT(*) FROM users u ' . $where, $params, 0);
$totalPages = max(1, (int) ceil($total / MEMBERS_PER_PAGE));
$page       = min(max(1, param_int('page', 1)), $totalPages);
$offset     = ($page - 1) * MEMBERS_PER_PAGE;

$listParams = $params + ['limit' => MEMBERS_PER_PAGE, 'offset' => $offset];
$members = db_all(
    'SELECT u.id, u.username, u.role, u.avatar, u.post_count, u.created_at, u.last_seen_at
       FROM users u ' . $where . '
      ORDER BY ' . $orderBy . '
      LIMIT :limit OFFSET :offset',
    $listParams
);

$pageTitle       = 'Members';
$pageDescription = 'Everyone who posts at GodsForum.';
$breadcrumbs     = [['label' => 'Members']];

$queryBase = 'members.php?sort=' . rawurlencode($sort) . ($search !== '' ? '&q=' . rawurlencode($search) : '');

require __DIR__ . '/includes/header.php';
?>

<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="font-serif text-2xl font-semibold text-ink">Members</h1>
        <p class="mt-0.5 text-sm text-ink-soft"><?= e(number_format($total)) ?> active account<?= $total === 1 ? '' : 's' ?>.</p>
    </div>

    <form method="get" action="<?= e(url('members.php')) ?>" class="flex flex-wrap items-end gap-2">
        <div>
            <label class="field-label" for="q">Find a member</label>
            <input class="field-input w-52" type="search" id="q" name="q" value="<?= e($search) ?>" maxlength="32" placeholder="Username">
        </div>
        <div>
            <label class="field-label" for="sort">Order by</label>
            <select class="field-input w-40" id="sort" name="sort">
                <option value="posts"  <?= $sort === 'posts'  ? 'selected' : '' ?>>Post count</option>
                <option value="name"   <?= $sort === 'name'   ? 'selected' : '' ?>>Name</option>
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">
            <span class="material-icons-outlined text-[18px]" aria-hidden="true">search</span>
            Apply
        </button>
    </form>
</div>

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <?php foreach ($members as $member): ?>
        <article class="panel flex items-center gap-3 p-4 transition-colors hover:bg-parchment-dark/40">
            <img src="<?= e(avatar_url(isset($member['avatar']) ? (string) $member['avatar'] : null)) ?>" alt=""
                 class="h-14 w-14 shrink-0 border border-rule bg-parchment-dark object-cover">
            <div class="min-w-0">
                <h2 class="truncate text-sm font-semibold">
                    <a class="row-link" href="<?= e(url('profile.php?id=' . (int) $member['id'])) ?>"><?= e((string) $member['username']) ?></a>
                </h2>
                <p class="mt-1">
                    <span class="<?= $member['role'] === 'admin' ? 'tag tag-crimson' : ($member['role'] === 'moderator' ? 'tag tag-forest' : 'tag') ?>">
                        <?= e(role_label((string) $member['role'])) ?>
                    </span>
                </p>
                <p class="mt-1 text-[11px] text-ink-soft">
                    <?= e(number_format((int) $member['post_count'])) ?> posts &middot;
                    joined <?= e(date('M Y', (int) strtotime((string) $member['created_at']))) ?>
                </p>
                <p class="text-[11px] text-ink-soft">Seen <?= e(time_ago(isset($member['last_seen_at']) ? (string) $member['last_seen_at'] : null)) ?></p>
            </div>
        </article>
    <?php endforeach; ?>
</div>

<?php if ($members === []): ?>
    <div class="panel p-10 text-center text-sm text-ink-soft">No members match that search.</div>
<?php endif; ?>

<?php if ($totalPages > 1): ?>
    <nav aria-label="Pagination" class="mt-6 flex flex-wrap items-center justify-center gap-1">
        <?php if ($page > 1): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(url($queryBase . '&page=' . ($page - 1))) ?>">Previous</a>
        <?php endif; ?>
        <?php foreach (page_window($page, $totalPages) as $p): ?>
            <a class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-ghost' ?>"
               href="<?= e(url($queryBase . '&page=' . $p)) ?>"><?= e((string) $p) ?></a>
        <?php endforeach; ?>
        <?php if ($page < $totalPages): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(url($queryBase . '&page=' . ($page + 1))) ?>">Next</a>
        <?php endif; ?>
    </nav>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
