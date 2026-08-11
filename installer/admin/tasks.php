<?php

/**
 * Klytos Admin — Tasks (manifest entry 13, template `overview-stats`)
 *
 * The work queue. `SPEC/manifest.md` §13 gives it a stat row, a grouped task
 * list and a good-news empty state, and one delta: **task state is a word plus
 * a glyph; "Overdue" is never red alone.**
 *
 * Two of §13's four stats are NOT built, and the reason is the product rather
 * than the delivery: **no task carries a due date.** `TaskManager::create()`
 * and `update()` store `page_slug`, `css_selector`, `description`, `priority`,
 * `status`, `created_by`, `assigned_to`, `created_at`, `updated_at` and
 * `completed_at` — there is no due field anywhere in the tree, so *Due this
 * week* and *Overdue* have nothing to count and the "never red alone" delta
 * governs a state that cannot exist. Deferred in `docs/roadmap.md` §0c and
 * asked as **DR-013**; see `docs/reference/tasks-screen.md`.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Klytos\Core\TaskManager;

$pageTitle   = __( 'tasks.title' );
$taskManager = new TaskManager( $app->getStorage() );
$success     = '';
$error       = '';

// ─── POST ────────────────────────────────────────────────────────

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    if ( ! klytos_verify_csrf() ) {
        // A refused token is REPORTED. The shipped screen wrote
        // `if ( ... && klytos_verify_csrf() )` with no `else`, so a refusal
        // re-rendered as if nothing had been sent — the FIFTH screen of this
        // build with the identical defect, after entries 27, 28, 32 and 24.
        $error = __( 'tasks.error_csrf' );
    } else {
        // The list is gated at `tasks.create` so an editor sees their own work
        // queue; completing and dismissing act on ANY task, including other
        // people's, which is the `tasks.manage` tier the matrix separates.
        klytos_require_permission( 'tasks.manage' );

        $action = (string) ( $_POST['action'] ?? '' );
        $taskId = (string) ( $_POST['task_id'] ?? '' );

        if ( $taskId !== '' && in_array( $action, ['complete', 'dismiss', 'delete'], true ) ) {
            try {
                klytos_do_action( 'admin.tasks.before_action', $action, $taskId );

                if ( $action === 'complete' ) {
                    $taskManager->complete( $taskId );
                    $success = __( 'tasks.completed' );
                } elseif ( $action === 'dismiss' ) {
                    $taskManager->update( $taskId, ['status' => 'dismissed'] );
                    $success = __( 'tasks.dismissed' );
                } else {
                    $taskManager->delete( $taskId );
                    $success = __( 'tasks.deleted' );
                }

                klytos_do_action( 'admin.tasks.after_action', $action, $taskId );
            } catch ( \Throwable $e ) {
                // The manager's raw English exception goes to the log, where it
                // is useful; the person gets a sentence a catalogue can reach
                // (D-103's defect 2, third occurrence).
                klytos_log( 'error', 'Task action failed: ' . $e->getMessage() );
                $error = __( 'tasks.error_action' );
            }
        }
    }
}

// ─── Data ────────────────────────────────────────────────────────

$statusFilter = (string) ( $_GET['status'] ?? 'open' );
$validFilters = ['open', 'in_progress', 'completed', 'all'];
if ( ! in_array( $statusFilter, $validFilters, true ) ) {
    // An unknown filter resolves to the first rather than failing — entry 9's
    // precedent for a query value nobody typed on purpose.
    $statusFilter = 'open';
}

$openCount     = $taskManager->count( 'open' );
$progressCount = $taskManager->count( 'in_progress' );
$tasks         = $taskManager->list( $statusFilter, '', 200 );

/*
 * §13's *Done (30d)*. `completed_at` is the only date the product stores about
 * a finished task, so the window is computed here rather than in the manager —
 * `count()` filters by status alone and widening a released signature inside a
 * fidelity stage is the deviation rule 5 forbids in the other direction
 * (adaptation 75's shape).
 */
$doneWindowStart = klytos_time() - ( 30 * 86400 );
$doneRecent      = 0;
foreach ( $taskManager->list( 'completed', '', 0 ) as $doneTask ) {
    $completedAt = (string) ( $doneTask['completed_at'] ?? '' );
    if ( $completedAt !== '' && klytos_datetime_to_timestamp( $completedAt ) >= $doneWindowStart ) {
        $doneRecent++;
    }
}

