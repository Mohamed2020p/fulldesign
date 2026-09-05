<?php
/**
 * GodsForum - Admin dashboard.
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

$stats = db_one(
    'SELECT
        (SELECT COUNT(*) FROM users)                              AS members,
        (SELECT COUNT(*) FROM users WHERE status = "banned")      AS banned,
        (SELECT COUNT(*) FROM topics)                             AS topics,
        (SELECT COUNT(*) FROM posts)                              AS posts,
        (SELECT COUNT(*) FROM boards)                             AS boards,
        (SELECT COUNT(*) FROM reports WHERE status = "open")      AS open_reports,
        (SELECT COUNT(*) FROM users WHERE created_at > (NOW() - INTERVAL 7 DAY))  AS new_members,
        (SELECT COUNT(*) FROM posts WHERE created_at > (NOW() - INTERVAL 7 DAY))  AS new_posts'
) ?? [];

$recentPosts = db_all(
    'SELECT p.id, p.ref, p.body, p.created_at, t.id AS topic_id, t.title, t.slug AS topic_slug, u.username
       FROM posts p
       JOIN topics t ON t.id = p.topic_id
       LEFT JOIN users u ON u.id = p.user_id
      ORDER BY p.created_at DESC LIMIT 8'
);

$recentMembers = db_all(
    'SELECT id, username, created_at, role, status FROM users ORDER BY created_at DESC LIMIT 8'
);

$openReports = db_all(
    'SELECT r.id, r.reason, r.created_at, p.id AS post_id, t.title, u.username AS reporter
       FROM reports r
       JOIN posts p ON p.id = r.post_id
       JOIN topics t ON t.id = p.topic_id
       LEFT JOIN users u ON u.id = r.reporter_id
      WHERE r.status = "open"
      ORDER BY r.created_at DESC LIMIT 5'
);

admin_header('Dashboard', 'A quick view of the board and anything waiting for the staff.');
?>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <?php
    $cards = [
        ['label' => 'Members',      'value' => (int) ($stats['members'] ?? 0),      'icon' => 'group',   'note' => (int) ($stats['new_members'] ?? 0) . ' joined this week'],
        ['label' => 'Topics',       'value' => (int) ($stats['topics'] ?? 0),       'icon' => 'forum',   'note' => (int) ($stats['boards'] ?? 0) . ' boards'],
        ['label' => 'Posts',        'value' => (int) ($stats['posts'] ?? 0),        'icon' => 'article', 'note' => (int) ($stats['new_posts'] ?? 0) . ' written this week'],
        ['label' => 'Open reports', 'value' => (int) ($stats['open_reports'] ?? 0), 'icon' => 'flag',    'note' => (int) ($stats['banned'] ?? 0) . ' suspended accounts'],
    ];
    foreach ($cards as $card): ?>
        <article class="panel p-4">
            <div class="flex items-center justify-between">
                <span class="text-[10px] uppercase tracking-[0.2em] text-soft"><?= e($card['label']) ?></span>
                <span class="material-icons-outlined text-[20px] text-gold" aria-hidden="true"><?= e($card['icon']) ?></span>
            </div>
            <p class="mt-2 font-serif text-3xl font-semibold text-ink"><?= e(number_format($card['value'])) ?></p>
            <p class="mt-1 text-xs text-soft"><?= e($card['note']) ?></p>
        </article>
    <?php endforeach; ?>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <section class="panel">
        <h2 class="panel-head">
            <span class="material-icons-outlined text-[18px]" aria-hidden="true">flag</span>
            Reports waiting
        </h2>
        <ul class="divide-rule">
            <?php foreach ($openReports as $report): ?>
                <li class="px-4 py-3">
                    <p class="text-sm font-semibold text-ink"><?= e(excerpt((string) $report['title'], 60)) ?></p>
                    <p class="mt-0.5 text-xs text-soft"><?= e(excerpt((string) $report['reason'], 120)) ?></p>
                    <p class="mt-1 text-[11px] text-soft">
                        reported by <?= e((string) ($report['reporter'] ?? 'a departed member')) ?> &middot; <?= e(time_ago((string) $report['created_at'])) ?>
                    </p>
                </li>
            <?php endforeach; ?>
            <?php if ($openReports === []): ?>
                <li class="px-4 py-8 text-center text-sm italic text-soft">Nothing is waiting. The hall is calm.</li>
            <?php endif; ?>
        </ul>
        <div class="border-t border-rule p-3">
            <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/reports')) ?>">Open the report queue</a>
        </div>
    </section>

    <section class="panel">
        <h2 class="panel-head">
            <span class="material-icons-outlined text-[18px]" aria-hidden="true">person_add</span>
            Newest members
        </h2>
        <ul class="divide-rule">
            <?php foreach ($recentMembers as $member): ?>
                <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                    <div class="min-w-0">
                        <a class="row-link text-sm" href="<?= e(member_url((string) $member['username'])) ?>"><?= e((string) $member['username']) ?></a>
                        <p class="text-[11px] text-soft">joined <?= e(time_ago((string) $member['created_at'])) ?></p>
                    </div>
                    <span class="<?= $member['status'] === 'banned' ? 'tag tag-crimson' : 'tag' ?>">
                        <?= $member['status'] === 'banned' ? 'Suspended' : e(role_label((string) $member['role'])) ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="border-t border-rule p-3">
            <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/members')) ?>">Manage members</a>
        </div>
    </section>

    <section class="panel xl:col-span-2">
        <h2 class="panel-head">
            <span class="material-icons-outlined text-[18px]" aria-hidden="true">schedule</span>
            Latest posts
        </h2>
        <div class="divide-rule">
            <?php foreach ($recentPosts as $post): ?>
                <article class="px-4 py-3">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <a class="row-link text-sm" href="<?= e(topic_url((string) $post['topic_slug'])) ?>#post-<?= e((string) (int) $post['id']) ?>">
                            <?= e(excerpt((string) $post['title'], 70)) ?>
                        </a>
                        <span class="text-[11px] text-soft">
                            <?= e((string) ($post['username'] ?? 'departed member')) ?> &middot; <?= e(time_ago((string) $post['created_at'])) ?>
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-soft"><?= e(excerpt((string) $post['body'], 170)) ?></p>
                </article>
            <?php endforeach; ?>
            <?php if ($recentPosts === []): ?>
                <p class="px-4 py-8 text-center text-sm italic text-soft">No posts yet.</p>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php admin_footer(); ?>
