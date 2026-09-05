<?php
/**
 * GodsForum - Admin: board appearance.
 *
 * Sets the theme new and signed out visitors see, and decides whether
 * members are allowed to override it with a choice of their own.
 */

declare(strict_types=1);

// This page is a route target, not a public address. It is reached only
// through the front controller, which has already resolved the request.
if (!defined('GF_ROUTER')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/layout.php';

$staff = require_super_admin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_post_csrf();

    $action = post_string('action');

    if ($action === 'theme') {
        $choice = post_string('default_theme');
        $allow  = post_string('allow_user_themes') === '1' ? '1' : '0';

        // Validated against the catalogue, so only a known key is ever stored.
        if (!theme_exists($choice)) {
            flash('error', 'That theme is not available.');
        } else {
            setting_put('default_theme', $choice);
            setting_put('allow_user_themes', $allow);

            log_admin_action(
                (int) $staff['id'],
                'change_theme',
                'Board theme set to ' . $choice . '; member overrides ' . ($allow === '1' ? 'allowed' : 'disabled') . '.'
            );
            flash('success', 'The board appearance has been saved.');
        }
    } elseif ($action === 'reset_member_themes') {
        $choice = setting('default_theme', 'parchment');
        db_query('UPDATE users SET theme = :t', ['t' => $choice]);
        log_admin_action((int) $staff['id'], 'reset_themes', 'Every member reset to the ' . $choice . ' theme.');
        flash('success', 'Every member has been reset to the board theme.');
    }

    redirect(url('admin/appearance'));
}

$currentDefault = setting('default_theme', 'parchment');
if (!theme_exists($currentDefault)) {
    $currentDefault = 'parchment';
}

$allowUserThemes = setting('allow_user_themes', '1') === '1';

// How many members are using each theme, for the usage column.
$usage = [];
foreach (db_all('SELECT theme, COUNT(*) AS total FROM users GROUP BY theme') as $row) {
    $usage[(string) $row['theme']] = (int) $row['total'];
}

admin_header('Appearance', 'Choose the theme the board is drawn in.');
?>

<form method="post" action="<?= e(url('admin/appearance')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="theme">

    <?php foreach (themes_by_family() as $family => $themes): ?>
        <section class="panel mb-5">
            <h2 class="panel-head">
                <span class="material-icons-outlined text-[18px]" aria-hidden="true">palette</span>
                <?= e($family) ?> themes
            </h2>

            <div class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-4">
                <?php foreach ($themes as $key => $theme): ?>
                    <label class="theme-card" data-theme="<?= e($key) ?>">
                        <span class="theme-swatch" aria-hidden="true">
                            <?php foreach ($theme['swatch'] as $colour): ?>
                                <span style="background-color: <?= e($colour) ?>"></span>
                            <?php endforeach; ?>
                        </span>

                        <span class="theme-card-body mt-2 flex items-start gap-2">
                            <input type="radio" name="default_theme" value="<?= e($key) ?>" class="mt-1"
                                   <?= $key === $currentDefault ? 'checked' : '' ?>>
                            <span>
                                <span class="block text-sm font-semibold"><?= e($theme['label']) ?></span>
                                <span class="block text-xs text-soft"><?= e($theme['description']) ?></span>
                                <span class="mt-1 block text-[11px] text-soft">
                                    <?= e(number_format($usage[$key] ?? 0)) ?> member<?= ($usage[$key] ?? 0) === 1 ? '' : 's' ?>
                                </span>
                            </span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <section class="panel mb-5">
        <h2 class="panel-head">
            <span class="material-icons-outlined text-[18px]" aria-hidden="true">manage_accounts</span>
            Member choice
        </h2>
        <div class="p-4">
            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" name="allow_user_themes" value="1" class="mt-0.5" <?= $allowUserThemes ? 'checked' : '' ?>>
                <span>
                    <span class="block font-medium">Let members pick their own theme</span>
                    <span class="block text-xs text-soft">
                        When this is off, everybody sees the board theme selected above and the
                        appearance page is closed.
                    </span>
                </span>
            </label>
        </div>
    </section>

    <button type="submit" class="btn btn-primary">
        <span class="material-icons-outlined text-[18px]" aria-hidden="true">save</span>
        Save appearance
    </button>
</form>

<section class="panel mt-6">
    <h2 class="panel-head">
        <span class="material-icons-outlined text-[18px]" aria-hidden="true">restart_alt</span>
        Reset every member
    </h2>
    <div class="flex flex-wrap items-center justify-between gap-3 p-4">
        <p class="max-w-xl text-sm text-soft">
            Set every account back to the board theme. Useful after a rebrand. Members
            may choose again afterwards if member choice is enabled.
        </p>
        <form method="post" action="<?= e(url('admin/appearance')) ?>"
              onsubmit="return confirm('Reset the theme for every member?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset_member_themes">
            <button type="submit" class="btn btn-danger">Reset all members</button>
        </form>
    </div>
</section>

<?php admin_footer(); ?>
