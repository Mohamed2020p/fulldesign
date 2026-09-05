<?php
/**
 * GodsForum - Search topics and posts.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$query = mb_substr(param_string('q'), 0, 120);
$scope = param_string('in', 'all');
if (!in_array($scope, ['all', 'titles', 'posts'], true)) {
    $scope = 'all';
}

$results    = [];
$total      = 0;
$page       = max(1, param_int('page', 1));
$perPage    = 15;
$totalPages = 1;

if (mb_strlen($query) >= 3) {
    $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $query) . '%';

    $condition = match ($scope) {
        'titles' => 't.title LIKE :like',
        'posts'  => 'p.body LIKE :like',
        default  => '(t.title LIKE :like OR p.body LIKE :like)',
    };

    $total = (int) db_value(
        'SELECT COUNT(*) FROM posts p JOIN topics t ON t.id = p.topic_id WHERE ' . $condition,
        ['like' => $like],
        0
    );

    $totalPages = max(1, (int) ceil($total / $perPage));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $perPage;

    $results = db_all(
        'SELECT p.id AS post_id, p.body, p.created_at,
                t.id AS topic_id, t.title,
                b.name AS board_name, b.id AS board_id,
                u.id AS user_id, u.username
           FROM posts p
           JOIN topics t ON t.id = p.topic_id
           JOIN boards b ON b.id = t.board_id
           LEFT JOIN users u ON u.id = p.user_id
          WHERE ' . $condition . '
          ORDER BY p.created_at DESC
          LIMIT :limit OFFSET :offset',
        ['like' => $like, 'limit' => $perPage, 'offset' => $offset]
    );
}

$pageTitle       = 'Search';
$pageDescription = 'Search every topic and post on GodsForum.';
$breadcrumbs     = [['label' => 'Search']];

require __DIR__ . '/includes/header.php';
?>

<section class="panel mb-6">
    <h1 class="panel-head">
        <span class="material-icons-outlined text-[18px]" aria-hidden="true">search</span>
        Search the board
    </h1>

    <form method="get" action="<?= e(url('search.php')) ?>" class="flex flex-wrap items-end gap-3 p-5">
        <div class="min-w-[16rem] flex-1">
            <label class="field-label" for="q">Words to look for</label>
            <input class="field-input" type="search" id="q" name="q" value="<?= e($query) ?>"
                   minlength="3" maxlength="120" required placeholder="prepared statements">
            <p class="field-help">At least three characters.</p>
        </div>
        <div>
            <label class="field-label" for="in">Search in</label>
            <select class="field-input w-44" id="in" name="in">
                <option value="all"    <?= $scope === 'all'    ? 'selected' : '' ?>>Titles and posts</option>
                <option value="titles" <?= $scope === 'titles' ? 'selected' : '' ?>>Titles only</option>
                <option value="posts"  <?= $scope === 'posts'  ? 'selected' : '' ?>>Post text only</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">
            <span class="material-icons-outlined text-[18px]" aria-hidden="true">travel_explore</span>
            Search
        </button>
    </form>
</section>

<?php if ($query !== '' && mb_strlen($query) < 3): ?>
    <p class="panel px-4 py-6 text-center text-sm text-ink-soft">Please type at least three characters.</p>
<?php elseif ($query !== ''): ?>
    <p class="mb-3 text-sm text-ink-soft">
        <?= e(number_format($total)) ?> result<?= $total === 1 ? '' : 's' ?> for
        <span class="font-semibold text-ink">&ldquo;<?= e($query) ?>&rdquo;</span>
    </p>

    <div class="panel divide-rule">
        <?php foreach ($results as $result): ?>
            <article class="px-4 py-3 transition-colors hover:bg-parchment-dark/40">
                <h2 class="text-[15px] font-semibold">
                    <a class="row-link" href="<?= e(topic_url((int) $result['topic_id'], (string) $result['title'])) ?>#post-<?= e((string) (int) $result['post_id']) ?>">
                        <?= e((string) $result['title']) ?>
                    </a>
                </h2>
                <p class="mt-1 text-sm text-ink-soft"><?= e(excerpt((string) $result['body'], 200)) ?></p>
                <p class="mt-1 text-[11px] text-ink-soft">
                    <a class="hover:text-crimson hover:underline" href="<?= e(url('board.php?id=' . (int) $result['board_id'])) ?>"><?= e((string) $result['board_name']) ?></a>
                    &middot; by
                    <?php if ($result['user_id'] !== null): ?>
                        <a class="font-medium text-ink hover:text-crimson hover:underline" href="<?= e(url('profile.php?id=' . (int) $result['user_id'])) ?>"><?= e((string) $result['username']) ?></a>
                    <?php else: ?>
                        <span class="font-medium text-ink">a departed member</span>
                    <?php endif; ?>
                    &middot; <?= e(time_ago((string) $result['created_at'])) ?>
                </p>
            </article>
        <?php endforeach; ?>

        <?php if ($results === []): ?>
            <p class="px-4 py-10 text-center text-sm text-ink-soft">Nothing matched that search.</p>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <?php $base = 'search.php?q=' . rawurlencode($query) . '&in=' . rawurlencode($scope); ?>
        <nav aria-label="Pagination" class="mt-5 flex flex-wrap items-center justify-center gap-1">
            <?php if ($page > 1): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(url($base . '&page=' . ($page - 1))) ?>">Previous</a>
            <?php endif; ?>
            <?php foreach (page_window($page, $totalPages) as $p): ?>
                <a class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url($base . '&page=' . $p)) ?>"><?= e((string) $p) ?></a>
            <?php endforeach; ?>
            <?php if ($page < $totalPages): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(url($base . '&page=' . ($page + 1))) ?>">Next</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php else: ?>
    <p class="panel px-4 py-10 text-center text-sm text-ink-soft">Type something above to search the archive.</p>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
