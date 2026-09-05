<?php
/**
 * GodsForum - Account registration.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('index.php');
}

$errors   = [];
$username = '';
$email    = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_post_csrf();

    if (setting('registration_open', '1') !== '1') {
        $errors[] = 'Registration is currently closed by the administrators.';
    }

    $username = post_string('username');
    $email    = post_string('email');
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['password_confirm'] ?? '');
    $honeypot = post_string('website'); // bots fill hidden fields

    if ($honeypot !== '') {
        $errors[] = 'Registration could not be completed.';
    }

    if (preg_match('/^[A-Za-z0-9_]{3,32}$/', $username) !== 1) {
        $errors[] = 'The username must be 3 to 32 characters, letters, numbers and underscores only.';
    }

    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 190) {
        $errors[] = 'Please provide a valid email address.';
    }

    if (mb_strlen($password) < 8) {
        $errors[] = 'The password must be at least 8 characters long.';
    } elseif (preg_match('/[A-Za-z]/', $password) !== 1 || preg_match('/\d/', $password) !== 1) {
        $errors[] = 'The password must contain both letters and numbers.';
    }

    if ($password !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    }

    if ($errors === []) {
        $taken = db_one(
            'SELECT username, email FROM users WHERE username = :u OR email = :e LIMIT 1',
            ['u' => $username, 'e' => mb_strtolower($email)]
        );

        if ($taken !== null) {
            if (mb_strtolower((string) $taken['username']) === mb_strtolower($username)) {
                $errors[] = 'That username is already taken.';
            } else {
                $errors[] = 'That email address is already registered.';
            }
        }
    }

    if ($errors === []) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        db_query(
            'INSERT INTO users (username, email, password_hash, role) VALUES (:u, :e, :p, "member")',
            ['u' => $username, 'e' => mb_strtolower($email), 'p' => $hash]
        );

        login_user(db_insert_id());
        flash('success', 'Welcome to the hall, ' . $username . '.');
        redirect('index.php');
    }
}

$pageTitle       = 'Register';
$pageDescription = 'Create a GodsForum account.';
$breadcrumbs     = [['label' => 'Register']];

require __DIR__ . '/includes/header.php';
?>

<div class="mx-auto max-w-lg">
    <section class="panel">
        <h1 class="panel-head">
            <span class="material-icons-outlined text-[18px]" aria-hidden="true">person_add</span>
            Create an account
        </h1>

        <form method="post" action="<?= e(url('register.php')) ?>" class="space-y-4 p-5" autocomplete="on">
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
                <label class="field-label" for="username">Username</label>
                <input class="field-input" type="text" id="username" name="username" required
                       minlength="3" maxlength="32" pattern="[A-Za-z0-9_]+"
                       value="<?= e($username) ?>" autocomplete="username" placeholder="hermes">
                <p class="field-help">Letters, numbers and underscores. This name is public.</p>
            </div>

            <div>
                <label class="field-label" for="email">Email address</label>
                <input class="field-input" type="email" id="email" name="email" required maxlength="190"
                       value="<?= e($email) ?>" autocomplete="email" placeholder="you@example.com">
                <p class="field-help">Never shown to other members.</p>
            </div>

            <div>
                <label class="field-label" for="password">Password</label>
                <input class="field-input" type="password" id="password" name="password" required
                       minlength="8" autocomplete="new-password">
                <p class="field-help">At least 8 characters, containing letters and numbers.</p>
            </div>

            <div>
                <label class="field-label" for="password_confirm">Repeat password</label>
                <input class="field-input" type="password" id="password_confirm" name="password_confirm" required
                       minlength="8" autocomplete="new-password">
            </div>

            <div class="hidden" aria-hidden="true">
                <label for="website">Leave this field empty</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <button type="submit" class="btn btn-primary w-full">
                <span class="material-icons-outlined text-[18px]" aria-hidden="true">how_to_reg</span>
                Register
            </button>

            <p class="text-center text-xs text-ink-soft">
                Already a member?
                <a class="font-medium text-crimson hover:underline" href="<?= e(url('login.php')) ?>">Sign in instead</a>.
            </p>
        </form>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
