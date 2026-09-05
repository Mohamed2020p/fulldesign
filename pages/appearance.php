<?php
/**
 * GodsForum - Let a member choose the theme the board is drawn in.
 */

declare(strict_types=1);

// This page is a route target, not a public address. It is reached only
// through the front controller, which has already resolved the request.
if (!defined('GF_ROUTER')) {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/themes.php';

$user = require_login();

if (setting('allow_user_themes', '1') !== '1') {
    flash('error', 'The administrators have fixed the board to a single theme.');
    redirect(url(''));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_post_csrf();

    $choice = post_string('theme');

    // The value is checked against the catalogue, never against the request.
    if (!theme_exists($choice)) {
        flash('error', 'That theme is not available.');
    } else {
        db_query('UPDATE users SET theme = :t WHERE id = :id', ['t' => $choice, 'id' => (int) $user['id']]);
        flash('success', 'Your theme has been saved.');
    }

    redirect(url('appearance'));
}

$current = active_theme();

$pageTitle       = 'Appearance';
$pageDescription = 'Choose the theme GodsForum is drawn in.';
$breadcrumbs     = [['label' => 'Appearance']];

require dirname(__DIR__) . '/includes/header.php';
?>

<div class="mx-auto max-w-4xl">
    <div class="mb-5">
        <h1 class="font-serif text-2xl font-semibold">Appearance</h1>
        <p class="mt-1 text-sm text-soft">
            Pick a theme. The choice is saved to your account and follows you on every device you sign in from.
        </p>
    </div>

    <form method="post" action="<?= e(url('appearance')) ?>">
        <?= csrf_field() ?>

        <?php foreach (themes_by_family() as $family => $themes): ?>
            <section class="panel mb-5">
                <h2 class="panel-head">
                    <span class="material-icons-outlined text-[18px]" aria-hidden="true">palette</span>
                    <?= e($family) ?> themes
                </h2>

                <div class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3">
                    <?php foreach ($themes as $key => $theme): ?>
                        <label class="theme-card" data-theme="<?= e($key) ?>">
                            <span class="theme-swatch" aria-hidden="true">
                                <?php foreach ($theme['swatch'] as $colour): ?>
                                    <span style="background-color: <?= e($colour) ?>"></span>
                                <?php endforeach; ?>
                            </span>

                            <span class="theme-card-body mt-2 flex items-start gap-2">
                                <input type="radio" name="theme" value="<?= e($key) ?>" class="mt-1"
                                       <?= $key === $current ? 'checked' : '' ?>>
                                <span>
                                    <span class="block text-sm font-semibold"><?= e($theme['label']) ?></span>
                                    <span class="block text-xs text-soft"><?= e($theme['description']) ?></span>
                                </span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <button type="submit" class="btn btn-primary">
            <span class="material-icons-outlined text-[18px]" aria-hidden="true">save</span>
            Save theme
        </button>
    </form>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
