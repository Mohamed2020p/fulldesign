<?php
/**
 * GodsForum - Admin: manage boards.
 */

declare(strict_types=1);

require_once __DIR__ . '/layout.php';

$staff  = require_admin();
$errors = [];

/** @var array<int, string> $iconChoices */
$iconChoices = [
    'forum', 'campaign', 'waving_hand', 'gavel', 'balance', 'coffee',
    'code', 'memory', 'palette', 'science', 'travel_explore', 'library_books',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_post_csrf();
    $action = post_string('action');

    if ($action === 'create' || $action === 'update') {
        $categoryId  = post_int('category_id');
        $name        = post_string('name');
        $description = post_string('description');
        $icon        = post_string('icon');
        $position    = post_int('position');
        $isLocked    = post_string('is_locked') === '1' ? 1 : 0;
        $id          = post_int('id');

        if (!in_array($icon, $iconChoices, true)) {
            $icon = 'forum';
        }
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            $errors[] = 'The board name must be 2 to 80 characters.';
        }
        if (mb_strlen($description) > 255) {
            $errors[] = 'The description may not exceed 255 characters.';
        }
        $categoryExists = (int) db_value('SELECT COUNT(*) FROM categories WHERE id = :c', ['c' => $categoryId], 0);
        if ($categoryExists === 0) {
            $errors[] = 'Please choose a valid category.';
        }

        if ($errors === []) {
            if ($action === 'create') {
                db_query(
                    'INSERT INTO boards (category_id, name, description, icon, position, is_locked)
                     VALUES (:c, :n, :d, :i, :p, :l)',
                    ['c' => $categoryId, 'n' => $name, 'd' => $description, 'i' => $icon, 'p' => $position, 'l' => $isLocked]
                );
                log_admin_action((int) $staff['id'], 'create_board', 'Board "' . $name . '" created.');
                flash('success', 'The board has been created.');
            } elseif ($id > 0) {
                db_query(
                    'UPDATE boards SET category_id = :c, name = :n, description = :d, icon = :i,
                            position = :p, is_locked = :l
                      WHERE id = :id',
                    ['c' => $categoryId, 'n' => $name, 'd' => $description, 'i' => $icon, 'p' => $position, 'l' => $isLocked, 'id' => $id]
                );
                log_admin_action((int) $staff['id'], 'update_board', 'Board #' . $id . ' updated.');
                flash('success', 'The board has been updated.');
            }
            redirect('admin/boards.php');
        }
    } elseif ($action === 'delete') {
        $id = post_int('id');
        if ($id > 0) {
            db_query('DELETE FROM boards WHERE id = :id', ['id' => $id]);
            log_admin_action((int) $staff['id'], 'delete_board', 'Board #' . $id . ' deleted with all its topics.');
            flash('success', 'The board and every topic inside it have been deleted.');
        }
        redirect('admin/boards.php');
    }
}

$editId  = param_int('edit');
$editing = $editId > 0
    ? db_one('SELECT id, category_id, name, description, icon, position, is_locked FROM boards WHERE id = :id', ['id' => $editId])
    : null;

$categories = db_all('SELECT id, name FROM categories ORDER BY position ASC, id ASC');

$boards = db_all(
    'SELECT b.id, b.name, b.description, b.icon, b.position, b.is_locked, c.name AS category_name,
            (SELECT COUNT(*) FROM topics t WHERE t.board_id = b.id) AS topic_count
       FROM boards b JOIN categories c ON c.id = b.category_id
      ORDER BY c.position ASC, b.position ASC, b.id ASC'
);

admin_header('Boards', 'Individual discussion boards and where they belong.');
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
            <span class="flex-1">Board</span>
            <span class="hidden w-24 text-center sm:block">Topics</span>
            <span class="hidden w-16 text-center sm:block">Order</span>
            <span class="w-32 text-right">Actions</span>
        </div>
        <div class="divide-rule">
            <?php foreach ($boards as $board): ?>
                <div class="flex flex-wrap items-center gap-3 px-4 py-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center border border-rule bg-parchment-dark text-ink">
                        <span class="material-icons-outlined text-[18px]" aria-hidden="true"><?= e((string) $board['icon']) ?></span>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-ink">
                            <?= e((string) $board['name']) ?>
                            <?php if ((int) $board['is_locked'] === 1): ?><span class="tag tag-crimson ml-1">Locked</span><?php endif; ?>
                        </p>
                        <p class="text-xs text-ink-soft"><?= e((string) $board['category_name']) ?> &middot; <?= e(excerpt((string) $board['description'], 70)) ?></p>
                    </div>
                    <span class="hidden w-24 text-center text-sm font-semibold text-ink sm:block"><?= e((string) (int) $board['topic_count']) ?></span>
                    <span class="hidden w-16 text-center text-sm text-ink-soft sm:block"><?= e((string) (int) $board['position']) ?></span>
                    <div class="flex w-32 justify-end gap-1">
                        <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/boards.php?edit=' . (int) $board['id'])) ?>">Edit</a>
                        <form method="post" action="<?= e(url('admin/boards.php')) ?>"
                              onsubmit="return confirm('Delete this board and every topic in it?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= e((string) (int) $board['id']) ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if ($boards === []): ?>
                <p class="px-4 py-8 text-center text-sm italic text-ink-soft">No boards yet.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel h-fit">
        <h2 class="panel-head">
            <span class="material-icons-outlined text-[18px]" aria-hidden="true"><?= $editing !== null ? 'edit' : 'add' ?></span>
            <?= $editing !== null ? 'Edit board' : 'New board' ?>
        </h2>
        <form method="post" action="<?= e(url('admin/boards.php')) ?>" class="space-y-4 p-5">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editing !== null ? 'update' : 'create' ?>">
            <?php if ($editing !== null): ?>
                <input type="hidden" name="id" value="<?= e((string) (int) $editing['id']) ?>">
            <?php endif; ?>

            <div>
                <label class="field-label" for="category_id">Category</label>
                <select class="field-input" id="category_id" name="category_id" required>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e((string) (int) $category['id']) ?>"
                            <?= $editing !== null && (int) $editing['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
                            <?= e((string) $category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

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
                <label class="field-label" for="icon">Icon</label>
                <select class="field-input" id="icon" name="icon">
                    <?php foreach ($iconChoices as $icon): ?>
                        <option value="<?= e($icon) ?>" <?= $editing !== null && (string) $editing['icon'] === $icon ? 'selected' : '' ?>>
                            <?= e($icon) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="field-help">Material Icons Outlined name.</p>
            </div>

            <div>
                <label class="field-label" for="position">Sort position</label>
                <input class="field-input" type="number" id="position" name="position" min="0" max="9999"
                       value="<?= e($editing !== null ? (string) (int) $editing['position'] : '0') ?>">
            </div>

            <label class="flex items-center gap-2 text-sm text-ink">
                <input type="checkbox" name="is_locked" value="1" class="h-4 w-4 border-rule"
                    <?= $editing !== null && (int) $editing['is_locked'] === 1 ? 'checked' : '' ?>>
                Lock this board against new topics
            </label>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary"><?= $editing !== null ? 'Save changes' : 'Create board' ?></button>
                <?php if ($editing !== null): ?>
                    <a class="btn btn-ghost" href="<?= e(url('admin/boards.php')) ?>">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </section>
</div>

<?php admin_footer(); ?>
