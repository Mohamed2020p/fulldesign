<?php
/**
 * GodsForum - Admin: review and remove individual posts.
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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_post_csrf();

    $postId = post_int('id');
    if (post_string('action') === 'delete' && $postId > 0) {
        $post = db_one('SELECT topic_id, user_id FROM posts WHERE id = :id', ['id' => $postId]);

        if ($post !== null) {
            $isFirst = (int) db_value(
                'SELECT id FROM posts WHERE topic_id = :t ORDER BY created_at ASC, id ASC LIMIT 1',
                ['t' => (int) $post['topic_id']],
                0
            ) === $postId;

            if ($isFirst) {
                db_query('DELETE FROM topics WHERE id = :t', ['t' => (int) $post['topic_id']]);
                log_admin_action((int) $staff['id'], 'delete_topic', 'Opening post #' . $postId . ' removed with its topic.');
                flash('success', 'That was the opening post, so the whole topic has been deleted.');
            } else {
                db_query('DELETE FROM posts WHERE id = :id', ['id' => $postId]);
                recount_topic((int) $post['topic_id']);
                log_admin_action((int) $staff['id'], 'delete_post', 'Post #' . $postId . ' deleted.');
                flash('success', 'The post has been deleted.');
            }

            if ($post['user_id'] !== null) {
                recount_user_posts((int) $post['user_id']);
            }
        }
    }

    redirect(url('admin/posts'));
}

$search = mb_substr(param_string('q'), 0, 100);
$where  = '';
$params = [];

if ($search !== '') {
    $where = 'WHERE p.body LIKE :like';
    $params['like'] = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
}

$perPage    = 15;
$total      = (int) db_value('SELECT COUNT(*) FROM posts p ' . $where, $params, 0);
$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min(max(1, param_int('page', 1)), $totalPages);
$offset     = ($page - 1) * $perPage;

$posts = db_all(
    'SELECT p.id, p.ref, p.body, p.created_at, p.ip_address,
            t.id AS topic_id, t.title, t.slug AS topic_slug,
            u.id AS user_id, u.username
       FROM posts p
       JOIN topics t ON t.id = p.topic_id
       LEFT JOIN users u ON u.id = p.user_id ' . $where . '
      ORDER BY p.created_at DESC
      LIMIT :limit OFFSET :offset',
    $params + ['limit' => $perPage, 'offset' => $offset]
);

admin_header('Posts', 'Every message on the board, newest first.');
?>

<form method="get" action="<?= e(url('admin/posts')) ?>" class="panel mb-5 flex flex-wrap items-end gap-3 p-4">
    <div class="min-w-[16rem] flex-1">
        <label class="field-label" for="q">Search post text</label>
        <input class="field-input" type="search" id="q" name="q" maxlength="100" value="<?= e($search) ?>" placeholder="Any words">
    </div>
    <button type="submit" class="btn btn-primary">Search</button>
    <?php if ($search !== ''): ?>
        <a class="btn btn-ghost" href="<?= e(url('admin/posts')) ?>">Clear</a>
    <?php endif; ?>
</form>

<section class="panel divide-rule">
    <?php foreach ($posts as $post): ?>
        <article class="px-4 py-3">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <a class="row-link text-sm" href="<?= e(topic_url((string) $post['topic_slug'])) ?>#post-<?= e((string) (int) $post['id']) ?>">
                    <?= e(excerpt((string) $post['title'], 70)) ?>
                </a>
                <span class="text-[11px] text-soft">
                    <?php if ($post['user_id'] !== null): ?>
                        <a class="hover:underline" href="<?= e(member_url((string) $post['username'])) ?>"><?= e((string) $post['username']) ?></a>
                    <?php else: ?>
                        departed member
                    <?php endif; ?>
                    &middot; <?= e(full_date((string) $post['created_at'])) ?>
                    &middot; <?= e((string) $post['ip_address']) ?>
                </span>
            </div>

            <p class="mt-1 text-xs leading-relaxed text-soft"><?= e(excerpt((string) $post['body'], 240)) ?></p>

            <div class="mt-2 flex gap-1">
                <a class="btn btn-ghost btn-sm" href="<?= e(url('post/' . rawurlencode((string) $post['ref']) . '/edit')) ?>">Edit</a>
                <form method="post" action="<?= e(url('admin/posts')) ?>" onsubmit="return confirm('Delete this post?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= e((string) (int) $post['id']) ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>

    <?php if ($posts === []): ?>
        <p class="px-4 py-8 text-center text-sm italic text-soft">No posts match.</p>
    <?php endif; ?>
</section>

<?php if ($totalPages > 1): ?>
    <?php $base = 'admin/posts?' . ($search !== '' ? 'q=' . rawurlencode($search) . '&' : ''); ?>
    <nav aria-label="Pagination" class="mt-5 flex flex-wrap items-center justify-center gap-1">
        <?php foreach (page_window($page, $totalPages, 3) as $p): ?>
            <a class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url($base . 'page=' . $p)) ?>"><?= e((string) $p) ?></a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>

<?php admin_footer(); ?>