$adminPath = $adminPath ?? '';

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
klytos_do_action( 'admin.tasks.before' );

/*
 * Defined AFTER the shell: `$spriteUrl` and `klytos_admin_icon()` are created
 * by `templates/sidebar.php`, and a closure binds its `use` variables at
 * DEFINITION time. Defined above the requires, entry 44's renderer captured
 * `null` and the whole page fataled under `strict_types` (D-110).
 */

/** The word AND the glyph for a task status — never colour on its own (§13). */
$statusMeta = static function ( string $status ): array {
    return match ( $status ) {
        'completed'   => ['glyph' => 'ks-task_alt',     'tone' => 'exito',    'label' => __( 'tasks.status_completed' )],
        'in_progress' => ['glyph' => 'ks-progress_activity', 'tone' => 'sync', 'label' => __( 'tasks.status_in_progress' )],
        'dismissed'   => ['glyph' => 'ks-close',        'tone' => 'offline',  'label' => __( 'tasks.status_dismissed' )],
        default       => ['glyph' => 'ks-pending',      'tone' => 'info',     'label' => __( 'tasks.status_open' )],
    };
};

/** The word AND the glyph for a priority — same rule, same reason. */
$priorityMeta = static function ( string $priority ): array {
    return match ( $priority ) {
        'urgent' => ['glyph' => 'ks-priority_high', 'tone' => 'peligro',  'label' => __( 'tasks.priority_urgent' )],
        'high'   => ['glyph' => 'ks-arrow_upward',  'tone' => 'aviso',    'label' => __( 'tasks.priority_high' )],
        'low'    => ['glyph' => 'ks-check',         'tone' => 'offline',  'label' => __( 'tasks.priority_low' )],
        default  => ['glyph' => 'ks-schedule',      'tone' => 'info',     'label' => __( 'tasks.priority_medium' )],
    };
};

$tasksUrl = static function ( string $status ) use ( $adminPath ): string {
    return $adminPath . 'tasks.php?status=' . rawurlencode( $status );
};
?>

<?php if ( $success !== '' ) : ?>
    <p class="k-status-line k-status-line--info" role="status" data-testid="tasks.success">
        <?php echo klytos_esc_html( $success ); ?>
    </p>
<?php endif; ?>

<?php if ( $error !== '' ) : ?>
    <p class="k-status-line k-status-line--aviso" role="alert" data-testid="tasks.error">
        <?php echo klytos_esc_html( $error ); ?>
    </p>
<?php endif; ?>

<?php klytos_do_action( 'admin.tasks.before_stats' ); ?>
<div class="k-stat-row" data-testid="tasks.stats">
    <?php
    /*
     * THREE cards, not §13's four — and the three are the facts the product
     * holds. *Due this week* and *Overdue* need a due date that exists nowhere
     * in the tree (`docs/roadmap.md` §0c, DR-013). *In progress* is a shipped
     * status this screen has always drawn and is built rather than dropped,
     * which also keeps the row inside `template-overview-stats.md` §1's 3–5
     * (adaptation 87).
     */
    $statCards = [
        ['id' => 'open',     'glyph' => 'ks-pending',   'tone' => 'info',  'value' => $openCount,     'label' => __( 'tasks.stat_open' )],
        ['id' => 'progress', 'glyph' => 'ks-progress_activity', 'tone' => 'sync', 'value' => $progressCount, 'label' => __( 'tasks.stat_in_progress' )],
        ['id' => 'done',     'glyph' => 'ks-task_alt',  'tone' => 'exito', 'value' => $doneRecent,    'label' => __( 'tasks.stat_done_30d' )],
    ];

    foreach ( klytos_apply_filters( 'admin.tasks.stats', $statCards ) as $card ) :
        $valId = 'tasks-stat-' . $card['id'] . '-value';
        $labId = 'tasks-stat-' . $card['id'] . '-label';
        ?>
        <div class="k-stat"
             aria-labelledby="<?php echo klytos_esc_attr( $valId . ' ' . $labId ); ?>"
             data-testid="tasks.stat.<?php echo klytos_esc_attr( (string) $card['id'] ); ?>">
            <span class="k-stat-tile k-stat-tile--<?php echo klytos_esc_attr( (string) $card['tone'] ); ?>" aria-hidden="true">
                <?php klytos_admin_icon( $spriteUrl, (string) $card['glyph'], '' ); ?>
            </span>
            <p class="k-stat-value" id="<?php echo klytos_esc_attr( $valId ); ?>"><?php echo (int) $card['value']; ?></p>
            <p class="k-stat-label" id="<?php echo klytos_esc_attr( $labId ); ?>"><?php echo klytos_esc_html( (string) $card['label'] ); ?></p>
        </div>
    <?php endforeach; ?>
