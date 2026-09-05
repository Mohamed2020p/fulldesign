<?php
/**
 * GodsForum - Start a new topic in a board.
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
$board = db_one(
    'SELECT b.id, b.name, b.slug, b.is_locked, c.name AS category_name
       FROM boards b JOIN categories c ON c.id = b.category_id
      WHERE b.slug = :slug LIMIT 1',
    ['slug' => (string) ($route['slug'] ?? '')]
);

if ($board === null) {
    router_not_found();
}

$boardId  = (int) $board['id'];
$boardUrl = board_url((string) $board['slug']);

if ((int) $board['is_locked'] === 1 && !is_admin()) {
    flash('error', 'That board is locked, new topics cannot be created there.');
    redirect($boardUrl);
}

$errors = [];
$title  = '';
$body   = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_post_csrf();

    $title = post_string('title');
    $body  = post_string('body');

    if (mb_strlen($title) < 5 || mb_strlen($title) > 140) {
        $errors[] = 'The title must be between 5 and 140 characters.';
    }

    if (mb_strlen($body) < 10) {
        $errors[] = 'The opening message must be at least 10 characters.';
    } elseif (mb_strlen($body) > 20000) {
        $errors[] = 'The opening message is too long, the limit is 20000 characters.';
    }

    $lastPostAt = db_value(
        'SELECT created_at FROM posts WHERE user_id = :u ORDER BY id DESC LIMIT 1',
        ['u' => (int) $user['id']]
    );
    if (is_string($lastPostAt) && (time() - (int) strtotime($lastPostAt)) < POST_FLOOD_SECONDS) {
        $errors[] = 'You are posting too quickly. Please wait a few seconds and try again.';
    }

    if ($errors === []) {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $topicSlug = unique_slug('topics', $title);

            db_query(
                'INSERT INTO topics (board_id, user_id, title, slug) VALUES (:b, :u, :t, :s)',
                ['b' => $boardId, 'u' => (int) $user['id'], 't' => $title, 's' => $topicSlug]
            );
            $topicId = db_insert_id();

            db_query(
                'INSERT INTO posts (ref, topic_id, user_id, body, ip_address) VALUES (:r, :t, :u, :b, :ip)',
                ['r' => generate_ref(), 't' => $topicId, 'u' => (int) $user['id'], 'b' => $body, 'ip' => client_ip()]
            );

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        recount_topic($topicId);
        recount_user_posts((int) $user['id']);

        flash('success', 'Your topic has been created.');
        redirect(topic_url($topicSlug));
    }
}

$pageTitle       = 'New topic';
$pageDescription = 'Start a new topic in ' . (string) $board['name'];
$breadcrumbs     = [
    ['label' => (string) $board['category_name']],
    ['label' => (string) $board['name'], 'url' => $boardUrl],
    ['label' => 'New topic'],
];

require dirname(__DIR__) . '/includes/header.php';
?>

<div class="mx-auto max-w-3xl">
    <section class="panel">
        <h1 class="panel-head">
            <span class="material-icons-outlined text-[18px]" aria-hidden="true">edit_note</span>
            New topic in <?= e((string) $board['name']) ?>
        </h1>

        <form method="post" action="<?= e(url('new_topic.php?board=' . $boardId)) ?>" class="space-y-4 p-5">
            <?= csrf_field() ?>

            <?php if ($errors !== []): ?>
                <div class="alert alert-error">
                    <ul class="list-inside list-disc space-y-1">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div>
                <label class="field-label" for="title">Topic title</label>
                <input class="field-input" type="text" id="title" name="title" required
                       minlength="5" maxlength="140" value="<?= e($title) ?>"
                       placeholder="A clear, specific summary of your question">
                <p class="field-help">Between 5 and 140 characters.</p>
            </div>

            <div>
                <label class="field-label" for="body">Opening message</label>
                <textarea class="field-input" id="body" name="body" rows="12" required
                          minlength="10" maxlength="20000"
                          placeholder="Give the hall enough context to answer well."><?= e($body) ?></textarea>
                <p class="field-help">Plain text. Blank lines separate paragraphs.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="btn btn-primary">
                    <span class="material-icons-outlined text-[18px]" aria-hidden="true">send</span>
                    Publish topic
                </button>
                <a class="btn btn-ghost" href="<?= e($boardUrl) ?>">Cancel</a>
            </div>
        </form>
    </section>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
