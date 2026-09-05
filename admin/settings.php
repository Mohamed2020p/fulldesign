<?php
/**
 * GodsForum - Admin: board settings (administrators only).
 */

declare(strict_types=1);

require_once __DIR__ . '/layout.php';

$staff = require_admin();

if (!is_super_admin()) {
    http_response_code(403);
    exit('Only an administrator may change board settings.');
}

/** @var array<string, array{label: string, help: string, type: string, max: int}> $schema */
$schema = [
    'site_name'         => ['label' => 'Board name',        'help' => 'Shown in the page title.',                     'type' => 'text',   'max' => 60],
    'site_tagline'      => ['label' => 'Tagline',           'help' => 'One short line under the logo.',               'type' => 'text',   'max' => 120],
    'welcome_message'   => ['label' => 'Welcome message',   'help' => 'Displayed on the board index banner.',         'type' => 'text',   'max' => 255],
    'registration_open' => ['label' => 'Registration open', 'help' => 'Allow new members to create accounts.',        'type' => 'bool',   'max' => 1],
    'maintenance_mode'  => ['label' => 'Maintenance mode',  'help' => 'Reserved flag for taking the board offline.',  'type' => 'bool',   'max' => 1],
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_post_csrf();

    foreach ($schema as $key => $meta) {
        if ($meta['type'] === 'bool') {
            $value = post_string($key) === '1' ? '1' : '0';
        } else {
            $value = mb_substr(post_string($key), 0, $meta['max']);
        }

        db_query(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            ['k' => $key, 'v' => $value]
        );
    }

    log_admin_action((int) $staff['id'], 'update_settings', 'Board settings saved.');
    flash('success', 'The settings have been saved.');
    redirect('admin/settings.php');
}

/** @var array<string, string> $values */
$values = [];
foreach (db_all('SELECT setting_key, setting_value FROM settings') as $row) {
    $values[(string) $row['setting_key']] = (string) $row['setting_value'];
}

admin_header('Settings', 'Values stored in the database and read on every page load.');
?>

<div class="max-w-2xl">
    <section class="panel">
        <h2 class="panel-head">
            <span class="material-icons-outlined text-[18px]" aria-hidden="true">tune</span>
            Board settings
        </h2>

        <form method="post" action="<?= e(url('admin/settings.php')) ?>" class="space-y-5 p-5">
            <?= csrf_field() ?>

            <?php foreach ($schema as $key => $meta): ?>
                <?php $current = $values[$key] ?? ''; ?>
                <div>
                    <?php if ($meta['type'] === 'bool'): ?>
                        <label class="flex items-start gap-2 text-sm text-ink">
                            <input type="checkbox" name="<?= e($key) ?>" value="1" class="mt-0.5 h-4 w-4 border-rule"
                                <?= $current === '1' ? 'checked' : '' ?>>
                            <span>
                                <span class="font-medium"><?= e($meta['label']) ?></span>
                                <span class="block text-xs text-ink-soft"><?= e($meta['help']) ?></span>
                            </span>
                        </label>
                    <?php else: ?>
                        <label class="field-label" for="<?= e($key) ?>"><?= e($meta['label']) ?></label>
                        <input class="field-input" type="text" id="<?= e($key) ?>" name="<?= e($key) ?>"
                               maxlength="<?= e((string) $meta['max']) ?>" value="<?= e($current) ?>">
                        <p class="field-help"><?= e($meta['help']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="btn btn-primary">
                <span class="material-icons-outlined text-[18px]" aria-hidden="true">save</span>
                Save settings
            </button>
        </form>
    </section>

    <section class="panel mt-6">
        <h2 class="panel-head">
            <span class="material-icons-outlined text-[18px]" aria-hidden="true">dns</span>
            Environment
        </h2>
        <dl class="divide-rule text-sm">
            <div class="flex justify-between px-5 py-2"><dt class="text-ink-soft">PHP version</dt><dd class="font-medium text-ink"><?= e(PHP_VERSION) ?></dd></div>
            <div class="flex justify-between px-5 py-2"><dt class="text-ink-soft">Database</dt><dd class="font-medium text-ink"><?= e(DB_NAME) ?> at <?= e(DB_HOST) ?></dd></div>
            <div class="flex justify-between px-5 py-2"><dt class="text-ink-soft">Base URL</dt><dd class="font-medium text-ink"><?= e(BASE_URL === '' ? '/' : BASE_URL) ?></dd></div>
            <div class="flex justify-between px-5 py-2"><dt class="text-ink-soft">Secure cookies</dt><dd class="font-medium text-ink"><?= COOKIE_SECURE ? 'On' : 'Off' ?></dd></div>
            <div class="flex justify-between px-5 py-2"><dt class="text-ink-soft">Debug mode</dt><dd class="font-medium text-ink"><?= DEBUG_MODE ? 'On' : 'Off' ?></dd></div>
        </dl>
    </section>
</div>

<?php admin_footer(); ?>
