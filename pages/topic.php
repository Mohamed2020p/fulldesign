<?php
/**
 * GodsForum - A single topic with its posts, plus the quick reply form.
 */

declare(strict_types=1);

// This page is a route target, not a public address. It is reached only
// through the front controller, which has already resolved the request.
if (!defined('GF_ROUTER')) {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/functions.php';

/** @var array<string, string> $route */
$topic = db_one(
    'SELECT t.id, t.title, t.slug, t.is_pinned, t.is_locked, t.view_count, t.reply_count, t.created_at,
            t.user_id AS author_id,
            b.id AS board_id, b.name AS board_name, b.slug AS board_slug, b.is_locked AS board_locked,
            c.name AS category_name
       FROM topics t
       JOIN boards b ON b.id = t.board_id
       JOIN categories c ON c.id = b.category_id
      WHERE t.slug = :slug LIMIT 1',
    ['slug' => (string) ($route['slug'] ?? '')]
);

if ($topic === null) {
    router_not_found();
}

$topicId   = (int) $topic['id'];
$topicSlug = (string) $topic['slug'];
$boardUrl  = board_url((string) $topic['board_slug']);

$user = current_user();

// ---------------------------------------------------------------------------
// Reply submission
// ---------------------------------------------------------------------------
$replyError = '';
$replyDraft = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_post_csrf();
    $user = require_login();
    $replyDraft = post_string('body');

    $locked = (int) $topic['is_locked'] === 1 || (int) $topic['board_locked'] === 1;

    if ($locked && !is_admin()) {
        $replyError = 'This topic is locked, no further replies are accepted.';
    } elseif (mb_strlen($replyDraft) < 2) {
        $replyError = 'Your reply is too short.';
    } elseif (mb_strlen($replyDraft) > 20000) {
        $replyError = 'Your reply is too long, the limit is 20000 characters.';
    } else {
        $lastPostAt = db_value(
            'SELECT created_at FROM posts WHERE user_id = :u ORDER BY id DESC LIMIT 1',
            ['u' => (int) $user['id']]
        );

        if (is_string($lastPostAt) && (time() - (int) strtotime($lastPostAt)) < POST_FLOOD_SECONDS) {
            $replyError = 'You are posting too quickly. Please wait a few seconds and try again.';
        } else {
            db_query(
                'INSERT INTO posts (ref, topic_id, user_id, body, ip_address) VALUES (:r, :t, :u, :b, :ip)',
                [
                    'r'  => generate_ref(),
                    't'  => (int) $topic['id'],
                    'u'  => (int) $user['id'],
                    'b'  => $replyDraft,
                    'ip' => client_ip(),
                ]
            );
            recount_topic((int) $topic['id']);
            recount_user_posts((int) $user['id']);

            flash('success', 'Your reply has been posted.');
            $lastPage = max(1, (int) ceil(
                ((int) db_value('SELECT COUNT(*) FROM posts WHERE topic_id = :t', ['t' => (int) $topic['id']], 1))
                / POSTS_PER_PAGE
            ));
            redirect(topic_url($topicSlug, $lastPage) . '#bottom');
        }
    }
}

// ---------------------------------------------------------------------------
// Read the posts
// ---------------------------------------------------------------------------
$totalPosts = (int) db_value('SELECT COUNT(*) FROM posts WHERE topic_id = :t', ['t' => $topicId], 0);
$totalPages = max(1, (int) ceil($totalPosts / POSTS_PER_PAGE));
$page       = min(max(1, (int) ($route['page'] ?? 1)), $totalPages);
$offset     = ($page - 1) * POSTS_PER_PAGE;

$posts = db_all(
    'SELECT p.id, p.ref, p.body, p.created_at, p.edited_at,
            u.id AS user_id, u.username, u.role, u.avatar, u.signature, u.post_count, u.created_at AS joined_at
       FROM posts p
       LEFT JOIN users u ON u.id = p.user_id
      WHERE p.topic_id = :t
      ORDER BY p.created_at ASC, p.id ASC
      LIMIT :limit OFFSET :offset',
    ['t' => $topicId, 'limit' => POSTS_PER_PAGE, 'offset' => $offset]
);

