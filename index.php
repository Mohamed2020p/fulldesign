<?php
/**
 * GodsForum - Board index.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$pageTitle       = 'Board index';
$pageDescription = SITE_TAGLINE;

$categories = db_all('SELECT id, name, description FROM categories ORDER BY position ASC, id ASC');

$boards = db_all(
    'SELECT b.id, b.category_id, b.name, b.description, b.icon, b.is_locked,
            (SELECT COUNT(*) FROM topics t WHERE t.board_id = b.id) AS topic_count,
            (SELECT COUNT(*) FROM posts p
               JOIN topics t2 ON t2.id = p.topic_id
              WHERE t2.board_id = b.id) AS post_count,
            lt.id       AS last_topic_id,
            lt.title    AS last_topic_title,
            lp.created_at AS last_post_at,
            lu.id       AS last_user_id,
            lu.username AS last_username
       FROM boards b
       LEFT JOIN posts lp ON lp.id = (
            SELECT p2.id FROM posts p2
              JOIN topics t3 ON t3.id = p2.topic_id
             WHERE t3.board_id = b.id
             ORDER BY p2.created_at DESC, p2.id DESC
             LIMIT 1)
       LEFT JOIN topics lt ON lt.id = lp.topic_id
       LEFT JOIN users  lu ON lu.id = lp.user_id
      ORDER BY b.position ASC, b.id ASC'
);

/** @var array<int, array<int, array<string, mixed>>> $boardsByCategory */
$boardsByCategory = [];
foreach ($boards as $board) {
    $boardsByCategory[(int) $board['category_id']][] = $board;
}

$stats = db_one(
    'SELECT
        (SELECT COUNT(*) FROM topics) AS topics,
        (SELECT COUNT(*) FROM posts)  AS posts,
        (SELECT COUNT(*) FROM users WHERE status = "active") AS members,
        (SELECT username FROM users WHERE status = "active" ORDER BY created_at DESC LIMIT 1) AS newest_member'
) ?? [];

$onlineCount = (int) db_value(
    'SELECT COUNT(*) FROM users WHERE last_seen_at > (NOW() - INTERVAL 15 MINUTE)',
    [],
    0
);

$latestTopics = db_all(
    'SELECT t.id, t.title, t.last_post_at, t.reply_count, b.name AS board_name, u.username
       FROM topics t
       JOIN boards b ON b.id = t.board_id
       LEFT JOIN users u ON u.id = t.user_id
      ORDER BY t.last_post_at DESC
      LIMIT 6'
);

require __DIR__ . '/includes/header.php';
?>

