<?php
/**
 * GodsForum - Edit an existing post (author or staff only).
 */

declare(strict_types=1);

// This page is a route target, not a public address. It is reached only
// through the front controller, which has already resolved the request.
if (!defined('GF_ROUTER')) {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/functions.php';

$user = require_login();

/** @var array<string, string> $route */
$post = db_one(
    'SELECT p.id, p.ref, p.body, p.user_id, p.topic_id,
            t.title AS topic_title, t.slug AS topic_slug, t.is_locked, t.board_id,
            b.name AS board_name, b.slug AS board_slug, b.is_locked AS board_locked
       FROM posts p
       JOIN topics t ON t.id = p.topic_id
       JOIN boards b ON b.id = t.board_id
      WHERE p.ref = :ref LIMIT 1',
    ['ref' => (string) ($route['ref'] ?? '')]
);

// A post the member is not allowed to edit is reported exactly like a post
// that does not exist, so nothing is learned by probing addresses.
if ($post === null || ((int) $post['user_id'] !== (int) $user['id'] && !is_admin())) {
    router_not_found();
}

$postId    = (int) $post['id'];
$topicSlug = (string) $post['topic_slug'];
$isOwner   = (int) $post['user_id'] === (int) $user['id'];

$locked = ((int) $post['is_locked'] === 1 || (int) $post['board_locked'] === 1) && !is_admin();
if ($locked) {
    flash('error', 'That topic is locked, its posts cannot be edited.');
    redirect(topic_url($topicSlug));
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
        redirect(topic_url($topicSlug) . '#post-' . $postId);
    }
}

$pageTitle   = 'Edit post';
$breadcrumbs = [
    ['label' => (string) $post['board_name'], 'url' => board_url((string) $post['board_slug'])],
    ['label' => excerpt((string) $post['topic_title'], 40), 'url' => topic_url($topicSlug)],
    ['label' => 'Edit post'],
];

require dirname(__DIR__) . '/includes/header.php';
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
                <div class="alert alert-error">
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
                <a class="btn btn-ghost" href="<?= e(topic_url($topicSlug)) ?>">Cancel</a>
            </div>
        </form>
    </section>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
