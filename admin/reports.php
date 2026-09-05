<?php
/**
 * GodsForum - Admin: the report queue.
 */

declare(strict_types=1);

require_once __DIR__ . '/layout.php';

$staff = require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_post_csrf();

    $action   = post_string('action');
    $reportId = post_int('id');

    if ($reportId > 0) {
        if ($action === 'resolve') {
            db_query('UPDATE reports SET status = "resolved" WHERE id = :id', ['id' => $reportId]);
            log_admin_action((int) $staff['id'], 'resolve_report', 'Report #' . $reportId . ' marked resolved.');
            flash('success', 'The report has been closed.');
        } elseif ($action === 'reopen') {
            db_query('UPDATE reports SET status = "open" WHERE id = :id', ['id' => $reportId]);
            log_admin_action((int) $staff['id'], 'reopen_report', 'Report #' . $reportId . ' reopened.');
            flash('success', 'The report has been reopened.');
        } elseif ($action === 'delete_post') {
            $postId = post_int('post_id');
            $post   = $postId > 0 ? db_one('SELECT topic_id, user_id FROM posts WHERE id = :id', ['id' => $postId]) : null;

            if ($post !== null) {
                db_query('DELETE FROM posts WHERE id = :id', ['id' => $postId]);
                recount_topic((int) $post['topic_id']);
                if ($post['user_id'] !== null) {
                    recount_user_posts((int) $post['user_id']);
                }
                db_query('UPDATE reports SET status = "resolved" WHERE id = :id', ['id' => $reportId]);
                log_admin_action((int) $staff['id'], 'delete_post', 'Post #' . $postId . ' deleted from the report queue.');
                flash('success', 'The reported post has been deleted and the report closed.');
            }
        }
    }

    redirect('admin/reports.php');
}

$filter = param_string('status', 'open');
if (!in_array($filter, ['open', 'resolved', 'all'], true)) {
    $filter = 'open';
}

$where  = $filter === 'all' ? '' : 'WHERE r.status = :status';
$params = $filter === 'all' ? [] : ['status' => $filter];

$reports = db_all(
    'SELECT r.id, r.reason, r.status, r.created_at,
            p.id AS post_id, p.body, p.created_at AS post_created,
            t.id AS topic_id, t.title,
            author.username AS author_name, author.id AS author_id,
            reporter.username AS reporter_name, reporter.id AS reporter_id
       FROM reports r
       JOIN posts p ON p.id = r.post_id
       JOIN topics t ON t.id = p.topic_id
       LEFT JOIN users author ON author.id = p.user_id
       LEFT JOIN users reporter ON reporter.id = r.reporter_id ' . $where . '
      ORDER BY r.created_at DESC
      LIMIT 100',
    $params
);

admin_header('Reports', 'Posts flagged by members for staff attention.');
?>

<nav aria-label="Report filters" class="mb-5 flex flex-wrap gap-1">
    <?php foreach (['open' => 'Open', 'resolved' => 'Resolved', 'all' => 'All'] as $key => $label): ?>
        <a class="btn btn-sm <?= $filter === $key ? 'btn-primary' : 'btn-ghost' ?>"
           href="<?= e(url('admin/reports.php?status=' . $key)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<div class="space-y-4">
    <?php foreach ($reports as $report): ?>
        <article class="panel">
            <header class="panel-subhead justify-between">
                <span>Report #<?= e((string) (int) $report['id']) ?> &middot; <?= e(full_date((string) $report['created_at'])) ?></span>
                <span class="<?= $report['status'] === 'open' ? 'tag tag-crimson' : 'tag tag-forest' ?>">
                    <?= e(ucfirst((string) $report['status'])) ?>
                </span>
            </header>

            <div class="space-y-3 p-4">
                <p class="text-sm text-ink">
                    <span class="font-semibold">Reason:</span> <?= e((string) $report['reason']) ?>
                </p>

                <p class="text-xs text-ink-soft">
                    Reported by
                    <?php if ($report['reporter_id'] !== null): ?>
                        <a class="font-medium text-ink hover:text-crimson hover:underline" href="<?= e(url('profile.php?id=' . (int) $report['reporter_id'])) ?>"><?= e((string) $report['reporter_name']) ?></a>
                    <?php else: ?>
                        <span class="font-medium text-ink">a departed member</span>
                    <?php endif; ?>
                </p>

                <div class="border border-rule bg-parchment-dark/60 p-3">
                    <p class="text-xs text-ink-soft">
                        Post by
                        <?php if ($report['author_id'] !== null): ?>
                            <a class="font-semibold text-ink hover:text-crimson hover:underline" href="<?= e(url('profile.php?id=' . (int) $report['author_id'])) ?>"><?= e((string) $report['author_name']) ?></a>
                        <?php else: ?>
                            <span class="font-semibold text-ink">a departed member</span>
                        <?php endif; ?>
                        in
                        <a class="hover:text-crimson hover:underline" href="<?= e(topic_url((int) $report['topic_id'], (string) $report['title'])) ?>#post-<?= e((string) (int) $report['post_id']) ?>">
                            <?= e(excerpt((string) $report['title'], 60)) ?>
                        </a>
                        &middot; <?= e(full_date((string) $report['post_created'])) ?>
                    </p>
                    <p class="mt-2 text-sm leading-relaxed text-ink"><?= e(excerpt((string) $report['body'], 400)) ?></p>
                </div>

                <div class="flex flex-wrap gap-1">
                    <a class="btn btn-ghost btn-sm" href="<?= e(topic_url((int) $report['topic_id'], (string) $report['title'])) ?>#post-<?= e((string) (int) $report['post_id']) ?>">View in context</a>

                    <?php if ($report['status'] === 'open'): ?>
                        <form method="post" action="<?= e(url('admin/reports.php')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="resolve">
                            <input type="hidden" name="id" value="<?= e((string) (int) $report['id']) ?>">
                            <button type="submit" class="btn btn-primary btn-sm">Mark resolved</button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="<?= e(url('admin/reports.php')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reopen">
                            <input type="hidden" name="id" value="<?= e((string) (int) $report['id']) ?>">
                            <button type="submit" class="btn btn-ghost btn-sm">Reopen</button>
                        </form>
                    <?php endif; ?>

                    <form method="post" action="<?= e(url('admin/reports.php')) ?>" onsubmit="return confirm('Delete the reported post?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_post">
                        <input type="hidden" name="id" value="<?= e((string) (int) $report['id']) ?>">
                        <input type="hidden" name="post_id" value="<?= e((string) (int) $report['post_id']) ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Delete the post</button>
                    </form>
                </div>
            </div>
        </article>
    <?php endforeach; ?>

    <?php if ($reports === []): ?>
        <p class="panel px-4 py-10 text-center text-sm italic text-ink-soft">Nothing in this queue.</p>
    <?php endif; ?>
</div>

<?php admin_footer(); ?>
