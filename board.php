<?php
/**
 * GodsForum - Topic listing for a single board.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$boardId = param_int('id');
$board = $boardId > 0
    ? db_one(
        'SELECT b.id, b.name, b.description, b.icon, b.is_locked, c.id AS category_id, c.name AS category_name
           FROM boards b JOIN categories c ON c.id = b.category_id
          WHERE b.id = :id LIMIT 1',
        ['id' => $boardId]
    )
    : null;

if ($board === null) {
    http_response_code(404);
    $pageTitle = 'Board not found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="panel p-10 text-center"><h1 class="font-serif text-xl text-ink">Board not found</h1>'
       . '<p class="mt-2 text-sm text-ink-soft">The board you asked for does not exist.</p>'
       . '<a class="btn btn-primary mt-5" href="' . e(url('index.php')) . '">Back to board index</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$totalTopics = (int) db_value('SELECT COUNT(*) FROM topics WHERE board_id = :b', ['b' => $boardId], 0);
$totalPages  = max(1, (int) ceil($totalTopics / TOPICS_PER_PAGE));
$page        = min(max(1, param_int('page', 1)), $totalPages);
$offset      = ($page - 1) * TOPICS_PER_PAGE;

$topics = db_all(
    'SELECT t.id, t.title, t.is_pinned, t.is_locked, t.view_count, t.reply_count,
            t.created_at, t.last_post_at,
            u.id AS author_id, u.username AS author_name,
            lu.id AS last_user_id, lu.username AS last_username
       FROM topics t
       LEFT JOIN users u ON u.id = t.user_id
       LEFT JOIN posts lp ON lp.id = (
            SELECT p.id FROM posts p WHERE p.topic_id = t.id ORDER BY p.created_at DESC, p.id DESC LIMIT 1)
       LEFT JOIN users lu ON lu.id = lp.user_id
      WHERE t.board_id = :board
      ORDER BY t.is_pinned DESC, t.last_post_at DESC
      LIMIT :limit OFFSET :offset',
    ['board' => $boardId, 'limit' => TOPICS_PER_PAGE, 'offset' => $offset]
);

$canPost = is_logged_in() && ((int) $board['is_locked'] === 0 || is_admin());

$pageTitle       = (string) $board['name'];
$pageDescription = (string) $board['description'];
$breadcrumbs     = [
    ['label' => (string) $board['category_name']],
    ['label' => (string) $board['name']],
];

require __DIR__ . '/includes/header.php';
?>

<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
    <div class="flex items-start gap-3">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center border border-rule bg-parchment-light text-ink">
            <span class="material-icons-outlined text-[22px]" aria-hidden="true"><?= e((string) $board['icon']) ?></span>
        </span>
        <div>
            <h1 class="font-serif text-2xl font-semibold text-ink"><?= e((string) $board['name']) ?></h1>
            <p class="mt-0.5 text-sm text-ink-soft"><?= e((string) $board['description']) ?></p>
        </div>
    </div>

    <?php if ($canPost): ?>
        <a class="btn btn-gold" href="<?= e(url('new_topic.php?board=' . (int) $board['id'])) ?>">
            <span class="material-icons-outlined text-[18px]" aria-hidden="true">edit_note</span>
            Start a topic
        </a>
    <?php elseif ((int) $board['is_locked'] === 1): ?>
        <span class="tag tag-crimson">Board locked</span>
    <?php else: ?>
        <a class="btn btn-ghost" href="<?= e(url('login.php')) ?>">
            <span class="material-icons-outlined text-[18px]" aria-hidden="true">login</span>
            Sign in to post
        </a>
    <?php endif; ?>
</div>

<section class="panel">
    <div class="panel-subhead">
        <span class="flex-1">Topic</span>
        <span class="hidden w-20 text-center sm:block">Replies</span>
        <span class="hidden w-20 text-center sm:block">Views</span>
        <span class="hidden w-48 lg:block">Last post</span>
    </div>

    <div class="divide-rule">
        <?php if ($topics === []): ?>
            <p class="px-4 py-10 text-center text-sm text-ink-soft">
                This board is empty. Be the first to start a topic.
            </p>
        <?php endif; ?>

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
                    <p class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-ink-soft">
                        <?php if ((int) $topic['is_pinned'] === 1): ?><span class="tag tag-gold">Pinned</span><?php endif; ?>
                        <?php if ((int) $topic['is_locked'] === 1): ?><span class="tag tag-crimson">Locked</span><?php endif; ?>
                        <span>
                            Started by
                            <?php if ($topic['author_id'] !== null): ?>
                                <a class="font-medium text-ink hover:text-crimson hover:underline"
                                   href="<?= e(url('profile.php?id=' . (int) $topic['author_id'])) ?>"><?= e((string) $topic['author_name']) ?></a>
                            <?php else: ?>
                                <span class="font-medium text-ink">a departed member</span>
                            <?php endif; ?>
                            <?= e(time_ago((string) $topic['created_at'])) ?>
                        </span>
                    </p>
                </div>

                <div class="hidden w-20 shrink-0 text-center text-sm font-semibold text-ink sm:block">
                    <?= e(number_short((int) $topic['reply_count'])) ?>
                </div>
                <div class="hidden w-20 shrink-0 text-center text-sm font-semibold text-ink sm:block">
                    <?= e(number_short((int) $topic['view_count'])) ?>
                </div>
                <div class="hidden w-48 shrink-0 border-l border-rule pl-4 text-xs text-ink-soft lg:block">
                    <span class="block"><?= e(time_ago((string) $topic['last_post_at'])) ?></span>
                    <span class="block">
                        by
                        <?php if ($topic['last_user_id'] !== null): ?>
                            <a class="font-medium text-ink hover:text-crimson hover:underline"
                               href="<?= e(url('profile.php?id=' . (int) $topic['last_user_id'])) ?>"><?= e((string) $topic['last_username']) ?></a>
                        <?php else: ?>
                            <span class="font-medium text-ink">a departed member</span>
                        <?php endif; ?>
                    </span>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php if ($totalPages > 1): ?>
    <nav aria-label="Pagination" class="mt-5 flex flex-wrap items-center justify-center gap-1 text-sm">
        <?php if ($page > 1): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('board.php?id=' . $boardId . '&page=' . ($page - 1))) ?>">Previous</a>
        <?php endif; ?>
        <?php foreach (page_window($page, $totalPages) as $p): ?>
            <a href="<?= e(url('board.php?id=' . $boardId . '&page=' . $p)) ?>"
               class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-ghost' ?>"
               <?= $p === $page ? 'aria-current="page"' : '' ?>><?= e((string) $p) ?></a>
        <?php endforeach; ?>
        <?php if ($page < $totalPages): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('board.php?id=' . $boardId . '&page=' . ($page + 1))) ?>">Next</a>
        <?php endif; ?>
    </nav>
    <p class="mt-2 text-center text-xs text-ink-soft">Page <?= e((string) $page) ?> of <?= e((string) $totalPages) ?></p>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
