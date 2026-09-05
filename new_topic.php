<?php
/**
 * GodsForum - Start a new topic in a board.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$user    = require_login();
$boardId = param_int('board');

$board = $boardId > 0
    ? db_one(
        'SELECT b.id, b.name, b.is_locked, c.name AS category_name
           FROM boards b JOIN categories c ON c.id = b.category_id
          WHERE b.id = :id LIMIT 1',
        ['id' => $boardId]
    )
    : null;

if ($board === null) {
    http_response_code(404);
    $pageTitle = 'Board not found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="panel p-10 text-center"><h1 class="font-serif text-xl text-ink">Board not found</h1>'
       . '<a class="btn btn-primary mt-5" href="' . e(url('index.php')) . '">Back to board index</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

if ((int) $board['is_locked'] === 1 && !is_admin()) {
    flash('error', 'That board is locked, new topics cannot be created there.');
    redirect('board.php?id=' . $boardId);
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
            db_query(
                'INSERT INTO topics (board_id, user_id, title) VALUES (:b, :u, :t)',
                ['b' => $boardId, 'u' => (int) $user['id'], 't' => $title]
            );
            $topicId = db_insert_id();

            db_query(
                'INSERT INTO posts (topic_id, user_id, body, ip_address) VALUES (:t, :u, :b, :ip)',
                ['t' => $topicId, 'u' => (int) $user['id'], 'b' => $body, 'ip' => client_ip()]
            );

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        recount_topic($topicId);
        recount_user_posts((int) $user['id']);

        flash('success', 'Your topic has been created.');
        redirect(topic_url($topicId, $title));
    }
}

$pageTitle       = 'New topic';
$pageDescription = 'Start a new topic in ' . (string) $board['name'];
$breadcrumbs     = [
    ['label' => (string) $board['category_name']],
    ['label' => (string) $board['name'], 'url' => url('board.php?id=' . $boardId)],
    ['label' => 'New topic'],
];

require __DIR__ . '/includes/header.php';
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
                <div class="border-l-4 border-crimson bg-crimson/10 px-3 py-2 text-sm text-crimson">
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
                <a class="btn btn-ghost" href="<?= e(url('board.php?id=' . $boardId)) ?>">Cancel</a>
            </div>
        </form>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
