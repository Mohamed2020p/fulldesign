<?php
/**
 * GodsForum - Admin: moderate topics.
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

    $action  = post_string('action');
    $topicId = post_int('id');

    if ($topicId > 0) {
        switch ($action) {
            case 'pin':
                db_query('UPDATE topics SET is_pinned = 1 - is_pinned WHERE id = :id', ['id' => $topicId]);
                log_admin_action((int) $staff['id'], 'toggle_pin', 'Topic #' . $topicId . ' pin toggled.');
                flash('success', 'The pinned state has been changed.');
                break;

            case 'lock':
                db_query('UPDATE topics SET is_locked = 1 - is_locked WHERE id = :id', ['id' => $topicId]);
                log_admin_action((int) $staff['id'], 'toggle_lock', 'Topic #' . $topicId . ' lock toggled.');
                flash('success', 'The locked state has been changed.');
                break;

            case 'move':
                $boardId = post_int('board_id');
                $exists  = (int) db_value('SELECT COUNT(*) FROM boards WHERE id = :b', ['b' => $boardId], 0);
                if ($exists === 1) {
                    db_query('UPDATE topics SET board_id = :b WHERE id = :id', ['b' => $boardId, 'id' => $topicId]);
                    log_admin_action((int) $staff['id'], 'move_topic', 'Topic #' . $topicId . ' moved to board #' . $boardId . '.');
                    flash('success', 'The topic has been moved.');
                } else {
                    flash('error', 'That board does not exist.');
                }
                break;

            case 'delete':
                db_query('DELETE FROM topics WHERE id = :id', ['id' => $topicId]);
                log_admin_action((int) $staff['id'], 'delete_topic', 'Topic #' . $topicId . ' deleted.');
                flash('success', 'The topic and all of its posts have been deleted.');
                break;
        }
    }

    redirect(url('admin/topics' . (param_int('page', 1) > 1 ? '?page=' . param_int('page', 1) : '')));
}

$boards  = db_all('SELECT id, name FROM boards ORDER BY position ASC, id ASC');

$perPage    = 20;
$total      = (int) db_value('SELECT COUNT(*) FROM topics', [], 0);
$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min(max(1, param_int('page', 1)), $totalPages);
$offset     = ($page - 1) * $perPage;

$topics = db_all(
    'SELECT t.id, t.title, t.slug, t.is_pinned, t.is_locked, t.reply_count, t.view_count, t.created_at,
            b.id AS board_id, b.name AS board_name, u.username
       FROM topics t
       JOIN boards b ON b.id = t.board_id
       LEFT JOIN users u ON u.id = t.user_id
      ORDER BY t.last_post_at DESC
      LIMIT :limit OFFSET :offset',
    ['limit' => $perPage, 'offset' => $offset]
);

admin_header('Topics', 'Pin, lock, move or remove any discussion.');
?>

<section class="panel">
    <div class="panel-subhead">
        <span class="flex-1">Topic</span>
        <span class="hidden w-24 text-center lg:block">Replies</span>
        <span class="w-64 text-right">Moderation</span>
    </div>

    <div class="divide-rule">
        <?php foreach ($topics as $topic): ?>
            <div class="flex flex-wrap items-center gap-3 px-4 py-3">
                <div class="min-w-0 flex-1">
                    <a class="row-link text-sm" href="<?= e(topic_url((string) $topic['slug'])) ?>">
                        <?= e(excerpt((string) $topic['title'], 70)) ?>
                    </a>
                    <p class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[11px] text-soft">
                        <?php if ((int) $topic['is_pinned'] === 1): ?><span class="tag tag-gold">Pinned</span><?php endif; ?>
                        <?php if ((int) $topic['is_locked'] === 1): ?><span class="tag tag-crimson">Locked</span><?php endif; ?>
                        <span><?= e((string) $topic['board_name']) ?> &middot; by <?= e((string) ($topic['username'] ?? 'departed member')) ?> &middot; <?= e(time_ago((string) $topic['created_at'])) ?></span>
                    </p>
                </div>

                <span class="hidden w-24 text-center text-sm font-semibold text-ink lg:block"><?= e((string) (int) $topic['reply_count']) ?></span>

                <div class="flex w-full flex-wrap items-center justify-end gap-1 lg:w-64">
                    <form method="post" action="<?= e(url('admin/topics?page=' . $page)) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= e((string) (int) $topic['id']) ?>">
                        <input type="hidden" name="action" value="pin">
                        <button type="submit" class="btn btn-ghost btn-sm"><?= (int) $topic['is_pinned'] === 1 ? 'Unpin' : 'Pin' ?></button>
                    </form>

                    <form method="post" action="<?= e(url('admin/topics?page=' . $page)) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= e((string) (int) $topic['id']) ?>">
                        <input type="hidden" name="action" value="lock">
                        <button type="submit" class="btn btn-ghost btn-sm"><?= (int) $topic['is_locked'] === 1 ? 'Unlock' : 'Lock' ?></button>
                    </form>

                    <form method="post" action="<?= e(url('admin/topics?page=' . $page)) ?>" class="flex items-center gap-1">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= e((string) (int) $topic['id']) ?>">
                        <input type="hidden" name="action" value="move">
                        <label class="sr-only" for="board-<?= e((string) (int) $topic['id']) ?>">Move to board</label>
                        <select class="field-input w-36 py-1 text-xs" id="board-<?= e((string) (int) $topic['id']) ?>" name="board_id">
                            <?php foreach ($boards as $board): ?>
                                <option value="<?= e((string) (int) $board['id']) ?>" <?= (int) $board['id'] === (int) $topic['board_id'] ? 'selected' : '' ?>>
                                    <?= e((string) $board['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-ghost btn-sm">Move</button>
                    </form>

                    <form method="post" action="<?= e(url('admin/topics?page=' . $page)) ?>"
                          onsubmit="return confirm('Delete this topic and all of its posts?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= e((string) (int) $topic['id']) ?>">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($topics === []): ?>
            <p class="px-4 py-8 text-center text-sm italic text-soft">No topics yet.</p>
        <?php endif; ?>
    </div>
</section>

<?php if ($totalPages > 1): ?>
    <nav aria-label="Pagination" class="mt-5 flex flex-wrap items-center justify-center gap-1">
        <?php foreach (page_window($page, $totalPages, 3) as $p): ?>
            <a class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('admin/topics?page=' . $p)) ?>"><?= e((string) $p) ?></a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>

<?php admin_footer(); ?>