</div>
<?php klytos_do_action( 'admin.tasks.after_stats' ); ?>

<?php // The shipped filter tabs, kept as the design's chips: links carrying
      // aria-current, never tabs and never buttons (accessibility.md §5.4).
      // Removing a shipped filter is not a fidelity decision (D-076). ?>
<nav class="k-filters" aria-label="<?php echo klytos_esc_attr( __( 'tasks.filter_label' ) ); ?>">
    <?php
    $chips = [
        'open'        => [__( 'tasks.filter_open' ), $openCount],
        'in_progress' => [__( 'tasks.filter_in_progress' ), $progressCount],
        'completed'   => [__( 'tasks.filter_completed' ), null],
        'all'         => [__( 'tasks.filter_all' ), null],
    ];
    foreach ( $chips as $chipValue => $chip ) :
        ?>
        <a class="k-chip" href="<?php echo klytos_esc_url( $tasksUrl( $chipValue ) ); ?>"
           <?php echo $statusFilter === $chipValue ? 'aria-current="true"' : ''; ?>
           data-testid="tasks.chip.<?php echo klytos_esc_attr( $chipValue ); ?>">
            <?php echo klytos_esc_html( (string) $chip[0] ); ?>
            <?php if ( $chip[1] !== null ) : ?>
                <span class="k-num"><?php echo (int) $chip[1]; ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php
$tasks = klytos_apply_filters( 'admin.tasks.list', $tasks, $statusFilter );

/*
 * §13's "grouped task list". The manifest does not say the axis, and the
 * product offers two — status and priority. Status is taken: the delta itself
 * says "task STATE is a word plus a glyph", the filter chips already select on
 * status, and grouping by the same axis the filter selects keeps one heading
 * per group instead of a heading that repeats the chip. Asked as DR-013.
 */
$groups = [];
foreach ( $tasks as $task ) {
    $groups[ (string) ( $task['status'] ?? 'open' ) ][] = $task;
}
$groupOrder = ['open', 'in_progress', 'completed', 'dismissed'];
uksort( $groups, static function ( string $a, string $b ) use ( $groupOrder ): int {
    return array_search( $a, $groupOrder, true ) <=> array_search( $b, $groupOrder, true );
} );
?>

<?php if ( $tasks === [] ) : ?>
    <?php
    /*
     * §13's empty state, and it is a GOOD-NEWS one:
     * "Nothing needs your attention." `template-overview-stats.md` §2 —
     * it must read like an answer, not a gap. The shipped screen's version
     * ("No tasks found") read like a search that failed, and told the reader to
     * call an MCP tool.
     */
    ?>
    <section class="k-card k-card--padded" aria-labelledby="tasks-empty-heading" data-testid="tasks.empty">
        <div class="k-card-body">
            <h2 class="k-card-heading" id="tasks-empty-heading"><?php echo klytos_esc_html( __( 'tasks.title' ) ); ?></h2>
            <p class="k-empty">
                <?php klytos_admin_icon( $spriteUrl, 'ks-task_alt', 'k-empty-icon' ); ?>
                <span class="k-empty-text">
                    <?php echo klytos_esc_html(
                        $statusFilter === 'open'
                            ? __( 'tasks.empty_good_news' )
                            : __( 'tasks.empty_filtered' )
                    ); ?>
                </span>
            </p>
        </div>
    </section>
