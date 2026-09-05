<?php
/**
 * GodsForum - Member profile, and account settings for the owner.
 */

declare(strict_types=1);

// This page is a route target, not a public address. It is reached only
// through the front controller, which has already resolved the request.
if (!defined('GF_ROUTER')) {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/functions.php';

$viewer = current_user();

/** @var array<string, string> $route */
$profile = db_one(
    'SELECT id, username, role, status, signature, avatar, post_count, created_at, last_seen_at
       FROM users WHERE username = :name LIMIT 1',
    ['name' => (string) ($route['username'] ?? '')]
);

if ($profile === null) {
    router_not_found();
}

$profileId  = (int) $profile['id'];
$profileUrl = member_url((string) $profile['username']);

$isOwner = $viewer !== null && (int) $viewer['id'] === (int) $profile['id'];
$errors  = [];
$notices = [];

// ---------------------------------------------------------------------------
// Owner-only account updates
// ---------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_post_csrf();

    if (!$isOwner) {
        http_response_code(403);
        exit('You may only edit your own profile.');
    }

    $action = post_string('action');

    if ($action === 'signature') {
        $signature = post_string('signature');

        if (mb_strlen($signature) > 255) {
            $errors[] = 'The signature may not exceed 255 characters.';
        } else {
            db_query('UPDATE users SET signature = :s WHERE id = :id', ['s' => $signature, 'id' => $profileId]);
            flash('success', 'Your signature has been saved.');
            redirect($profileUrl);
        }
    } elseif ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirm'] ?? '');

        $hash = (string) db_value('SELECT password_hash FROM users WHERE id = :id', ['id' => $profileId], '');

        if ($hash === '' || !password_verify($current, $hash)) {
            $errors[] = 'Your current password is not correct.';
        } elseif (mb_strlen($new) < 8 || preg_match('/[A-Za-z]/', $new) !== 1 || preg_match('/\d/', $new) !== 1) {
            $errors[] = 'The new password must be at least 8 characters with letters and numbers.';
        } elseif ($new !== $confirm) {
            $errors[] = 'The two new passwords do not match.';
        } else {
            db_query(
                'UPDATE users SET password_hash = :p WHERE id = :id',
                ['p' => password_hash($new, PASSWORD_DEFAULT), 'id' => $profileId]
            );
            flash('success', 'Your password has been changed.');
            redirect($profileUrl);
        }
    } elseif ($action === 'avatar') {
        $file = $_FILES['avatar'] ?? null;

        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Please choose an image file first.';
        } elseif ((int) $file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'The upload failed. Please try again.';
        } elseif ((int) $file['size'] > AVATAR_MAX_BYTES) {
            $errors[] = 'The image is larger than 2 MB.';
        } elseif (!is_uploaded_file((string) $file['tmp_name'])) {
            $errors[] = 'The upload could not be verified.';
        } else {
            $info = @getimagesize((string) $file['tmp_name']);
            $allowed = [
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_PNG  => 'png',
                IMAGETYPE_GIF  => 'gif',
                IMAGETYPE_WEBP => 'webp',
            ];

            if ($info === false || !isset($allowed[$info[2]])) {
                $errors[] = 'Only JPEG, PNG, GIF and WebP images are accepted.';
            } else {
                if (!is_dir(AVATAR_DIR)) {
                    @mkdir(AVATAR_DIR, 0775, true);
                }

                $filename = 'u' . $profileId . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$info[2]];
                $target   = AVATAR_DIR . '/' . $filename;

                if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
                    $errors[] = 'The image could not be stored on the server.';
                } else {
                    @chmod($target, 0644);

                    $previous = isset($profile['avatar']) ? (string) $profile['avatar'] : '';
                    if ($previous !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $previous) === 1) {
                        @unlink(AVATAR_DIR . '/' . $previous);
                    }

                    db_query('UPDATE users SET avatar = :a WHERE id = :id', ['a' => $filename, 'id' => $profileId]);
                    flash('success', 'Your avatar has been updated.');
                    redirect($profileUrl);
                }
            }
        }
    }
}