// One view per session per topic keeps the counter honest enough.
gf_start_session();
$viewedKey = 'viewed_topic_' . $topicId;
if (!isset($_SESSION[$viewedKey])) {
    db_query('UPDATE topics SET view_count = view_count + 1 WHERE id = :id', ['id' => $topicId]);
    $_SESSION[$viewedKey] = true;
}

$locked     = (int) $topic['is_locked'] === 1 || (int) $topic['board_locked'] === 1;
$canReply   = is_logged_in() && (!$locked || is_admin());

$pageTitle       = (string) $topic['title'];
$pageDescription = 'Discussion in ' . (string) $topic['board_name'];
$breadcrumbs     = [
    ['label' => (string) $topic['category_name']],
    ['label' => (string) $topic['board_name'], 'url' => $boardUrl],
    ['label' => excerpt((string) $topic['title'], 48)],
];

require dirname(__DIR__) . '/includes/header.php';
?>

<div class="mb-5 flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="max-w-2xl font-serif text-2xl font-semibold leading-tight">
            <?= e((string) $topic['title']) ?>
        </h1>
        <p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-soft">
            <?php if ((int) $topic['is_pinned'] === 1): ?><span class="tag tag-gold">Pinned</span><?php endif; ?>
            <?php if ($locked): ?><span class="tag tag-crimson">Locked</span><?php endif; ?>
            <span><?= e(number_format($totalPosts)) ?> post<?= $totalPosts === 1 ? '' : 's' ?></span>
            <span aria-hidden="true">&middot;</span>
            <span><?= e(number_format((int) $topic['view_count'])) ?> views</span>
            <span aria-hidden="true">&middot;</span>
            <span>opened <?= e(full_date((string) $topic['created_at'])) ?></span>
        </p>
    </div>

    <div class="flex flex-wrap gap-2">
        <a class="btn btn-ghost btn-sm" href="<?= e($boardUrl) ?>">
            <span class="material-icons-outlined text-[16px]" aria-hidden="true">arrow_back</span>
            Back to board
        </a>
        <?php if ($canReply): ?>
            <a class="btn btn-gold btn-sm" href="#reply">
                <span class="material-icons-outlined text-[16px]" aria-hidden="true">reply</span>
                Reply
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="space-y-4">
    <?php foreach ($posts as $index => $post): ?>
        <?php $number = $offset + $index + 1; ?>
        <article id="post-<?= e((string) (int) $post['id']) ?>" class="panel">
            <header class="panel-subhead justify-between">
                <span>#<?= e((string) $number) ?> &middot; <?= e(full_date((string) $post['created_at'])) ?></span>
                <span class="normal-case tracking-normal">
                    <?php if ($post['edited_at'] !== null): ?>
                        <span class="text-[11px] italic text-soft">edited <?= e(time_ago((string) $post['edited_at'])) ?></span>
                    <?php endif; ?>
                </span>
            </header>

            <div class="flex flex-col gap-4 p-4 sm:flex-row">
                <div class="w-full shrink-0 border-b border-rule pb-3 text-center sm:w-40 sm:border-b-0 sm:border-r sm:pb-0 sm:pr-4">
                    <img src="<?= e(avatar_url(isset($post['avatar']) ? (string) $post['avatar'] : null)) ?>" alt=""
                         class="mx-auto h-16 w-16 border border-rule object-cover" style="background-color: var(--page-alt)">
                    <?php if ($post['user_id'] !== null): ?>
                        <a class="row-link mt-2 block text-sm font-semibold hover:underline"
                           href="<?= e(member_url((string) $post['username'])) ?>"><?= e((string) $post['username']) ?></a>
                        <span class="mt-1 inline-block <?= $post['role'] === 'admin' ? 'tag tag-crimson' : ($post['role'] === 'moderator' ? 'tag tag-forest' : 'tag') ?>">
                            <?= e(role_label((string) $post['role'])) ?>
                        </span>
                        <dl class="mt-2 space-y-0.5 text-[11px] text-soft">
                            <div><dt class="inline">Posts</dt> <dd class="inline font-medium"><?= e(number_format((int) $post['post_count'])) ?></dd></div>
                            <div><dt class="inline">Joined</dt> <dd class="inline font-medium"><?= e(date('M Y', (int) strtotime((string) $post['joined_at']))) ?></dd></div>
                        </dl>
                    <?php else: ?>
                        <p class="mt-2 text-sm font-semibold italic text-soft">Departed member</p>
                    <?php endif; ?>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="prose-post"><?= format_post((string) $post['body']) ?></div>

                    <?php if ($post['user_id'] !== null && (string) $post['signature'] !== ''): ?>
                        <p class="mt-4 border-t border-dashed border-rule pt-2 text-xs italic text-soft">
                            <?= e((string) $post['signature']) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (is_logged_in()): ?>
                        <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-rule pt-3">
                            <?php if ($canReply): ?>
                                <a class="btn btn-ghost btn-sm" href="#reply">
                                    <span class="material-icons-outlined text-[16px]" aria-hidden="true">reply</span> Reply
                                </a>
                            <?php endif; ?>
                            <?php if ($user !== null && ((int) $post['user_id'] === (int) $user['id'] || is_admin())): ?>
                                <a class="btn btn-ghost btn-sm" href="<?= e(url('post/' . rawurlencode((string) $post['ref']) . '/edit')) ?>">
                                    <span class="material-icons-outlined text-[16px]" aria-hidden="true">edit</span> Edit
                                </a>
                            <?php endif; ?>
                            <?php if ($user !== null && (int) $post['user_id'] !== (int) $user['id']): ?>
                                <a class="btn btn-danger btn-sm" href="<?= e(url('post/' . rawurlencode((string) $post['ref']) . '/report')) ?>">
                                    <span class="material-icons-outlined text-[16px]" aria-hidden="true">flag</span> Report
                                </a>
                            <?php endif; ?>
                            <a class="ml-auto text-[11px] text-soft hover:underline"
                               href="<?= e(post_url($topicSlug, (int) $post['id'], $page)) ?>">Permalink</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</div>

