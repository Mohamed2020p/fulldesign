<?php
/**
 * GodsForum - Sign in.
 */

declare(strict_types=1);

// This page is a route target, not a public address. It is reached only
// through the front controller, which has already resolved the request.
if (!defined('GF_ROUTER')) {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/functions.php';

if (is_logged_in()) {
    redirect(url(''));
}

$errors     = [];
$identifier = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_post_csrf();

    $identifier = post_string('identifier');
    $password   = (string) ($_POST['password'] ?? '');
    $ip         = client_ip();

    if ($identifier === '' || $password === '') {
        $errors[] = 'Please fill in both fields.';
    } elseif (login_attempts_recent($identifier, $ip) >= LOGIN_MAX_ATTEMPTS) {
        $errors[] = 'Too many failed attempts. Please wait fifteen minutes before trying again.';
    } else {
        $account = db_one(
            'SELECT id, username, password_hash, status, ban_reason, banned_until FROM users
              WHERE username = :id OR email = :id LIMIT 1',
            ['id' => $identifier]
        );

        // A temporary suspension that has expired is lifted here, so the member
        // can sign in again the moment their time is served.
        if ($account !== null && $account['status'] === 'banned' && $account['banned_until'] !== null
            && strtotime((string) $account['banned_until']) <= time()) {
            db_query(
                'UPDATE users SET status = "active", ban_reason = "", banned_until = NULL, banned_by = NULL
                  WHERE id = :id',
                ['id' => (int) $account['id']]
            );
            db_query(
                'UPDATE bans SET lifted_at = NOW() WHERE user_id = :id AND lifted_at IS NULL',
                ['id' => (int) $account['id']]
            );
            $account['status'] = 'active';
        }

        $hash = is_array($account) ? (string) $account['password_hash'] : '';
        // Always run a verification so the response time does not leak account existence.
        $valid = password_verify($password, $hash !== '' ? $hash : '$2y$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidin');

        if ($account !== null && $valid && $account['status'] === 'active') {
            if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                db_query(
                    'UPDATE users SET password_hash = :p WHERE id = :id',
                    ['p' => password_hash($password, PASSWORD_DEFAULT), 'id' => (int) $account['id']]
                );
            }

            login_attempt_record($identifier, $ip, true);
            login_user((int) $account['id']);
            flash('success', 'Signed in as ' . (string) $account['username'] . '.');
            redirect(url(''));
        }

        login_attempt_record($identifier, $ip, false);

        if ($account !== null && $valid && $account['status'] === 'banned') {
            $notice = 'This account has been suspended.';

            $reason = trim((string) $account['ban_reason']);
            if ($reason !== '') {
                $notice .= ' Reason given: ' . $reason;
            }

            if ($account['banned_until'] !== null) {
                $notice .= ' The suspension is lifted on '
                    . date('j M Y \a\t H:i', (int) strtotime((string) $account['banned_until'])) . '.';
            } else {
                $notice .= ' The suspension is permanent.';
            }

            $errors[] = $notice . ' Contact the administrators if you believe this is a mistake.';
        } else {
            $errors[] = 'Those credentials were not recognised.';
        }
    }
}

$pageTitle       = 'Sign in';
$pageDescription = 'Sign in to your GodsForum account.';
$breadcrumbs     = [['label' => 'Sign in']];

require dirname(__DIR__) . '/includes/header.php';
?>

<div class="mx-auto max-w-md">
    <section class="panel">
        <h1 class="panel-head">
            <span class="material-icons-outlined text-[18px]" aria-hidden="true">login</span>
            Sign in
        </h1>

        <form method="post" action="<?= e(url('login')) ?>" class="space-y-4 p-5">
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
                <label class="field-label" for="identifier">Username or email</label>
                <input class="field-input" type="text" id="identifier" name="identifier" required
                       value="<?= e($identifier) ?>" autocomplete="username" placeholder="zeus">
            </div>

            <div>
                <label class="field-label" for="password">Password</label>
                <input class="field-input" type="password" id="password" name="password" required
                       autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary w-full">
                <span class="material-icons-outlined text-[18px]" aria-hidden="true">key</span>
                Sign in
            </button>

            <p class="text-center text-xs text-soft">
                No account yet?
                <a class="font-medium hover:underline" href="<?= e(url('register')) ?>">Register</a>.
            </p>
        </form>
    </section>

    <p class="mt-4 text-center text-xs text-soft">
        Accounts are locked for fifteen minutes after
        <?= e((string) LOGIN_MAX_ATTEMPTS) ?> failed attempts.
    </p>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
