<?php
/**
 * GodsForum - Topic listing for a single board.
 *
 * Reached as /board/<slug> and /board/<slug>/page/<n>. The slug arrives from
 * the router already constrained to [a-z0-9-], and is bound as a parameter,
 * so it can only ever match a row or match nothing.
 */

declare(strict_types=1);

// This page is a route target, not a public address. It is reached only
// through the front controller, which has already resolved the request.
if (!defined('GF_ROUTER')) {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/functions.php';

/** @var array<string, string> $route */
$slug = (string) ($route['slug'] ?? '');

$board = db_one(
    'SELECT b.id, b.name, b.slug, b.description, b.icon, b.is_locked,
            c.id AS category_id, c.name AS category_name, c.slug AS category_slug
       FROM boards b
       JOIN categories c ON c.id = b.category_id
      WHERE b.slug = :slug
      LIMIT 1',
    ['slug' => $slug]
);

if ($board === null) {
    router_not_found();
}

$boardId     = (int) $board['id'];
$totalTopics = (int) db_value('SELECT COUNT(*) FROM topics WHERE board_id = :b', ['b' => $boardId], 0);
$totalPages  = max(1, (int) ceil($totalTopics / TOPICS_PER_PAGE));
$page        = min(max(1, (int) ($route['page'] ?? 1)), $totalPages);
$offset      = ($page - 1) * TOPICS_PER_PAGE;

$topics = db_all(
    'SELECT t.id, t.title, t.slug, t.is_pinned, t.is_locked, t.view_count, t.reply_count,
            t.created_at, t.last_post_at,
            u.username AS author_name,
            lu.username AS last_username
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

require dirname(__DIR__) . '/includes/header.php';
?>

<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
    <div class="flex items-start gap-3">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center border border-rule" style="background-color: var(--page-alt)">
            <span class="material-icons-outlined text-[22px]" aria-hidden="true"><?= e((string) $board['icon']) ?></span>
        </span>
        <div>
            <h1 class="font-serif text-2xl font-semibold"><?= e((string) $board['name']) ?></h1>
            <p class="mt-0.5 text-sm text-soft"><?= e((string) $board['description']) ?></p>
        </div>
    </div>

    <?php if ($canPost): ?>
        <a class="btn btn-gold" href="<?= e(url('board/' . rawurlencode((string) $board['slug']) . '/new')) ?>">
            <span class="material-icons-outlined text-[18px]" aria-hidden="true">edit_note</span>
            Start a topic
        </a>
    <?php elseif ((int) $board['is_locked'] === 1): ?>
        <span class="tag tag-crimson">Board locked</span>
    <?php else: ?>
        <a class="btn btn-ghost" href="<?= e(url('login')) ?>">
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
            <p class="px-4 py-10 text-center text-sm text-soft">
                This board is empty. Be the first to start a topic.
            </p>
        <?php endif; ?>

        <?php foreach ($topics as $topic): ?>
            <article class="row-hover flex flex-wrap items-center gap-4 px-4 py-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center border border-rule text-soft" style="background-color: var(--page-alt)">
                    <span class="material-icons-outlined text-[18px]" aria-hidden="true"><?=
                        (int) $topic['is_pinned'] === 1 ? 'push_pin' : ((int) $topic['is_locked'] === 1 ? 'lock' : 'chat_bubble_outline')
                    ?></span>
                </span>

                <div class="min-w-0 flex-1">
                    <h2 class="text-[15px] font-semibold leading-snug">
                        <a class="row-link" href="<?= e(topic_url((string) $topic['slug'])) ?>">
                            <?= e((string) $topic['title']) ?>
                        </a>
                    </h2>
                    <p class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-soft">
                        <?php if ((int) $topic['is_pinned'] === 1): ?><span class="tag tag-gold">Pinned</span><?php endif; ?>
                        <?php if ((int) $topic['is_locked'] === 1): ?><span class="tag tag-crimson">Locked</span><?php endif; ?>
                        <span>
                            Started by
                            <?php if ($topic['author_name'] !== null): ?>
                                <a class="font-medium hover:underline" href="<?= e(member_url((string) $topic['author_name'])) ?>"><?= e((string) $topic['author_name']) ?></a>
                            <?php else: ?>
                                <span class="font-medium">a departed member</span>
                            <?php endif; ?>
                            <?= e(time_ago((string) $topic['created_at'])) ?>
                        </span>
                    </p>
                </div>

                <div class="hidden w-20 shrink-0 text-center text-sm font-semibold sm:block">
                    <?= e(number_short((int) $topic['reply_count'])) ?>
                </div>
                <div class="hidden w-20 shrink-0 text-center text-sm font-semibold sm:block">
                    <?= e(number_short((int) $topic['view_count'])) ?>
                </div>
                <div class="hidden w-48 shrink-0 border-l border-rule pl-4 text-xs text-soft lg:block">
                    <span class="block"><?= e(time_ago((string) $topic['last_post_at'])) ?></span>
                    <span class="block">
                        by
                        <?php if ($topic['last_username'] !== null): ?>
                            <a class="font-medium hover:underline" href="<?= e(member_url((string) $topic['last_username'])) ?>"><?= e((string) $topic['last_username']) ?></a>
                        <?php else: ?>
                            <span class="font-medium">a departed member</span>
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
            <a class="btn btn-ghost btn-sm" href="<?= e(board_url((string) $board['slug'], $page - 1)) ?>">Previous</a>
        <?php endif; ?>
        <?php foreach (page_window($page, $totalPages) as $p): ?>
            <a href="<?= e(board_url((string) $board['slug'], $p)) ?>"
               class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-ghost' ?>"
               <?= $p === $page ? 'aria-current="page"' : '' ?>><?= e((string) $p) ?></a>
        <?php endforeach; ?>
        <?php if ($page < $totalPages): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(board_url((string) $board['slug'], $page + 1)) ?>">Next</a>
        <?php endif; ?>
    </nav>
    <p class="mt-2 text-center text-xs text-soft">Page <?= e((string) $page) ?> of <?= e((string) $totalPages) ?></p>
<?php endif; ?>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