$recentTopics = db_all(
    'SELECT t.id, t.title, t.slug, t.created_at, t.reply_count, b.name AS board_name
       FROM topics t JOIN boards b ON b.id = t.board_id
      WHERE t.user_id = :u
      ORDER BY t.created_at DESC LIMIT 8',
    ['u' => $profileId]
);

$recentPosts = db_all(
    'SELECT p.id, p.body, p.created_at, t.id AS topic_id, t.title AS topic_title, t.slug AS topic_slug
       FROM posts p JOIN topics t ON t.id = p.topic_id
      WHERE p.user_id = :u
      ORDER BY p.created_at DESC LIMIT 8',
    ['u' => $profileId]
);

$pageTitle   = (string) $profile['username'];
$pageDescription = 'Profile of ' . (string) $profile['username'];
$breadcrumbs = [
    ['label' => 'Members', 'url' => url('members')],
    ['label' => (string) $profile['username']],
];

require dirname(__DIR__) . '/includes/header.php';
?>

<?php if ($errors !== []): ?>
    <div class="alert alert-error mb-4">
        <ul class="list-inside list-disc space-y-1">
            <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="grid gap-6 lg:grid-cols-[20rem_minmax(0,1fr)]">
    <aside class="space-y-6">
        <section class="panel">
            <h1 class="panel-head">
                <span class="material-icons-outlined text-[18px]" aria-hidden="true">account_circle</span>
                Member profile
            </h1>
            <div class="p-5 text-center">
                <img src="<?= e(avatar_url(isset($profile['avatar']) ? (string) $profile['avatar'] : null)) ?>" alt=""
                     class="mx-auto h-24 w-24 border border-rule object-cover" style="background-color: var(--page-alt)">
                <h2 class="mt-3 font-serif text-xl font-semibold"><?= e((string) $profile['username']) ?></h2>
                <p class="mt-1">
                    <span class="<?= $profile['role'] === 'admin' ? 'tag tag-crimson' : ($profile['role'] === 'moderator' ? 'tag tag-forest' : 'tag') ?>">
                        <?= e(role_label((string) $profile['role'])) ?>
                    </span>
                    <?php if ($profile['status'] === 'banned'): ?>
                        <span class="tag tag-crimson">Suspended</span>
                    <?php endif; ?>
                </p>

                <?php if ((string) $profile['signature'] !== ''): ?>
                    <p class="mt-3 border-t border-dashed border-rule pt-3 text-xs italic text-soft">
                        <?= e((string) $profile['signature']) ?>
                    </p>
                <?php endif; ?>
            </div>

            <dl class="divide-rule border-t border-rule text-sm">
                <div class="flex justify-between px-5 py-2">
                    <dt class="text-soft">Posts</dt>
                    <dd class="font-semibold"><?= e(number_format((int) $profile['post_count'])) ?></dd>
                </div>
                <div class="flex justify-between px-5 py-2">
                    <dt class="text-soft">Joined</dt>
                    <dd class="font-semibold"><?= e(date('j M Y', (int) strtotime((string) $profile['created_at']))) ?></dd>
                </div>
                <div class="flex justify-between px-5 py-2">
                    <dt class="text-soft">Last seen</dt>
                    <dd class="font-semibold"><?= e(time_ago(isset($profile['last_seen_at']) ? (string) $profile['last_seen_at'] : null)) ?></dd>
                </div>
            </dl>
        </section>

        <?php if ($isOwner): ?>
            <section class="panel">
                <h2 class="panel-head">
                    <span class="material-icons-outlined text-[18px]" aria-hidden="true">image</span>
                    Change avatar
                </h2>
                <form method="post" action="<?= e($profileUrl) ?>" enctype="multipart/form-data" class="space-y-3 p-5">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="avatar">
                    <div>
                        <label class="field-label" for="avatar">Image file</label>
                        <input class="field-input" type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" required>
                        <p class="field-help">JPEG, PNG, GIF or WebP. Maximum 2 MB.</p>
                    </div>
                    <button type="submit" class="btn btn-primary w-full">
                        <span class="material-icons-outlined text-[18px]" aria-hidden="true">upload</span>
                        Upload
                    </button>
                </form>
            </section>
        <?php endif; ?>
    </aside>

    <div class="space-y-6">
        <?php if ($isOwner): ?>
            <section class="panel">
                <h2 class="panel-head">
                    <span class="material-icons-outlined text-[18px]" aria-hidden="true">draw</span>
                    Signature
                </h2>
                <form method="post" action="<?= e($profileUrl) ?>" class="space-y-3 p-5">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="signature">
                    <div>
                        <label class="field-label" for="signature">Shown under every post you write</label>
                        <input class="field-input" type="text" id="signature" name="signature" maxlength="255"
                               value="<?= e((string) $profile['signature']) ?>" placeholder="A short line about yourself">
                        <p class="field-help">Plain text, up to 255 characters.</p>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <span class="material-icons-outlined text-[18px]" aria-hidden="true">save</span>
                        Save signature
                    </button>
                </form>
            </section>

            <section class="panel">
                <h2 class="panel-head">
                    <span class="material-icons-outlined text-[18px]" aria-hidden="true">password</span>
                    Change password
                </h2>
                <form method="post" action="<?= e($profileUrl) ?>" class="grid gap-4 p-5 sm:grid-cols-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="password">
                    <div>
                        <label class="field-label" for="current_password">Current</label>
                        <input class="field-input" type="password" id="current_password" name="current_password" required autocomplete="current-password">
                    </div>
                    <div>
                        <label class="field-label" for="new_password">New</label>
                        <input class="field-input" type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
                    </div>
                    <div>
                        <label class="field-label" for="new_password_confirm">Repeat new</label>
                        <input class="field-input" type="password" id="new_password_confirm" name="new_password_confirm" required minlength="8" autocomplete="new-password">
                    </div>
                    <div class="sm:col-span-3">
                        <button type="submit" class="btn btn-primary">
                            <span class="material-icons-outlined text-[18px]" aria-hidden="true">lock_reset</span>
                            Update password
                        </button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <section class="panel">
            <h2 class="panel-head">
                <span class="material-icons-outlined text-[18px]" aria-hidden="true">topic</span>
                Topics started
            </h2>
            <ul class="divide-rule">
                <?php foreach ($recentTopics as $topic): ?>
                    <li class="px-4 py-2.5">
                        <a class="row-link text-sm" href="<?= e(topic_url((string) $topic['slug'])) ?>">
                            <?= e((string) $topic['title']) ?>
                        </a>
                        <p class="mt-0.5 text-xs text-soft">
                            <?= e((string) $topic['board_name']) ?> &middot;
                            <?= e(number_format((int) $topic['reply_count'])) ?> replies &middot;
                            <?= e(time_ago((string) $topic['created_at'])) ?>
                        </p>
                    </li>
                <?php endforeach; ?>
                <?php if ($recentTopics === []): ?>
                    <li class="px-4 py-6 text-center text-sm italic text-soft">No topics started yet.</li>
                <?php endif; ?>
            </ul>
        </section>

        <section class="panel">
            <h2 class="panel-head">
                <span class="material-icons-outlined text-[18px]" aria-hidden="true">forum</span>
                Recent posts
            </h2>
            <ul class="divide-rule">
                <?php foreach ($recentPosts as $post): ?>
                    <li class="px-4 py-2.5">
                        <a class="row-link text-sm" href="<?= e(topic_url((string) $post['topic_slug'])) ?>#post-<?= e((string) (int) $post['id']) ?>">
                            <?= e((string) $post['topic_title']) ?>
                        </a>
                        <p class="mt-0.5 text-xs text-soft"><?= e(excerpt((string) $post['body'], 130)) ?></p>
                        <p class="mt-0.5 text-[11px] text-soft"><?= e(time_ago((string) $post['created_at'])) ?></p>
                    </li>
                <?php endforeach; ?>
                <?php if ($recentPosts === []): ?>
                    <li class="px-4 py-6 text-center text-sm italic text-soft">No posts yet.</li>
                <?php endif; ?>
            </ul>
        </section>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