<section class="panel mb-6 overflow-hidden">
    <div class="relative">
        <img src="<?= e(url('assets/img/banner.png')) ?>" alt=""
             class="h-36 w-full object-cover object-center opacity-90 sm:h-44">
        <div class="absolute inset-0 bg-gradient-to-r from-parchment-light via-parchment-light/85 to-transparent"></div>
        <div class="absolute inset-0 flex flex-col justify-center px-5 sm:px-8">
            <p class="text-[11px] uppercase tracking-[0.3em] text-crimson">Established for plain conversation</p>
            <h1 class="mt-1 font-serif text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
                Welcome to the hall
            </h1>
            <p class="mt-1 max-w-md text-sm text-ink-soft">
                <?= e(setting('welcome_message', 'A plain, fast message board. Threads stay where you left them.')) ?>
            </p>
        </div>
    </div>
    <div class="grid grid-cols-2 divide-x divide-rule border-t border-rule text-center sm:grid-cols-4">
        <?php
        $tiles = [
            ['label' => 'Topics',  'value' => number_format((int) ($stats['topics'] ?? 0))],
            ['label' => 'Posts',   'value' => number_format((int) ($stats['posts'] ?? 0))],
            ['label' => 'Members', 'value' => number_format((int) ($stats['members'] ?? 0))],
            ['label' => 'Online',  'value' => number_format($onlineCount)],
        ];
        foreach ($tiles as $tile): ?>
            <div class="px-3 py-3">
                <div class="font-serif text-xl font-semibold text-ink"><?= e($tile['value']) ?></div>
                <div class="text-[10px] uppercase tracking-[0.2em] text-ink-soft"><?= e($tile['label']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
    <div class="space-y-6">
        <?php if ($categories === []): ?>
            <div class="panel p-8 text-center text-sm text-ink-soft">
                No categories have been created yet.
            </div>
        <?php endif; ?>

        <?php foreach ($categories as $category): ?>
            <?php $catBoards = $boardsByCategory[(int) $category['id']] ?? []; ?>
            <section class="panel">
                <h2 class="panel-head">
                    <span class="material-icons-outlined text-[18px]" aria-hidden="true">folder_open</span>
                    <?= e((string) $category['name']) ?>
                </h2>

                <?php if ((string) $category['description'] !== ''): ?>
                    <p class="border-b border-rule bg-parchment-dark px-4 py-2 text-xs text-ink-soft">
                        <?= e((string) $category['description']) ?>
                    </p>
                <?php endif; ?>

                <div class="divide-rule">
                    <?php if ($catBoards === []): ?>
                        <p class="px-4 py-5 text-sm text-ink-soft">This category has no boards yet.</p>
                    <?php endif; ?>

                    <?php foreach ($catBoards as $board): ?>
                        <article class="flex flex-wrap items-center gap-4 px-4 py-3 transition-colors hover:bg-parchment-dark/50">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center border border-rule bg-parchment-dark text-ink">
                                <span class="material-icons-outlined text-[20px]" aria-hidden="true"><?= e((string) $board['icon']) ?></span>
                            </span>

                            <div class="min-w-0 flex-1">
                                <h3 class="text-[15px] font-semibold">
                                    <a class="row-link" href="<?= e(url('board.php?id=' . (int) $board['id'])) ?>">
                                        <?= e((string) $board['name']) ?>
                                    </a>
                                    <?php if ((int) $board['is_locked'] === 1): ?>
                                        <span class="tag tag-crimson ml-1">Locked</span>
                                    <?php endif; ?>
                                </h3>
                                <p class="mt-0.5 text-xs leading-relaxed text-ink-soft"><?= e((string) $board['description']) ?></p>
                            </div>

                            <dl class="hidden w-28 shrink-0 text-center text-xs text-ink-soft sm:block">
                                <div><dt class="inline">Topics</dt> <dd class="inline font-semibold text-ink"><?= e(number_short((int) $board['topic_count'])) ?></dd></div>
                                <div><dt class="inline">Posts</dt> <dd class="inline font-semibold text-ink"><?= e(number_short((int) $board['post_count'])) ?></dd></div>
                            </dl>

                            <div class="hidden w-56 shrink-0 border-l border-rule pl-4 text-xs text-ink-soft lg:block">
                                <?php if ($board['last_topic_id'] !== null): ?>
                                    <a class="row-link block truncate text-[13px]"
                                       href="<?= e(topic_url((int) $board['last_topic_id'], (string) $board['last_topic_title'])) ?>">
                                        <?= e(excerpt((string) $board['last_topic_title'], 34)) ?>
                                    </a>
                                    <span class="mt-0.5 block">
                                        by
                                        <?php if ($board['last_user_id'] !== null): ?>
                                            <a class="font-medium text-ink hover:text-crimson hover:underline"
                                               href="<?= e(url('profile.php?id=' . (int) $board['last_user_id'])) ?>"><?= e((string) $board['last_username']) ?></a>
                                        <?php else: ?>
                                            <span class="font-medium text-ink">a departed member</span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="block"><?= e(time_ago((string) $board['last_post_at'])) ?></span>
                                <?php else: ?>
                                    <span class="italic">No posts yet</span>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

    <aside class="space-y-6">
        <section class="panel">
            <h2 class="panel-head">
                <span class="material-icons-outlined text-[18px]" aria-hidden="true">schedule</span>
                Latest activity
            </h2>
            <ul class="divide-rule">
                <?php foreach ($latestTopics as $topic): ?>
                    <li class="px-4 py-2.5">
                        <a class="row-link block text-[13px] leading-snug"
                           href="<?= e(topic_url((int) $topic['id'], (string) $topic['title'])) ?>">
                            <?= e(excerpt((string) $topic['title'], 52)) ?>
                        </a>
                        <p class="mt-0.5 text-[11px] text-ink-soft">
                            <?= e((string) $topic['board_name']) ?> &middot; <?= e(time_ago((string) $topic['last_post_at'])) ?>
                        </p>
                    </li>
                <?php endforeach; ?>
                <?php if ($latestTopics === []): ?>
                    <li class="px-4 py-4 text-xs italic text-ink-soft">Nothing has been posted yet.</li>
                <?php endif; ?>
            </ul>
        </section>

        <section class="panel">
            <h2 class="panel-head">
                <span class="material-icons-outlined text-[18px]" aria-hidden="true">info</span>
                Board information
            </h2>
            <dl class="space-y-2 px-4 py-3 text-xs text-ink-soft">
                <div class="flex justify-between">
                    <dt>Newest member</dt>
                    <dd class="font-medium text-ink"><?= e((string) ($stats['newest_member'] ?? 'nobody yet')) ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt>Active in 15 min</dt>
                    <dd class="font-medium text-ink"><?= e((string) $onlineCount) ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt>Registration</dt>
                    <dd class="font-medium text-ink"><?= setting('registration_open', '1') === '1' ? 'Open' : 'Closed' ?></dd>
                </div>
            </dl>
            <?php if (!is_logged_in()): ?>
                <div class="border-t border-rule px-4 py-3">
                    <a href="<?= e(url('register.php')) ?>" class="btn btn-gold w-full">
                        <span class="material-icons-outlined text-[18px]" aria-hidden="true">person_add</span>
                        Join the hall
                    </a>
                </div>
            <?php endif; ?>
        </section>
    </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