<?php else : ?>
    <?php foreach ( $groups as $groupStatus => $groupTasks ) : ?>
        <?php $groupMeta = $statusMeta( (string) $groupStatus ); ?>
        <section class="k-card k-card--padded"
                 aria-labelledby="tasks-group-<?php echo klytos_esc_attr( (string) $groupStatus ); ?>"
                 data-testid="tasks.group.<?php echo klytos_esc_attr( (string) $groupStatus ); ?>">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="tasks-group-<?php echo klytos_esc_attr( (string) $groupStatus ); ?>">
                    <?php echo klytos_esc_html( (string) $groupMeta['label'] ); ?>
                </h2>

                <ul class="k-collection">
                    <?php foreach ( $groupTasks as $task ) : ?>
                        <?php
                        $taskId   = (string) ( $task['id'] ?? '' );
                        $priority = $priorityMeta( (string) ( $task['priority'] ?? 'medium' ) );
                        $status   = (string) ( $task['status'] ?? 'open' );
                        $slug     = (string) ( $task['page_slug'] ?? '' );
                        $createdAt = (string) ( $task['created_at'] ?? '' );
                        ?>
                        <li class="k-collection-row" data-testid="tasks.row.<?php echo klytos_esc_attr( $taskId ); ?>">
                            <div class="k-collection-main">
                                <span class="k-collection-title">
                                    <?php echo klytos_esc_html( (string) ( $task['description'] ?? '' ) ); ?>
                                </span>

                                <span class="k-collection-meta">
                                    <?php // The state is a WORD before it is a tint (§13's delta). ?>
                                    <span class="k-badge k-badge--<?php echo klytos_esc_attr( (string) $priority['tone'] ); ?>">
                                        <?php klytos_admin_icon( $spriteUrl, (string) $priority['glyph'], 'k-badge-icon' ); ?>
                                        <?php echo klytos_esc_html( (string) $priority['label'] ); ?>
                                    </span>

                                    <?php if ( $slug !== '' ) : ?>
                                        <span><?php echo klytos_esc_html( __( 'tasks.on_page', ['page' => $slug] ) ); ?></span>
                                    <?php endif; ?>

                                    <?php if ( $createdAt !== '' ) : ?>
                                        <?php // Stored UTC, displayed in the reader's zone. The shipped
                                              // screen used bare date(), so every install showed the
                                              // server's clock as if it were the reader's (D-103). ?>
                                        <time datetime="<?php echo klytos_esc_attr( klytos_utc_to_local( $createdAt, 'c' ) ); ?>">
                                            <?php echo klytos_esc_html( klytos_utc_to_local( $createdAt, 'Y-m-d H:i' ) ); ?>
                                        </time>
                                    <?php endif; ?>
                                </span>
                            </div>

                            <?php if ( $status !== 'completed' && $status !== 'dismissed' && klytos_has_permission( 'tasks.manage' ) ) : ?>
                                <div class="k-collection-actions">
                                    <?php
                                    /*
                                     * The shipped screen drew these as "✓" and "✕" with a
                                     * `title` attribute and nothing else. `title` is announced
                                     * by no screen reader reliably and is unreachable by touch,
                                     * so each control now NAMES ITS ROW — the same correction
                                     * entry 37's Remove button got (D-098).
                                     */
                                    $rowName = (string) ( $task['description'] ?? $taskId );
                                    ?>
                                    <form method="post">
                                        <?php klytos_csrf_field(); ?>
                                        <input type="hidden" name="action" value="complete">
                                        <input type="hidden" name="task_id" value="<?php echo klytos_esc_attr( $taskId ); ?>">
                                        <button type="submit" class="k-btn k-btn--secondary k-btn--sm"
                                                aria-label="<?php echo klytos_esc_attr( __( 'tasks.complete_named', ['task' => $rowName] ) ); ?>"
                                                data-testid="tasks.complete.<?php echo klytos_esc_attr( $taskId ); ?>">
                                            <?php echo klytos_esc_html( __( 'tasks.complete' ) ); ?>
                                        </button>
                                    </form>
                                    <form method="post">
                                        <?php klytos_csrf_field(); ?>
                                        <input type="hidden" name="action" value="dismiss">
                                        <input type="hidden" name="task_id" value="<?php echo klytos_esc_attr( $taskId ); ?>">
                                        <button type="submit" class="k-btn k-btn--secondary k-btn--sm"
                                                aria-label="<?php echo klytos_esc_attr( __( 'tasks.dismiss_named', ['task' => $rowName] ) ); ?>"
                                                data-testid="tasks.dismiss.<?php echo klytos_esc_attr( $taskId ); ?>">
                                            <?php echo klytos_esc_html( __( 'tasks.dismiss' ) ); ?>
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
    <?php endforeach; ?>
<?php endif; ?>

<?php klytos_do_action( 'admin.tasks.after' ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
