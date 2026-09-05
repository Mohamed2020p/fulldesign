<?php
/**
 * GodsForum - Public site footer.
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$footStats = db_one(
    'SELECT
        (SELECT COUNT(*) FROM topics) AS topics,
        (SELECT COUNT(*) FROM posts)  AS posts,
        (SELECT COUNT(*) FROM users WHERE status = "active") AS members'
) ?? ['topics' => 0, 'posts' => 0, 'members' => 0];
?>
</main>

<footer class="mt-10 border-t-2 border-ink bg-ink text-parchment/80">
    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-8 md:grid-cols-3">
        <div>
            <h2 class="font-serif text-lg tracking-[0.16em] text-parchment">GODSFORUM</h2>
            <p class="mt-2 max-w-sm text-sm leading-relaxed">
                An old school message board built with PHP and MySQL. Threads stay where you left them,
                pages load in an instant, and nothing decides what you read except you.
            </p>
        </div>

        <div>
            <h2 class="text-xs uppercase tracking-[0.2em] text-gold">Sections</h2>
            <ul class="mt-3 space-y-1.5 text-sm">
                <li><a href="<?= e(url('index.php')) ?>" class="hover:text-gold hover:underline">Board index</a></li>
                <li><a href="<?= e(url('recent.php')) ?>" class="hover:text-gold hover:underline">Recent activity</a></li>
                <li><a href="<?= e(url('members.php')) ?>" class="hover:text-gold hover:underline">Member list</a></li>
                <li><a href="<?= e(url('rules.php')) ?>" class="hover:text-gold hover:underline">Board rules</a></li>
            </ul>
        </div>

        <div>
            <h2 class="text-xs uppercase tracking-[0.2em] text-gold">Board statistics</h2>
            <dl class="mt-3 space-y-1.5 text-sm">
                <div class="flex justify-between border-b border-parchment/10 pb-1">
                    <dt>Topics</dt><dd class="font-medium text-parchment"><?= e(number_format((int) $footStats['topics'])) ?></dd>
                </div>
                <div class="flex justify-between border-b border-parchment/10 pb-1">
                    <dt>Posts</dt><dd class="font-medium text-parchment"><?= e(number_format((int) $footStats['posts'])) ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt>Members</dt><dd class="font-medium text-parchment"><?= e(number_format((int) $footStats['members'])) ?></dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="border-t border-parchment/15">
        <p class="mx-auto max-w-6xl px-4 py-4 text-center text-xs tracking-wide text-parchment/60">
            <?= e(SITE_NAME) ?> &copy; <?= e(date('Y')) ?>. Built with PHP, MySQL and Tailwind CSS.
        </p>
    </div>
</footer>

</body>
</html>
