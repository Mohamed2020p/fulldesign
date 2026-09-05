<?php
/**
 * GodsForum - Theme catalogue.
 *
 * Every theme is a set of CSS custom properties applied through a
 * data-theme attribute on the <html> element. The stylesheet never changes,
 * only the variables, so switching themes costs nothing at runtime and
 * cannot break the layout.
 */

declare(strict_types=1);

// Defence in depth. Apache already denies this directory, but if a server is
// misconfigured these files must still refuse to run as a request target.
if (!defined('GF_ROUTER') && PHP_SAPI !== 'cli' && realpath(__FILE__) === realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''))) {
    http_response_code(404);
    exit;
}


/**
 * All themes available on the board.
 *
 * @return array<string, array{label: string, description: string, family: string, swatch: array<int, string>}>
 */
function theme_catalogue(): array
{
    return [
        'parchment' => [
            'label'       => 'Parchment',
            'description' => 'The classic old school board: aged paper, navy ink and gold.',
            'family'      => 'Classic',
            'swatch'      => ['#f4ecdc', '#1c2b45', '#c2a14d'],
        ],
        'midnight' => [
            'label'       => 'Midnight',
            'description' => 'A dark reading theme with cool blue panels and amber accents.',
            'family'      => 'Classic',
            'swatch'      => ['#141a24', '#e8edf5', '#d9a441'],
        ],
        'ember' => [
            'label'       => 'Ember',
            'description' => 'Warm charcoal with copper highlights, easy on the eyes at night.',
            'family'      => 'Classic',
            'swatch'      => ['#1d1917', '#f0e6df', '#c9713d'],
        ],
        'forest' => [
            'label'       => 'Forest',
            'description' => 'Muted greens on a soft cream page, calm and low contrast.',
            'family'      => 'Classic',
            'swatch'      => ['#f1f2e9', '#22372b', '#5c8a4a'],
        ],
        'slate' => [
            'label'       => 'Slate',
            'description' => 'A clean modern light theme with a neutral grey structure.',
            'family'      => 'Modern',
            'swatch'      => ['#f6f7f9', '#1f2733', '#3b6fd4'],
        ],
        'aurora' => [
            'label'       => 'Aurora',
            'description' => 'Modern dark interface with violet and teal accents.',
            'family'      => 'Modern',
            'swatch'      => ['#12131c', '#e9e9f2', '#8b7cf6'],
        ],
        'sandstone' => [
            'label'       => 'Sandstone',
            'description' => 'Soft terracotta and sand, a gentle warm light theme.',
            'family'      => 'Modern',
            'swatch'      => ['#faf5ef', '#3a2f28', '#b5754a'],
        ],
        'contrast' => [
            'label'       => 'High contrast',
            'description' => 'Maximum legibility: pure black on white with heavy borders.',
            'family'      => 'Accessible',
            'swatch'      => ['#ffffff', '#000000', '#04c'],
        ],
    ];
}

function theme_exists(string $key): bool
{
    return array_key_exists($key, theme_catalogue());
}

/**
 * The theme key to render the current request with.
 *
 * Order of preference: the signed in member's saved choice, then the board
 * default set by an administrator, then the built in fallback.
 */
function active_theme(): string
{
    static $resolved = null;

    if ($resolved !== null) {
        return $resolved;
    }

    $default = setting('default_theme', 'parchment');
    if (!theme_exists($default)) {
        $default = 'parchment';
    }

    $resolved = $default;

    if (setting('allow_user_themes', '1') === '1') {
        $user = current_user();
        if ($user !== null) {
            $choice = (string) ($user['theme'] ?? '');
            if (theme_exists($choice)) {
                $resolved = $choice;
            }
        }
    }

    return $resolved;
}

/**
 * Group the catalogue by family, for rendering the picker.
 *
 * @return array<string, array<string, array{label: string, description: string, family: string, swatch: array<int, string>}>>
 */
function themes_by_family(): array
{
    $grouped = [];
    foreach (theme_catalogue() as $key => $theme) {
        $grouped[$theme['family']][$key] = $theme;
    }

    return $grouped;
}
