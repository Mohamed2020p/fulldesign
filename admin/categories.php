<?php
/**
 * GodsForum - Admin: manage categories.
 */

declare(strict_types=1);

require_once __DIR__ . '/layout.php';

$staff  = require_admin();
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_post_csrf();

    $action = post_string('action');

    if ($action === 'create' || $action === 'update') {
        $name        = post_string('name');
        $description = post_string('description');
        $position    = post_int('position');
        $id          = post_int('id');

        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            $errors[] = 'The category name must be 2 to 80 characters.';
        }
        if (mb_strlen($description) > 255) {
            $errors[] = 'The description may not exceed 255 characters.';
        }

        if ($errors === []) {
            if ($action === 'create') {
                db_query(
                    'INSERT INTO categories (name, description, position) VALUES (:n, :d, :p)',
                    ['n' => $name, 'd' => $description, 'p' => $position]
                );
                log_admin_action((int) $staff['id'], 'create_category', 'Category "' . $name . '" created.');
                flash('success', 'The category has been created.');
            } elseif ($id > 0) {
                db_query(
                    'UPDATE categories SET name = :n, description = :d, position = :p WHERE id = :id',
                    ['n' => $name, 'd' => $description, 'p' => $position, 'id' => $id]
                );
                log_admin_action((int) $staff['id'], 'update_category', 'Category #' . $id . ' updated.');
                flash('success', 'The category has been updated.');
            }
            redirect('admin/categories.php');
        }
    } elseif ($action === 'delete') {
        $id = post_int('id');
        if ($id > 0) {
            $boardCount = (int) db_value('SELECT COUNT(*) FROM boards WHERE category_id = :c', ['c' => $id], 0);
            if ($boardCount > 0) {
                flash('error', 'Move or delete the boards inside this category first.');
            } else {
                db_query('DELETE FROM categories WHERE id = :id', ['id' => $id]);
                log_admin_action((int) $staff['id'], 'delete_category', 'Category #' . $id . ' deleted.');
                flash('success', 'The category has been deleted.');
            }
        }
        redirect('admin/categories.php');
    }
}

$editId   = param_int('edit');
$editing  = $editId > 0 ? db_one('SELECT id, name, description, position FROM categories WHERE id = :id', ['id' => $editId]) : null;

$categories = db_all(
    'SELECT c.id, c.name, c.description, c.position,
            (SELECT COUNT(*) FROM boards b WHERE b.category_id = c.id) AS board_count
       FROM categories c ORDER BY c.position ASC, c.id ASC'
);

admin_header('Categories', 'Top level groupings shown on the board index.');
?>

<?php if ($errors !== []): ?>
    <div class="mb-4 border-l-4 border-crimson bg-crimson/10 px-4 py-3 text-sm text-crimson">
        <ul class="list-inside list-disc space-y-1">
            <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
    <section class="panel">
        <div class="panel-subhead">
            <span class="flex-1">Category</span>
            <span class="w-20 text-center">Boards</span>
            <span class="w-16 text-center">Order</span>
            <span class="w-32 text-right">Actions</span>
        </div>
        <div class="divide-rule">
            <?php foreach ($categories as $category): ?>
                <div class="flex flex-wrap items-center gap-3 px-4 py-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-ink"><?= e((string) $category['name']) ?></p>
                        <p class="text-xs text-ink-soft"><?= e((string) $category['description']) ?></p>
                    </div>
                    <span class="w-20 text-center text-sm font-semibold text-ink"><?= e((string) (int) $category['board_count']) ?></span>
                    <span class="w-16 text-center text-sm text-ink-soft"><?= e((string) (int) $category['position']) ?></span>
                    <div class="flex w-32 justify-end gap-1">
                        <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/categories.php?edit=' . (int) $category['id'])) ?>">Edit</a>
                        <form method="post" action="<?= e(url('admin/categories.php')) ?>"
                              onsubmit="return confirm('Delete this category?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= e((string) (int) $category['id']) ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if ($categories === []): ?>
                <p class="px-4 py-8 text-center text-sm italic text-ink-soft">No categories yet.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel h-fit">
        <h2 class="panel-head">
            <span class="material-icons-outlined text-[18px]" aria-hidden="true"><?= $editing !== null ? 'edit' : 'add' ?></span>
            <?= $editing !== null ? 'Edit category' : 'New category' ?>
        </h2>
        <form method="post" action="<?= e(url('admin/categories.php')) ?>" class="space-y-4 p-5">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editing !== null ? 'update' : 'create' ?>">
            <?php if ($editing !== null): ?>
                <input type="hidden" name="id" value="<?= e((string) (int) $editing['id']) ?>">
            <?php endif; ?>

            <div>
                <label class="field-label" for="name">Name</label>
                <input class="field-input" type="text" id="name" name="name" required maxlength="80"
                       value="<?= e($editing !== null ? (string) $editing['name'] : '') ?>">
            </div>

            <div>
                <label class="field-label" for="description">Description</label>
                <input class="field-input" type="text" id="description" name="description" maxlength="255"
                       value="<?= e($editing !== null ? (string) $editing['description'] : '') ?>">
            </div>

            <div>
                <label class="field-label" for="position">Sort position</label>
                <input class="field-input" type="number" id="position" name="position" min="0" max="9999"
                       value="<?= e($editing !== null ? (string) (int) $editing['position'] : '0') ?>">
                <p class="field-help">Lower numbers appear first.</p>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary"><?= $editing !== null ? 'Save changes' : 'Create category' ?></button>
                <?php if ($editing !== null): ?>
                    <a class="btn btn-ghost" href="<?= e(url('admin/categories.php')) ?>">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </section>
</div>

<?php admin_footer(); ?>
