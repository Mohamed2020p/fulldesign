<?php
/**
 * GodsForum - Report a post to the staff.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$user   = require_login();
$postId = param_int('post');

$post = $postId > 0
    ? db_one(
        'SELECT p.id, p.body, p.created_at, p.topic_id, t.title AS topic_title, u.username
           FROM posts p
           JOIN topics t ON t.id = p.topic_id
           LEFT JOIN users u ON u.id = p.user_id
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

$errors = [];
$reason = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_post_csrf();
    $reason = post_string('reason');

    if (mb_strlen($reason) < 10 || mb_strlen($reason) > 255) {
        $errors[] = 'Please describe the problem in 10 to 255 characters.';
    }

    $already = (int) db_value(
        'SELECT COUNT(*) FROM reports WHERE post_id = :p AND reporter_id = :u AND status = "open"',
        ['p' => $postId, 'u' => (int) $user['id']],
        0
    );
    if ($already > 0) {
        $errors[] = 'You have already reported this post and the staff are looking at it.';
    }

    if ($errors === []) {
        db_query(
            'INSERT INTO reports (post_id, reporter_id, reason) VALUES (:p, :u, :r)',
            ['p' => $postId, 'u' => (int) $user['id'], 'r' => $reason]
        );

        flash('success', 'Thank you. The staff have been notified.');
        redirect(topic_url((int) $post['topic_id'], (string) $post['topic_title']) . '#post-' . $postId);
    }
}

$pageTitle   = 'Report a post';
$breadcrumbs = [
    ['label' => excerpt((string) $post['topic_title'], 40), 'url' => topic_url((int) $post['topic_id'], (string) $post['topic_title'])],
    ['label' => 'Report'],
];

require __DIR__ . '/includes/header.php';
?>

<div class="mx-auto max-w-2xl">
    <section class="panel">
        <h1 class="panel-head">
            <span class="material-icons-outlined text-[18px]" aria-hidden="true">flag</span>
            Report a post
        </h1>

        <div class="border-b border-rule bg-parchment-dark px-5 py-3 text-xs text-ink-soft">
            <p>
                Post by <span class="font-semibold text-ink"><?= e((string) ($post['username'] ?? 'a departed member')) ?></span>,
                <?= e(full_date((string) $post['created_at'])) ?>
            </p>
            <p class="mt-2 border-l-2 border-rule pl-3 italic"><?= e(excerpt((string) $post['body'], 220)) ?></p>
        </div>

        <form method="post" action="<?= e(url('report.php?post=' . $postId)) ?>" class="space-y-4 p-5">
            <?= csrf_field() ?>

            <?php if ($errors !== []): ?>
                <div class="border-l-4 border-crimson bg-crimson/10 px-3 py-2 text-sm text-crimson">
                    <ul class="list-inside list-disc space-y-1">
                        <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div>
                <label class="field-label" for="reason">What is wrong with this post?</label>
                <textarea class="field-input" id="reason" name="reason" rows="5" required
                          minlength="10" maxlength="255"
                          placeholder="Explain briefly, for example: personal attack, advertising, off topic."><?= e($reason) ?></textarea>
                <p class="field-help">Reports are private and only visible to the staff.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="btn btn-danger">
                    <span class="material-icons-outlined text-[18px]" aria-hidden="true">outlined_flag</span>
                    Send report
                </button>
                <a class="btn btn-ghost" href="<?= e(topic_url((int) $post['topic_id'], (string) $post['topic_title'])) ?>">Cancel</a>
            </div>
        </form>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
