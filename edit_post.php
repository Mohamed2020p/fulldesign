<?php
/**
 * GodsForum - Edit an existing post (author or staff only).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$user   = require_login();
$postId = param_int('id');

$post = $postId > 0
    ? db_one(
        'SELECT p.id, p.body, p.user_id, p.topic_id,
                t.title AS topic_title, t.is_locked, t.board_id,
                b.name AS board_name, b.is_locked AS board_locked
           FROM posts p
           JOIN topics t ON t.id = p.topic_id
           JOIN boards b ON b.id = t.board_id
          WHERE p.id = :id LIMIT 1',
        ['id' => $postId]
    )
    : null;

if ($post === null) {
    http_response_code(404);
    $pageTitle = 'Post not found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="panel p-10 text-center"><h1 class="font-serif text-xl text-ink">Post not found</h1>'
       . '<a class="btn btn-primary mt-5" href="' . e(url('index.php')) . '">Back to board index</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$isOwner = (int) $post['user_id'] === (int) $user['id'];
if (!$isOwner && !is_admin()) {
    http_response_code(403);
    exit('You may only edit your own posts.');
}

$locked = ((int) $post['is_locked'] === 1 || (int) $post['board_locked'] === 1) && !is_admin();
if ($locked) {
    flash('error', 'That topic is locked, its posts cannot be edited.');
    redirect(ltrim(topic_url((int) $post['topic_id'], (string) $post['topic_title']), '/'));
}

$errors = [];
$body   = (string) $post['body'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_post_csrf();
    $body = post_string('body');

    if (mb_strlen($body) < 2) {
        $errors[] = 'The message is too short.';
    } elseif (mb_strlen($body) > 20000) {
        $errors[] = 'The message is too long, the limit is 20000 characters.';
    }

    if ($errors === []) {
        db_query(
            'UPDATE posts SET body = :b, edited_at = NOW() WHERE id = :id',
            ['b' => $body, 'id' => $postId]
        );

        if (!$isOwner) {
            log_admin_action((int) $user['id'], 'edit_post', 'Post #' . $postId . ' edited by staff.');
        }

        flash('success', 'The post has been updated.');
        redirect(ltrim(topic_url((int) $post['topic_id'], (string) $post['topic_title']), '/') . '#post-' . $postId);
    }
}

$pageTitle   = 'Edit post';
$breadcrumbs = [
    ['label' => (string) $post['board_name'], 'url' => url('board.php?id=' . (int) $post['board_id'])],
    ['label' => excerpt((string) $post['topic_title'], 40), 'url' => topic_url((int) $post['topic_id'], (string) $post['topic_title'])],
    ['label' => 'Edit post'],
];

require __DIR__ . '/includes/header.php';
?>

<div class="mx-auto max-w-3xl">
    <section class="panel">
        <h1 class="panel-head">
            <span class="material-icons-outlined text-[18px]" aria-hidden="true">edit</span>
            Edit post
        </h1>

        <form method="post" action="<?= e(url('edit_post.php?id=' . $postId)) ?>" class="space-y-4 p-5">
            <?= csrf_field() ?>

            <?php if ($errors !== []): ?>
                <div class="border-l-4 border-crimson bg-crimson/10 px-3 py-2 text-sm text-crimson">
                    <ul class="list-inside list-disc space-y-1">
                        <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div>
                <label class="field-label" for="body">Message</label>
                <textarea class="field-input" id="body" name="body" rows="12" required
                          minlength="2" maxlength="20000"><?= e($body) ?></textarea>
                <p class="field-help">An "edited" note will be shown under the post.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="btn btn-primary">
                    <span class="material-icons-outlined text-[18px]" aria-hidden="true">save</span>
                    Save changes
                </button>
                <a class="btn btn-ghost" href="<?= e(topic_url((int) $post['topic_id'], (string) $post['topic_title'])) ?>">Cancel</a>
            </div>
        </form>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
