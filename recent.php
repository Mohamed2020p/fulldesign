<?php
/**
 * GodsForum - Recently active topics across every board.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$perPage    = 20;
$total      = (int) db_value('SELECT COUNT(*) FROM topics', [], 0);
$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min(max(1, param_int('page', 1)), $totalPages);
$offset     = ($page - 1) * $perPage;

$topics = db_all(
    'SELECT t.id, t.title, t.reply_count, t.view_count, t.last_post_at, t.is_locked, t.is_pinned,
            b.id AS board_id, b.name AS board_name,
            u.id AS author_id, u.username AS author_name,
            lu.id AS last_user_id, lu.username AS last_username
       FROM topics t
       JOIN boards b ON b.id = t.board_id
       LEFT JOIN users u ON u.id = t.user_id
       LEFT JOIN posts lp ON lp.id = (
            SELECT p.id FROM posts p WHERE p.topic_id = t.id ORDER BY p.created_at DESC, p.id DESC LIMIT 1)
       LEFT JOIN users lu ON lu.id = lp.user_id
      ORDER BY t.last_post_at DESC
      LIMIT :limit OFFSET :offset',
    ['limit' => $perPage, 'offset' => $offset]
);

$pageTitle       = 'Recent activity';
$pageDescription = 'The most recently active discussions on the board.';
$breadcrumbs     = [['label' => 'Recent activity']];

require __DIR__ . '/includes/header.php';
?>

<div class="mb-5">
    <h1 class="font-serif text-2xl font-semibold text-ink">Recent activity</h1>
    <p class="mt-0.5 text-sm text-ink-soft">Every board, newest conversation first.</p>
</div>

<section class="panel">
    <div class="panel-subhead">
        <span class="flex-1">Topic</span>
        <span class="hidden w-20 text-center sm:block">Replies</span>
        <span class="hidden w-40 lg:block">Last post</span>
    </div>

    <div class="divide-rule">
        <?php foreach ($topics as $topic): ?>
            <article class="flex flex-wrap items-center gap-4 px-4 py-3 transition-colors hover:bg-parchment-dark/50">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center border border-rule bg-parchment-dark text-ink-soft">
                    <span class="material-icons-outlined text-[18px]" aria-hidden="true"><?=
                        (int) $topic['is_pinned'] === 1 ? 'push_pin' : ((int) $topic['is_locked'] === 1 ? 'lock' : 'chat_bubble_outline')
                    ?></span>
                </span>

                <div class="min-w-0 flex-1">
                    <h2 class="text-[15px] font-semibold leading-snug">
                        <a class="row-link" href="<?= e(topic_url((int) $topic['id'], (string) $topic['title'])) ?>">
                            <?= e((string) $topic['title']) ?>
                        </a>
                    </h2>
                    <p class="mt-0.5 text-xs text-ink-soft">
                        in <a class="hover:text-crimson hover:underline" href="<?= e(url('board.php?id=' . (int) $topic['board_id'])) ?>"><?= e((string) $topic['board_name']) ?></a>
                        &middot; started by
                        <?php if ($topic['author_id'] !== null): ?>
                            <a class="font-medium text-ink hover:text-crimson hover:underline" href="<?= e(url('profile.php?id=' . (int) $topic['author_id'])) ?>"><?= e((string) $topic['author_name']) ?></a>
                        <?php else: ?>
                            <span class="font-medium text-ink">a departed member</span>
                        <?php endif; ?>
                    </p>
                </div>

                <div class="hidden w-20 shrink-0 text-center text-sm font-semibold text-ink sm:block">
                    <?= e(number_short((int) $topic['reply_count'])) ?>
                </div>

                <div class="hidden w-40 shrink-0 border-l border-rule pl-4 text-xs text-ink-soft lg:block">
                    <span class="block"><?= e(time_ago((string) $topic['last_post_at'])) ?></span>
                    <span class="block">
                        by
                        <?php if ($topic['last_user_id'] !== null): ?>
                            <a class="font-medium text-ink hover:text-crimson hover:underline" href="<?= e(url('profile.php?id=' . (int) $topic['last_user_id'])) ?>"><?= e((string) $topic['last_username']) ?></a>
                        <?php else: ?>
                            <span class="font-medium text-ink">a departed member</span>
                        <?php endif; ?>
                    </span>
                </div>
            </article>
        <?php endforeach; ?>

        <?php if ($topics === []): ?>
            <p class="px-4 py-10 text-center text-sm text-ink-soft">No topics have been created yet.</p>
        <?php endif; ?>
    </div>
</section>

<?php if ($totalPages > 1): ?>
    <nav aria-label="Pagination" class="mt-5 flex flex-wrap items-center justify-center gap-1">
        <?php if ($page > 1): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('recent.php?page=' . ($page - 1))) ?>">Previous</a>
        <?php endif; ?>
        <?php foreach (page_window($page, $totalPages) as $p): ?>
            <a class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('recent.php?page=' . $p)) ?>"><?= e((string) $p) ?></a>
        <?php endforeach; ?>
        <?php if ($page < $totalPages): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('recent.php?page=' . ($page + 1))) ?>">Next</a>
        <?php endif; ?>
    </nav>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