<?php if ($totalPages > 1): ?>
    <nav aria-label="Pagination" class="mt-5 flex flex-wrap items-center justify-center gap-1 text-sm">
        <?php if ($page > 1): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(topic_url($topicSlug, $page - 1)) ?>">Previous</a>
        <?php endif; ?>
        <?php foreach (page_window($page, $totalPages) as $p): ?>
            <a class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-ghost' ?>"
               href="<?= e(topic_url($topicSlug, $p)) ?>"
               <?= $p === $page ? 'aria-current="page"' : '' ?>><?= e((string) $p) ?></a>
        <?php endforeach; ?>
        <?php if ($page < $totalPages): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(topic_url($topicSlug, $page + 1)) ?>">Next</a>
        <?php endif; ?>
    </nav>
<?php endif; ?>

<div id="bottom"></div>

<section id="reply" class="panel mt-6">
    <h2 class="panel-head">
        <span class="material-icons-outlined text-[18px]" aria-hidden="true">reply</span>
        Post a reply
    </h2>

    <?php if ($canReply): ?>
        <form method="post" action="<?= e(topic_url($topicSlug, $page)) ?>#reply" class="p-4">
            <?= csrf_field() ?>

            <?php if ($replyError !== ''): ?>
                <p class="alert alert-error mb-3"><?= e($replyError) ?></p>
            <?php endif; ?>

            <label class="field-label" for="body">Your message</label>
            <textarea id="body" name="body" rows="7" required minlength="2" maxlength="20000"
                      class="field-input font-sans"
                      placeholder="Write a considered reply. Plain text only, links are shown as written."><?= e($replyDraft) ?></textarea>
            <p class="field-help">Formatting is plain text. Blank lines separate paragraphs.</p>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <button type="submit" class="btn btn-primary">
                    <span class="material-icons-outlined text-[18px]" aria-hidden="true">send</span>
                    Post reply
                </button>
                <a class="btn btn-ghost" href="<?= e($boardUrl) ?>">Cancel</a>
            </div>
        </form>
    <?php elseif ($locked): ?>
        <p class="p-6 text-center text-sm text-soft">This topic is locked. No further replies are accepted.</p>
    <?php else: ?>
        <p class="p-6 text-center text-sm text-soft">
            <a class="font-medium hover:underline" href="<?= e(url('login')) ?>">Sign in</a>
            or
            <a class="font-medium hover:underline" href="<?= e(url('register')) ?>">register</a>
            to join this discussion.
        </p>
    <?php endif; ?>
</section>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
