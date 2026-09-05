<?php
/**
 * GodsForum - Board rules, a static informational page.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$pageTitle       = 'Board rules';
$pageDescription = 'How GodsForum is run.';
$breadcrumbs     = [['label' => 'Board rules']];

/** @var array<int, array{title: string, body: string, icon: string}> $rules */
$rules = [
    [
        'icon'  => 'subject',
        'title' => 'Stay on topic',
        'body'  => 'Post in the board that matches your subject. A thread that drifts is fine, a thread opened in the wrong place is moved without notice.',
    ],
    [
        'icon'  => 'handshake',
        'title' => 'Argue with the idea, not the person',
        'body'  => 'Strong disagreement is welcome and expected. Insults, threats and personal remarks are not, and will be removed by the staff.',
    ],
    [
        'icon'  => 'block',
        'title' => 'No advertising',
        'body'  => 'Do not use the board to sell things, farm links or promote a product. Sharing your own work in the Workshop is different and encouraged.',
    ],
    [
        'icon'  => 'manage_search',
        'title' => 'Search before you post',
        'body'  => 'Most questions have been asked before. A short search saves everyone time and keeps the archive tidy.',
    ],
    [
        'icon'  => 'shield',
        'title' => 'Respect the staff',
        'body'  => 'Moderator decisions can be questioned politely in Rules and Feedback. Reopening a locked thread to continue an argument is not acceptable.',
    ],
    [
        'icon'  => 'privacy_tip',
        'title' => 'Protect privacy',
        'body'  => 'Never post another person private information. Never post your own password, address or documents, not even in a screenshot.',
    ],
];

require __DIR__ . '/includes/header.php';
?>

<div class="mx-auto max-w-3xl">
    <div class="mb-5">
        <h1 class="font-serif text-2xl font-semibold text-ink">Board rules</h1>
        <p class="mt-0.5 text-sm text-ink-soft">
            Six lines that keep the hall readable. Registering an account means agreeing to them.
        </p>
    </div>

    <section class="panel divide-rule">
        <?php foreach ($rules as $index => $rule): ?>
            <article class="flex gap-4 px-5 py-4">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center border border-rule bg-parchment-dark text-ink">
                    <span class="material-icons-outlined text-[20px]" aria-hidden="true"><?= e($rule['icon']) ?></span>
                </span>
                <div>
                    <h2 class="text-[15px] font-semibold text-ink">
                        <?= e((string) ($index + 1)) ?>. <?= e($rule['title']) ?>
                    </h2>
                    <p class="mt-1 text-sm leading-relaxed text-ink-soft"><?= e($rule['body']) ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="panel mt-6">
        <h2 class="panel-head">
            <span class="material-icons-outlined text-[18px]" aria-hidden="true">gavel</span>
            Enforcement
        </h2>
        <div class="space-y-3 p-5 text-sm leading-relaxed text-ink-soft">
            <p>
                Minor breaches are handled with an edit and a quiet word. Repeated breaches lead to a locked
                thread, then to a suspended account. Suspensions are recorded in the staff log with the reason.
            </p>
            <p>
                If you see something that breaks these rules, use the report link under the post rather than
                replying to it. Reports are private and reach every moderator at once.
            </p>
        </div>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
