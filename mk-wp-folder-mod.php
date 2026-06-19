<?php
/**
 * Plugin Name: MK WP Folder Mod
 * Plugin URI:  https://github.com/meksone/mk-wp-folder-mod
 * Description: WP Media Folder automation – post folder sync, logger & bulk cleaner.
 * Version:     0.4.9
 * Author:      Manuel Serrenti (meksONE)
 * Author URI:  https://meksone.com
 * License:     GPL-2.0+
 * Text Domain: mk-wp-folder-mod
 * Domain Path: /languages
 */

define( 'WPMF_TD',      'mk-wp-folder-mod' );
define( 'WPMF_VERSION', '0.4.9' );
define( 'WPMF_GITHUB',  'meksone/mk-wp-folder-mod' );
define( 'WPMF_SLUG',    'mk-wp-folder-mod/mk-wp-folder-mod.php' );

add_action( 'init', function () {
    load_plugin_textdomain( WPMF_TD, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

// ─────────────────────────────────────────────
// GITHUB UPDATER
// ─────────────────────────────────────────────
add_filter( 'pre_set_site_transient_update_plugins', function ( $transient ) {
    if ( empty( $transient->checked ) ) return $transient;

    $response = wp_remote_get(
        'https://api.github.com/repos/' . WPMF_GITHUB . '/releases/latest',
        [ 'headers' => [ 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) ] ]
    );

    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) return $transient;

    $release = json_decode( wp_remote_retrieve_body( $response ) );
    if ( empty( $release->tag_name ) ) return $transient;

    $latest = ltrim( $release->tag_name, 'v' );
    if ( ! version_compare( $latest, WPMF_VERSION, '>' ) ) return $transient;

    $zip_url = '';
    if ( ! empty( $release->assets ) ) {
        foreach ( $release->assets as $asset ) {
            if ( str_ends_with( $asset->name, '.zip' ) ) {
                $zip_url = $asset->browser_download_url;
                break;
            }
        }
    }
    if ( ! $zip_url ) $zip_url = $release->zipball_url;

    $transient->response[ WPMF_SLUG ] = (object) [
        'slug'        => 'mk-wp-folder-mod',
        'plugin'      => WPMF_SLUG,
        'new_version' => $latest,
        'url'         => 'https://github.com/' . WPMF_GITHUB,
        'package'     => $zip_url,
    ];

    return $transient;
} );

add_filter( 'plugins_api', function ( $result, $action, $args ) {
    if ( $action !== 'plugin_information' || ( $args->slug ?? '' ) !== 'mk-wp-folder-mod' ) return $result;

    $response = wp_remote_get(
        'https://api.github.com/repos/' . WPMF_GITHUB . '/releases/latest',
        [ 'headers' => [ 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) ] ]
    );

    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) return $result;

    $release = json_decode( wp_remote_retrieve_body( $response ) );
    if ( empty( $release->tag_name ) ) return $result;

    $zip_url = '';
    if ( ! empty( $release->assets ) ) {
        foreach ( $release->assets as $asset ) {
            if ( str_ends_with( $asset->name, '.zip' ) ) {
                $zip_url = $asset->browser_download_url;
                break;
            }
        }
    }
    if ( ! $zip_url ) $zip_url = $release->zipball_url;

    return (object) [
        'name'          => 'MK WP Folder Mod',
        'slug'          => 'mk-wp-folder-mod',
        'version'       => ltrim( $release->tag_name, 'v' ),
        'author'        => '<a href="https://meksone.com">meksONE</a>',
        'homepage'      => 'https://github.com/' . WPMF_GITHUB,
        'download_link' => $zip_url,
        'sections'      => [ 'description' => $release->body ?? '' ],
    ];
}, 10, 3 );

// ─────────────────────────────────────────────
// 1. LOGGING
// ─────────────────────────────────────────────
function wpmf_custom_logger( string $message ): void {
    $log = get_option( 'wpmf_automation_log', [] );
    array_unshift( $log, [ 'time' => current_time( 'd-m-Y H:i:s' ), 'msg' => $message ] );
    update_option( 'wpmf_automation_log', array_slice( $log, 0, 100 ) );
}

// ─────────────────────────────────────────────
// 2. SETTINGS HELPERS
// ─────────────────────────────────────────────
function wpmf_auto_get_settings(): array {
    $defaults = [
        'folder_name_length' => 30,
        'post_name_folder'   => false,
        'cpt_enabled'        => [],
    ];
    $saved = get_option( 'wpmf_auto_settings', [] );
    return array_merge( $defaults, $saved );
}

function wpmf_auto_get_folder_length(): int {
    $s = wpmf_auto_get_settings();
    $v = (int) $s['folder_name_length'];
    return ( $v >= 5 && $v <= 100 ) ? $v : 30;
}

function wpmf_auto_cpt_is_enabled( string $post_type ): bool {
    $s = wpmf_auto_get_settings();
    return ! empty( $s['cpt_enabled'][ $post_type ] );
}

// ─────────────────────────────────────────────
// 3. ADMIN MENU
// ─────────────────────────────────────────────
add_action( 'admin_menu', function () {
    add_submenu_page(
        'upload.php',
        __( 'WPMF Log', WPMF_TD ),
        __( 'WPMF Log', WPMF_TD ),
        'manage_options', 'wpmf-automation-log', 'wpmf_render_log_page'
    );
    add_submenu_page(
        'upload.php',
        __( 'WPMF Settings', WPMF_TD ),
        __( 'WPMF Settings', WPMF_TD ),
        'manage_options', 'wpmf-auto-settings', 'wpmf_render_settings_page'
    );
    add_submenu_page(
        'upload.php',
        __( 'WPMF Folder Manager', WPMF_TD ),
        __( 'WPMF Folder Manager', WPMF_TD ),
        'manage_options', 'wpmf-folder-manager', 'wpmf_render_folder_manager_page'
    );
} );

// ─────────────────────────────────────────────
// 4. LOG PAGE
// ─────────────────────────────────────────────
function wpmf_render_log_page(): void {
    $log = get_option( 'wpmf_automation_log', [] );
    echo '<div class="wrap"><h1>📜 ' . esc_html__( 'WP Media Folder Automation Log', WPMF_TD ) . '</h1>';
    echo '<table class="wp-list-table widefat fixed striped" style="margin-top:20px;">
            <thead><tr><th width="20%">' . esc_html__( 'Date/Time', WPMF_TD ) . '</th><th>' . esc_html__( 'Event', WPMF_TD ) . '</th></tr></thead><tbody>';
    if ( empty( $log ) ) {
        echo '<tr><td colspan="2">' . esc_html__( 'No events.', WPMF_TD ) . '</td></tr>';
    } else {
        foreach ( $log as $entry ) {
            echo '<tr><td><strong>' . esc_html( $entry['time'] ) . '</strong></td>
                      <td>' . esc_html( $entry['msg'] ) . '</td></tr>';
        }
    }
    echo '</tbody></table></div>';
}

// ─────────────────────────────────────────────
// 5. SETTINGS PAGE
// ─────────────────────────────────────────────
function wpmf_render_settings_page(): void {
    if (
        isset( $_POST['wpmf_save_settings'] ) &&
        check_admin_referer( 'wpmf_settings_action', 'wpmf_settings_nonce' )
    ) {
        $length = isset( $_POST['folder_name_length'] ) ? (int) $_POST['folder_name_length'] : 30;
        $length = max( 5, min( 100, $length ) );

        $post_name_folder = ! empty( $_POST['post_name_folder'] );

        $cpt_enabled = [];
        if ( ! empty( $_POST['cpt_enabled'] ) && is_array( $_POST['cpt_enabled'] ) ) {
            foreach ( $_POST['cpt_enabled'] as $cpt ) {
                $cpt_enabled[ sanitize_key( $cpt ) ] = 1;
            }
        }

        update_option( 'wpmf_auto_settings', [
            'folder_name_length' => $length,
            'post_name_folder'   => $post_name_folder,
            'cpt_enabled'        => $cpt_enabled,
        ] );

        echo '<div class="updated notice is-dismissible"><p>✅ ' . esc_html__( 'Settings saved.', WPMF_TD ) . '</p></div>';
    }

    $settings = wpmf_auto_get_settings();
    $cpts     = get_post_types( [ 'public' => true, '_builtin' => false ], 'objects' );
    ?>
    <div class="wrap">
        <h1>⚙️ <?php esc_html_e( 'WPMF Automation Settings', WPMF_TD ); ?></h1>
        <form method="post">
            <?php wp_nonce_field( 'wpmf_settings_action', 'wpmf_settings_nonce' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Post name folder', WPMF_TD ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="post_name_folder" value="1"
                                <?php checked( ! empty( $settings['post_name_folder'] ) ); ?> />
                            <?php esc_html_e( 'Create a subfolder named after the post inside Year/Month', WPMF_TD ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'When disabled, media are placed directly in the month folder.', WPMF_TD ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="folder_name_length"><?php esc_html_e( 'Maximum folder name length', WPMF_TD ); ?></label>
                    </th>
                    <td>
                        <input type="number" id="folder_name_length" name="folder_name_length"
                            value="<?php echo esc_attr( $settings['folder_name_length'] ); ?>"
                            min="5" max="100" style="width:80px;" />
                        <p class="description"><?php esc_html_e( 'Maximum characters for folder names (5–100). Default: 30.', WPMF_TD ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Enabled Custom Post Types', WPMF_TD ); ?></th>
                    <td>
                        <?php if ( empty( $cpts ) ) : ?>
                            <em><?php esc_html_e( 'No public CPTs found.', WPMF_TD ); ?></em>
                        <?php else : ?>
                            <?php foreach ( $cpts as $cpt ) : ?>
                                <label style="display:block; margin-bottom:6px;">
                                    <input type="checkbox" name="cpt_enabled[]"
                                        value="<?php echo esc_attr( $cpt->name ); ?>"
                                        <?php checked( ! empty( $settings['cpt_enabled'][ $cpt->name ] ) ); ?> />
                                    <strong><?php echo esc_html( $cpt->labels->name ); ?></strong>
                                    <code style="margin-left:6px;"><?php echo esc_html( $cpt->name ); ?></code>
                                </label>
                            <?php endforeach; ?>
                            <p class="description">
                                <?php esc_html_e( 'Selected CPTs will have a root folder named after the post type. All associated media will be placed inside it.', WPMF_TD ); ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="wpmf_save_settings" value="1" class="button button-primary">
                    💾 <?php esc_html_e( 'Save settings', WPMF_TD ); ?>
                </button>
            </p>
        </form>
    </div>
    <?php
}

// ─────────────────────────────────────────────
// 6. FOLDER MANAGER PAGE
// ─────────────────────────────────────────────
function wpmf_render_folder_manager_page(): void {
    $message = '';

    if ( isset( $_POST['wpmf_do_delete_empty'] ) &&
        check_admin_referer( 'wpmf_folder_manager_action', 'wpmf_folder_manager_nonce' ) ) {
        $deleted = wpmf_delete_empty_folders();
        $message = sprintf(
            /* translators: %s: number of deleted folders */
            __( '✅ Deleted <strong>%s</strong> empty folders (folders protected with ! were kept).', WPMF_TD ),
            $deleted
        );
    }

    if ( isset( $_POST['wpmf_do_delete_all'] ) &&
        check_admin_referer( 'wpmf_folder_manager_action', 'wpmf_folder_manager_nonce' ) ) {
        $deleted = wpmf_delete_all_folders_except_protected();
        $message = sprintf(
            /* translators: %s: number of deleted folders */
            __( '✅ Deleted <strong>%s</strong> unprotected folders (folders protected with ! were kept).', WPMF_TD ),
            $deleted
        );
    }

    if ( isset( $_POST['wpmf_do_fix_root'] ) &&
        check_admin_referer( 'wpmf_folder_manager_action', 'wpmf_folder_manager_nonce' ) ) {
        $result  = wpmf_fix_root_duplicates();
        $message = sprintf(
            /* translators: 1: total scanned, 2: fixed duplicates */
            __( '✅ Scanned <strong>%1$s</strong> attachments. Fixed <strong>%2$s</strong> with multiple assignments.', WPMF_TD ),
            $result['total_scanned'],
            $result['fixed_duplicates']
        );
    }

    if ( isset( $_POST['wpmf_do_assign_unassigned'] ) &&
        check_admin_referer( 'wpmf_folder_manager_action', 'wpmf_folder_manager_nonce' ) ) {
        $moved   = wpmf_assign_unassigned_media();
        $message = sprintf(
            /* translators: %s: number of moved media */
            __( '✅ Moved <strong>%s</strong> unassigned media to <em>Unassigned</em>.', WPMF_TD ),
            $moved
        );
    }

    if ( isset( $_POST['wpmf_do_assign_orphaned'] ) &&
        check_admin_referer( 'wpmf_folder_manager_action', 'wpmf_folder_manager_nonce' ) ) {
        $result  = wpmf_assign_orphaned_media();
        $message = sprintf(
            /* translators: 1: linked to post, 2: by date */
            __( '✅ Processed orphaned media: <strong>%1$s</strong> linked to a post, <strong>%2$s</strong> assigned by upload date.', WPMF_TD ),
            $result['linked'],
            $result['by_date']
        );
    }

    if ( $message ) {
        echo '<div class="updated notice is-dismissible"><p>' . $message . '</p></div>';
    }

    $taxonomy  = 'wpmf-category';
    $all_terms = taxonomy_exists( $taxonomy )
        ? get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'id=>name' ] )
        : [];

    $protected_ids = [];
    $empty_ids     = [];
    $deletable_ids = [];

    if ( ! empty( $all_terms ) && ! is_wp_error( $all_terms ) ) {
        foreach ( $all_terms as $tid => $tname ) {
            if ( str_starts_with( $tname, '!' ) ) {
                $protected_ids = array_merge( $protected_ids, wpmf_collect_protected_ids( (int) $tid, $taxonomy ) );
            }
        }
        $protected_ids = array_unique( $protected_ids );

        foreach ( $all_terms as $tid => $tname ) {
            if ( in_array( (int) $tid, $protected_ids, true ) ) continue;
            $deletable_ids[] = (int) $tid;
            if ( wpmf_folder_is_empty( (int) $tid ) ) {
                $empty_ids[] = (int) $tid;
            }
        }
    }

    global $wpdb;

    $duplicate_count = (int) $wpdb->get_var(
        "SELECT COUNT(*)
           FROM (
               SELECT tr.object_id
                 FROM {$wpdb->term_relationships} tr
                 JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                WHERE tt.taxonomy = 'wpmf-category'
                GROUP BY tr.object_id
               HAVING COUNT(*) > 1
           ) AS dupes"
    );

    $unassigned_count = wpmf_count_unassigned_media();
    $orphaned_count   = wpmf_count_orphaned_media();
    ?>
    <div class="wrap">
        <h1>🗂️ <?php esc_html_e( 'WPMF Folder Manager', WPMF_TD ); ?></h1>
        <p><?php esc_html_e( 'Folders starting with ! and all their descendants are always protected.', WPMF_TD ); ?></p>

        <div style="display:flex; gap:20px; flex-wrap:wrap; margin:20px 0;">
            <div style="flex:1; min-width:180px; background:#d4edda; border:1px solid #c3e6cb; padding:15px; border-radius:6px;">
                <h3 style="margin-top:0;">🔒 <?php esc_html_e( 'Protected', WPMF_TD ); ?></h3>
                <p style="font-size:2em; margin:0; font-weight:bold;"><?php echo count( $protected_ids ); ?></p>
                <p style="margin:4px 0 0;"><?php esc_html_e( 'safe folders', WPMF_TD ); ?></p>
            </div>
            <div style="flex:1; min-width:180px; background:#fff3cd; border:1px solid #ffc107; padding:15px; border-radius:6px;">
                <h3 style="margin-top:0;">🕳️ <?php esc_html_e( 'Empty', WPMF_TD ); ?></h3>
                <p style="font-size:2em; margin:0; font-weight:bold;"><?php echo count( $empty_ids ); ?></p>
                <p style="margin:4px 0 0;"><?php esc_html_e( 'folders without media', WPMF_TD ); ?></p>
            </div>
            <div style="flex:1; min-width:180px; background:#f8d7da; border:1px solid #f5c6cb; padding:15px; border-radius:6px;">
                <h3 style="margin-top:0;">🗑️ <?php esc_html_e( 'Unprotected', WPMF_TD ); ?></h3>
                <p style="font-size:2em; margin:0; font-weight:bold;"><?php echo count( $deletable_ids ); ?></p>
                <p style="margin:4px 0 0;"><?php esc_html_e( 'deletable folders', WPMF_TD ); ?></p>
            </div>
            <div style="flex:1; min-width:180px; background:#d1ecf1; border:1px solid #bee5eb; padding:15px; border-radius:6px;">
                <h3 style="margin-top:0;">⚠️ <?php esc_html_e( 'Duplicates', WPMF_TD ); ?></h3>
                <p style="font-size:2em; margin:0; font-weight:bold;"><?php echo $duplicate_count; ?></p>
                <p style="margin:4px 0 0;"><?php esc_html_e( 'attachments in multiple folders', WPMF_TD ); ?></p>
            </div>
            <div style="flex:1; min-width:180px; background:#e2d9f3; border:1px solid #c5b3e6; padding:15px; border-radius:6px;">
                <h3 style="margin-top:0;">📭 <?php esc_html_e( 'Unassigned', WPMF_TD ); ?></h3>
                <p style="font-size:2em; margin:0; font-weight:bold;"><?php echo $unassigned_count; ?></p>
                <p style="margin:4px 0 0;"><?php esc_html_e( 'media without folder', WPMF_TD ); ?></p>
            </div>
            <div style="flex:1; min-width:180px; background:#fde8d8; border:1px solid #f5a66d; padding:15px; border-radius:6px;">
                <h3 style="margin-top:0;">🔗 <?php esc_html_e( 'Orphaned', WPMF_TD ); ?></h3>
                <p style="font-size:2em; margin:0; font-weight:bold;"><?php echo $orphaned_count; ?></p>
                <p style="margin:4px 0 0;"><?php esc_html_e( 'media without parent post', WPMF_TD ); ?></p>
            </div>
        </div>

        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <form method="post">
                <?php wp_nonce_field( 'wpmf_folder_manager_action', 'wpmf_folder_manager_nonce' ); ?>
                <button type="submit" name="wpmf_do_assign_unassigned" value="1" class="button button-secondary"
                    <?php echo $unassigned_count === 0 ? 'disabled' : ''; ?>
                    onclick="return confirm('<?php echo esc_js( sprintf(
                        /* translators: %d: number of media items */
                        __( 'Move %d media to the "Unassigned" folder?', WPMF_TD ),
                        $unassigned_count
                    ) ); ?>');">
                    📭 <?php printf( esc_html__( 'Collect unassigned (%d)', WPMF_TD ), $unassigned_count ); ?>
                </button>
            </form>
            <form method="post">
                <?php wp_nonce_field( 'wpmf_folder_manager_action', 'wpmf_folder_manager_nonce' ); ?>
                <button type="submit" name="wpmf_do_assign_orphaned" value="1" class="button button-secondary"
                    <?php echo $orphaned_count === 0 ? 'disabled' : ''; ?>
                    onclick="return confirm('<?php echo esc_js( sprintf(
                        /* translators: %d: number of orphaned media */
                        __( 'Try to assign %d orphaned media to posts or date folders?', WPMF_TD ),
                        $orphaned_count
                    ) ); ?>');">
                    🔗 <?php printf( esc_html__( 'Tenta di assegnare ai post (%d)', WPMF_TD ), $orphaned_count ); ?>
                </button>
            </form>
            <form method="post">
                <?php wp_nonce_field( 'wpmf_folder_manager_action', 'wpmf_folder_manager_nonce' ); ?>
                <button type="submit" name="wpmf_do_fix_root" value="1" class="button button-secondary"
                    <?php echo $duplicate_count === 0 ? 'disabled' : ''; ?>
                    onclick="return confirm('<?php echo esc_js( sprintf(
                        /* translators: %d: number of attachments */
                        __( 'Fix %d attachments with multiple folder assignments?', WPMF_TD ),
                        $duplicate_count
                    ) ); ?>');">
                    🔧 <?php printf( esc_html__( 'Fix duplicates (%d)', WPMF_TD ), $duplicate_count ); ?>
                </button>
            </form>
            <form method="post">
                <?php wp_nonce_field( 'wpmf_folder_manager_action', 'wpmf_folder_manager_nonce' ); ?>
                <button type="submit" name="wpmf_do_delete_empty" value="1" class="button button-secondary"
                    <?php echo empty( $empty_ids ) ? 'disabled' : ''; ?>
                    onclick="return confirm('<?php echo esc_js( sprintf(
                        /* translators: %d: number of empty folders */
                        __( 'Delete %d empty folders?', WPMF_TD ),
                        count( $empty_ids )
                    ) ); ?>');">
                    🕳️ <?php printf( esc_html__( 'Delete empty folders (%d)', WPMF_TD ), count( $empty_ids ) ); ?>
                </button>
            </form>
            <form method="post">
                <?php wp_nonce_field( 'wpmf_folder_manager_action', 'wpmf_folder_manager_nonce' ); ?>
                <button type="submit" name="wpmf_do_delete_all" value="1" class="button button-primary"
                    <?php echo empty( $deletable_ids ) ? 'disabled' : ''; ?>
                    onclick="return confirm('<?php echo esc_js( sprintf(
                        /* translators: %d: number of unprotected folders */
                        __( 'Delete ALL %d unprotected folders? This cannot be undone.', WPMF_TD ),
                        count( $deletable_ids )
                    ) ); ?>');">
                    🗑️ <?php printf( esc_html__( 'Delete unprotected folders (%d)', WPMF_TD ), count( $deletable_ids ) ); ?>
                </button>
            </form>
        </div>
    </div>
    <?php
}

// ─────────────────────────────────────────────
// 7. BULK ACTIONS
// ─────────────────────────────────────────────
foreach ( [ 'edit-post', 'upload' ] as $_wpmf_screen ) {
    add_filter( "bulk_actions-{$_wpmf_screen}", function ( $actions ) {
        $actions['wpmf_clean_meta'] = __( 'Delete WPMF Meta', WPMF_TD );
        return $actions;
    } );
    add_filter( "handle_bulk_actions-{$_wpmf_screen}", 'wpmf_handle_clean_meta_bulk', 10, 3 );
}

function wpmf_handle_clean_meta_bulk( string $redirect_to, string $doaction, array $post_ids ): string {
    if ( $doaction !== 'wpmf_clean_meta' ) return $redirect_to;
    foreach ( $post_ids as $post_id ) {
        delete_post_meta( $post_id, '_wpmf_automated_folder_id' );
    }
    wpmf_custom_logger( '🧹 Pulizia Meta eseguita su ' . count( $post_ids ) . ' elementi.' );
    return add_query_arg( 'wpmf_meta_cleaned', count( $post_ids ), $redirect_to );
}

// ─────────────────────────────────────────────
// 8. BULK ACTION – detach folder per CPT
// ─────────────────────────────────────────────
add_action( 'init', function () {
    $settings = wpmf_auto_get_settings();
    if ( empty( $settings['cpt_enabled'] ) ) return;
    foreach ( array_keys( $settings['cpt_enabled'] ) as $post_type ) {
        $screen = 'edit-' . $post_type;
        add_filter( "bulk_actions-{$screen}", function ( $actions ) {
            $actions['wpmf_detach_folder'] = __( 'Detach WPMF folder', WPMF_TD );
            return $actions;
        } );
        add_filter( "handle_bulk_actions-{$screen}", function ( $redirect_to, $doaction, $post_ids ) {
            if ( $doaction !== 'wpmf_detach_folder' ) return $redirect_to;
            $detached = 0;
            foreach ( $post_ids as $post_id ) {
                $post_id   = (int) $post_id;
                $folder_id = (int) get_post_meta( $post_id, '_wpmf_automated_folder_id', true );
                if ( $folder_id ) {
                    $attachments = get_children( [ 'post_parent' => $post_id, 'post_type' => 'attachment', 'fields' => 'ids' ] );
                    foreach ( $attachments as $att_id ) {
                        wp_remove_object_terms( (int) $att_id, [ $folder_id ], 'wpmf-category' );
                    }
                }
                delete_post_meta( $post_id, '_wpmf_automated_folder_id' );
                $detached++;
            }
            wpmf_custom_logger( "🔗 Scollegata cartella WPMF da {$detached} elementi CPT." );
            return add_query_arg( 'wpmf_folder_detached', $detached, $redirect_to );
        }, 10, 3 );
    }
} );

add_action( 'admin_notices', function () {
    if ( ! empty( $_REQUEST['wpmf_meta_cleaned'] ) ) {
        printf(
            '<div class="updated notice is-dismissible"><p>' .
            /* translators: %d: number of items */
            esc_html__( 'Cleanup complete: removed WPMF meta from %d items.', WPMF_TD ) .
            '</p></div>',
            intval( $_REQUEST['wpmf_meta_cleaned'] )
        );
    }
    if ( ! empty( $_REQUEST['wpmf_folder_detached'] ) ) {
        printf(
            '<div class="updated notice is-dismissible"><p>✅ ' .
            /* translators: %d: number of items */
            esc_html__( 'WPMF folder detached from %d items.', WPMF_TD ) .
            '</p></div>',
            intval( $_REQUEST['wpmf_folder_detached'] )
        );
    }
} );

// ─────────────────────────────────────────────
// 9. CORE HELPERS
// ─────────────────────────────────────────────
function wpmf_sanitize_folder_name( string $title, int $max_length = 0 ): string {
    if ( $max_length <= 0 ) $max_length = wpmf_auto_get_folder_length();
    $title = wp_strip_all_tags( $title );
    $title = html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    $title = preg_replace( '/[^\p{L}\p{N}\s\-]/u', '', $title );
    $title = preg_replace( '/\s+/', ' ', trim( $title ) );
    if ( mb_strlen( $title ) <= $max_length ) return $title;
    $truncated  = mb_substr( $title, 0, $max_length );
    $last_space = mb_strrpos( $truncated, ' ' );
    return $last_space !== false ? rtrim( mb_substr( $truncated, 0, $last_space ) ) : $truncated;
}

function wpmf_month_map(): array {
    return [
        '01' => [ 'name' => __( '01 - January',   WPMF_TD ), 'color' => '#e74c3c' ],
        '02' => [ 'name' => __( '02 - February',  WPMF_TD ), 'color' => '#e67e22' ],
        '03' => [ 'name' => __( '03 - March',     WPMF_TD ), 'color' => '#f1c40f' ],
        '04' => [ 'name' => __( '04 - April',     WPMF_TD ), 'color' => '#2ecc71' ],
        '05' => [ 'name' => __( '05 - May',       WPMF_TD ), 'color' => '#1abc9c' ],
        '06' => [ 'name' => __( '06 - June',      WPMF_TD ), 'color' => '#3498db' ],
        '07' => [ 'name' => __( '07 - July',      WPMF_TD ), 'color' => '#5dade2' ],
        '08' => [ 'name' => __( '08 - August',    WPMF_TD ), 'color' => '#9b59b6' ],
        '09' => [ 'name' => __( '09 - September', WPMF_TD ), 'color' => '#e91e63' ],
        '10' => [ 'name' => __( '10 - October',   WPMF_TD ), 'color' => '#ff5722' ],
        '11' => [ 'name' => __( '11 - November',  WPMF_TD ), 'color' => '#795548' ],
        '12' => [ 'name' => __( '12 - December',  WPMF_TD ), 'color' => '#00bcd4' ],
    ];
}

function wpmf_pick_color( string $name ): string {
    $palette = [ '#e74c3c','#e67e22','#f1c40f','#2ecc71','#1abc9c','#3498db','#9b59b6','#e91e63','#00bcd4','#ff5722' ];
    return $palette[ abs( crc32( $name ) ) % count( $palette ) ];
}

function wpmf_set_folder_color( int $term_id, string $color, bool $force = false ): void {
    if ( ! function_exists( 'wpmfGetOption' ) || ! function_exists( 'wpmfSetOption' ) ) return;
    $colors = wpmfGetOption( 'folder_color' );
    if ( ! is_array( $colors ) ) $colors = [];
    if ( ! $force && isset( $colors[ $term_id ] ) && $colors[ $term_id ] !== '#8f8f8f' ) return;
    $colors[ $term_id ] = $color;
    wpmfSetOption( 'folder_color', $colors );
}

function wpmf_find_folder_in_db( string $name, int $parent_id ): int {
    global $wpdb;
    $term_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT t.term_id FROM {$wpdb->terms} AS t JOIN {$wpdb->term_taxonomy} AS tt ON tt.term_id = t.term_id WHERE t.name = %s AND tt.taxonomy = 'wpmf-category' AND tt.parent = %d LIMIT 1",
        $name, $parent_id
    ) );
    return $term_id ? (int) $term_id : 0;
}

function wpmf_get_or_create_folder( string $name, int $parent_id = 0, string $color = '' ): int {
    $taxonomy = 'wpmf-category';
    if ( ! taxonomy_exists( $taxonomy ) ) return 0;
    $existing_id = wpmf_find_folder_in_db( $name, $parent_id );
    if ( $existing_id ) return $existing_id;
    $result = wp_insert_term( $name, $taxonomy, [ 'parent' => $parent_id ] );
    if ( is_wp_error( $result ) ) {
        $existing = $result->get_error_data( 'term_exists' );
        if ( $existing ) return (int) $existing;
        wpmf_custom_logger( '⚠️ Errore creazione cartella "' . $name . '": ' . $result->get_error_message() );
        return 0;
    }
    $term_id     = (int) $result['term_id'];
    $final_color = $color ?: wpmf_pick_color( $name );
    wpmf_set_folder_color( $term_id, $final_color );
    wpmf_custom_logger( '📁 Creata cartella: ' . $name . ' (colore: ' . $final_color . ')' . ( $parent_id ? " (parent: $parent_id)" : ' (root)' ) );
    return $term_id;
}

function wpmf_build_date_hierarchy( string $post_date, string $folder_name ): int {
    $year      = date( 'Y', strtotime( $post_date ) );
    $month_num = date( 'm', strtotime( $post_date ) );
    $month_map = wpmf_month_map();
    $month     = $month_map[ $month_num ];
    $year_id   = wpmf_get_or_create_folder( $year, 0, '#5d6d7e' );
    if ( ! $year_id ) return 0;
    $month_id  = wpmf_get_or_create_folder( $month['name'], $year_id, $month['color'] );
    if ( ! $month_id ) return 0;
    $s = wpmf_auto_get_settings();
    if ( empty( $s['post_name_folder'] ) ) return $month_id;
    return wpmf_get_or_create_folder( $folder_name, $month_id );
}

function wpmf_build_cpt_folder( string $post_type ): int {
    $cpt_obj    = get_post_type_object( $post_type );
    $label      = $cpt_obj ? $cpt_obj->labels->name : $post_type;
    $safe_label = wpmf_sanitize_folder_name( $label );
    $color      = wpmf_pick_color( $post_type );
    return wpmf_get_or_create_folder( $safe_label, 0, $color );
}

// ─────────────────────────────────────────────
// 10. FOLDER OPERATIONS
// ─────────────────────────────────────────────
function wpmf_collect_protected_ids( int $term_id, string $taxonomy ): array {
    global $wpdb;
    // Iterative BFS using a single query per level — avoids N recursive DB calls.
    $protected = [];
    $queue     = [ $term_id ];
    while ( ! empty( $queue ) ) {
        $protected = array_merge( $protected, $queue );
        $in        = implode( ',', array_map( 'intval', $queue ) );
        $queue     = $wpdb->get_col(
            "SELECT tt.term_id FROM {$wpdb->term_taxonomy} tt WHERE tt.taxonomy = 'wpmf-category' AND tt.parent IN ({$in})"
        );
        $queue = array_map( 'intval', $queue );
    }
    return array_unique( $protected );
}

function wpmf_folder_is_empty( int $term_id ): bool {
    global $wpdb;
    $count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->term_relationships} tr JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE tr.term_taxonomy_id = (SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = 'wpmf-category') AND p.post_type = 'attachment'",
        $term_id
    ) );
    if ( $count > 0 ) return false;
    $children = get_terms( [ 'taxonomy' => 'wpmf-category', 'parent' => $term_id, 'hide_empty' => false, 'fields' => 'ids', 'number' => 1 ] );
    return empty( $children ) || is_wp_error( $children );
}

function wpmf_get_protected_ids(): array {
    global $wpdb;
    $roots = $wpdb->get_col(
        "SELECT tt.term_id FROM {$wpdb->term_taxonomy} tt JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE tt.taxonomy = 'wpmf-category' AND t.name LIKE '!%'"
    );
    if ( empty( $roots ) ) return [];
    $protected = [];
    foreach ( $roots as $root_id ) {
        $protected = array_merge( $protected, wpmf_collect_protected_ids( (int) $root_id, 'wpmf-category' ) );
    }
    return array_unique( array_map( 'intval', $protected ) );
}

function wpmf_bulk_delete_terms( array $term_ids ): int {
    global $wpdb;
    if ( empty( $term_ids ) ) return 0;

    // Resolve term_taxonomy_ids for all targets in one query.
    $in       = implode( ',', $term_ids );
    $tt_ids   = $wpdb->get_col(
        "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id IN ({$in}) AND taxonomy = 'wpmf-category'"
    );
    if ( empty( $tt_ids ) ) return 0;
    $tt_in = implode( ',', array_map( 'intval', $tt_ids ) );

    // Bulk delete in dependency order: relationships → taxonomy → terms.
    $wpdb->query( "DELETE FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ({$tt_in})" );
    $wpdb->query( "DELETE FROM {$wpdb->term_taxonomy}      WHERE term_taxonomy_id IN ({$tt_in})" );
    $wpdb->query( "DELETE FROM {$wpdb->terms}              WHERE term_id           IN ({$in})" );

    // Remove folder_color option entries for deleted terms.
    if ( function_exists( 'wpmfGetOption' ) && function_exists( 'wpmfSetOption' ) ) {
        $colors = wpmfGetOption( 'folder_color' );
        if ( is_array( $colors ) ) {
            foreach ( $term_ids as $tid ) unset( $colors[ $tid ] );
            wpmfSetOption( 'folder_color', $colors );
        }
    }

    // Single cache flush after all deletes.
    clean_term_cache( $term_ids, 'wpmf-category' );
    wp_cache_delete( 'all_ids', 'wpmf-category' );

    return count( $term_ids );
}

function wpmf_delete_empty_folders(): int {
    $taxonomy = 'wpmf-category';
    if ( ! taxonomy_exists( $taxonomy ) ) return 0;

    global $wpdb;
    $protected_ids = wpmf_get_protected_ids();
    $total_deleted = 0;

    // Iteratively delete leaf-empty folders until none remain.
    // Each pass: find terms with no attachment relationships AND no children.
    do {
        $exclude = empty( $protected_ids )
            ? ''
            : 'AND tt.term_id NOT IN (' . implode( ',', $protected_ids ) . ')';

        $empty_ids = $wpdb->get_col(
            "SELECT tt.term_id
               FROM {$wpdb->term_taxonomy} tt
              WHERE tt.taxonomy = 'wpmf-category'
                {$exclude}
                AND tt.count = 0
                AND tt.term_id NOT IN (
                    SELECT DISTINCT parent FROM {$wpdb->term_taxonomy}
                     WHERE taxonomy = 'wpmf-category' AND parent > 0
                )"
        );

        if ( empty( $empty_ids ) ) break;
        $empty_ids     = array_map( 'intval', $empty_ids );
        $total_deleted += wpmf_bulk_delete_terms( $empty_ids );
    } while ( ! empty( $empty_ids ) );

    if ( $total_deleted > 0 ) wpmf_custom_logger( "🕳️ Eliminate {$total_deleted} cartelle vuote." );
    return $total_deleted;
}

function wpmf_delete_all_folders_except_protected(): int {
    $taxonomy = 'wpmf-category';
    if ( ! taxonomy_exists( $taxonomy ) ) return 0;

    global $wpdb;
    $all_ids = $wpdb->get_col(
        "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'wpmf-category'"
    );
    if ( empty( $all_ids ) ) return 0;

    $all_ids       = array_map( 'intval', $all_ids );
    $protected_ids = wpmf_get_protected_ids();
    $to_delete     = empty( $protected_ids )
        ? $all_ids
        : array_values( array_diff( $all_ids, $protected_ids ) );

    if ( empty( $to_delete ) ) return 0;

    $deleted = wpmf_bulk_delete_terms( $to_delete );
    if ( $deleted > 0 ) wpmf_custom_logger( "🗑️ Reset cartelle: eliminate {$deleted} cartelle (protette con ! mantenute)." );
    return $deleted;
}

function wpmf_fix_root_duplicates(): array {
    global $wpdb;
    $duplicates = $wpdb->get_results(
        "SELECT tr.object_id, COUNT(*) AS term_count FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.taxonomy = 'wpmf-category' GROUP BY tr.object_id HAVING COUNT(*) > 1"
    );
    $fixed   = 0;
    $scanned = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT tr.object_id) FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.taxonomy = 'wpmf-category'" );
    foreach ( $duplicates as $row ) {
        $att_id = (int) $row->object_id;
        $terms  = wp_get_object_terms( $att_id, 'wpmf-category', [ 'fields' => 'ids', 'orderby' => 'term_id', 'order' => 'ASC' ] );
        if ( is_wp_error( $terms ) || count( $terms ) <= 1 ) continue;
        $keep = [ (int) $terms[0] ];
        wp_set_object_terms( $att_id, $keep, 'wpmf-category', false );
        wpmf_custom_logger( "🔧 Allegato #{$att_id}: mantenuto solo term #{$keep[0]}, rimossi " . ( count( $terms ) - 1 ) . " duplicati." );
        $fixed++;
    }
    if ( $fixed > 0 ) wpmf_custom_logger( "🔧 Fix completato: corretti {$fixed} allegati su {$scanned} scansionati." );
    return [ 'fixed_duplicates' => $fixed, 'total_scanned' => $scanned ];
}

function wpmf_count_unassigned_media(): int {
    global $wpdb;
    return (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.post_type = 'attachment' AND p.post_status != 'trash' AND p.ID NOT IN (SELECT tr.object_id FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.taxonomy = 'wpmf-category')"
    );
}

function wpmf_assign_unassigned_media(): int {
    global $wpdb;
    $folder_id = wpmf_get_or_create_folder( __( 'Unassigned', WPMF_TD ), 0, '#95a5a6' );
    if ( ! $folder_id ) return 0;
    $batch_size = 500;
    $offset     = 0;
    $moved      = 0;
    do {
        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p WHERE p.post_type = 'attachment' AND p.post_status != 'trash' AND p.ID NOT IN (SELECT tr.object_id FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.taxonomy = 'wpmf-category') LIMIT %d OFFSET %d",
            $batch_size, $offset
        ) );
        foreach ( $ids as $att_id ) {
            wp_set_object_terms( (int) $att_id, [ $folder_id ], 'wpmf-category', false );
            $moved++;
        }
    } while ( count( $ids ) === $batch_size );
    if ( $moved > 0 ) wpmf_custom_logger( "📭 Assegnati {$moved} media senza cartella a \"Non assegnati\"." );
    return $moved;
}

function wpmf_count_orphaned_media(): int {
    global $wpdb;
    // Attachments with no wpmf-category term AND no post_parent.
    return (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} p
          WHERE p.post_type = 'attachment'
            AND p.post_status != 'trash'
            AND p.post_parent = 0
            AND p.ID NOT IN (
                SELECT tr.object_id
                  FROM {$wpdb->term_relationships} tr
                  JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                 WHERE tt.taxonomy = 'wpmf-category'
            )"
    );
}

function wpmf_assign_orphaned_media(): array {
    global $wpdb;

    $batch_size = 200;
    $linked     = 0;
    $by_date    = 0;

    // Build a map: attachment_id => post_id for all featured images in one query.
    $featured = $wpdb->get_results(
        "SELECT meta_value AS att_id, post_id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id'",
        OBJECT_K  // keyed by att_id
    );
    // $featured[att_id]->post_id

    $offset = 0;
    do {
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.ID, p.post_date
               FROM {$wpdb->posts} p
              WHERE p.post_type = 'attachment'
                AND p.post_status != 'trash'
                AND p.post_parent = 0
                AND p.ID NOT IN (
                    SELECT tr.object_id
                      FROM {$wpdb->term_relationships} tr
                      JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                     WHERE tt.taxonomy = 'wpmf-category'
                )
              LIMIT %d OFFSET %d",
            $batch_size, $offset
        ) );

        foreach ( $rows as $row ) {
            $att_id = (int) $row->ID;

            if ( isset( $featured[ $att_id ] ) ) {
                // Used as featured image — link to that post and trigger folder logic.
                $post_id = (int) $featured[ $att_id ]->post_id;
                $parent  = get_post( $post_id );

                if ( $parent && $parent->post_status !== 'trash' ) {
                    // Set post_parent so save_post / attachment hooks work normally.
                    wp_update_post( [ 'ID' => $att_id, 'post_parent' => $post_id ] );

                    // Resolve or create folder for the parent post.
                    $folder_id = (int) get_post_meta( $post_id, '_wpmf_automated_folder_id', true );
                    if ( ! $folder_id ) {
                        $post_type   = $parent->post_type;
                        $folder_name = wpmf_sanitize_folder_name( get_the_title( $post_id ) );
                        $is_post     = ( $post_type === 'post' );
                        $is_cpt      = ( ! $is_post && $post_type !== 'page' && wpmf_auto_cpt_is_enabled( $post_type ) );
                        if ( $is_post ) {
                            $folder_id = wpmf_build_date_hierarchy( $parent->post_date, $folder_name );
                        } elseif ( $is_cpt ) {
                            $folder_id = wpmf_build_cpt_folder( $post_type );
                        }
                        if ( $folder_id ) {
                            update_post_meta( $post_id, '_wpmf_automated_folder_id', $folder_id );
                        }
                    }

                    if ( $folder_id ) {
                        wp_set_object_terms( $att_id, $folder_id, 'wpmf-category', false );
                        wpmf_custom_logger( "🖼️ Allegato #{$att_id} (immagine in evidenza) collegato al post #{$post_id} → cartella #{$folder_id}" );
                        $linked++;
                        continue;
                    }
                }
            }

            // No post match — assign to date-based folder using upload date.
            $folder_id = wpmf_build_date_hierarchy( $row->post_date, '' );
            if ( $folder_id ) {
                wp_set_object_terms( $att_id, $folder_id, 'wpmf-category', false );
                wpmf_custom_logger( "📅 Allegato #{$att_id} assegnato per data di caricamento → cartella #{$folder_id}" );
                $by_date++;
            }
        }

        $offset += $batch_size;
    } while ( count( $rows ) === $batch_size );

    wpmf_custom_logger( "🔗 Orphaned media: {$linked} collegati a post, {$by_date} assegnati per data." );
    return [ 'linked' => $linked, 'by_date' => $by_date ];
}

// ─────────────────────────────────────────────
// 11. SINCRONIZZAZIONE POST & CPT → MEDIA
// ─────────────────────────────────────────────
add_action( 'save_post', function ( int $post_id, WP_Post $post, bool $_update ) {
    if ( wp_is_post_revision( $post_id ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( $post->post_status === 'trash' ) return;
    $post_type = $post->post_type;
    $is_post   = ( $post_type === 'post' );
    $is_cpt    = ( ! $is_post && $post_type !== 'page' && wpmf_auto_cpt_is_enabled( $post_type ) );
    if ( ! $is_post && ! $is_cpt ) return;
    $folder_name     = wpmf_sanitize_folder_name( get_the_title( $post_id ) );
    $saved_folder_id = (int) get_post_meta( $post_id, '_wpmf_automated_folder_id', true );
    if ( ! $saved_folder_id ) {
        $new_id = $is_post ? wpmf_build_date_hierarchy( $post->post_date, $folder_name ) : wpmf_build_cpt_folder( $post_type );
        if ( $new_id ) {
            update_post_meta( $post_id, '_wpmf_automated_folder_id', $new_id );
            wpmf_custom_logger( "🔗 {$post_type} #{$post_id} collegato a cartella #{$new_id} ({$folder_name})" );
            $saved_folder_id = $new_id;
        }
    } else {
        if ( $is_post ) {
            $folder = get_term( $saved_folder_id, 'wpmf-category' );
            if ( $folder && ! is_wp_error( $folder ) && $folder->name !== $folder_name ) {
                wp_update_term( $saved_folder_id, 'wpmf-category', [ 'name' => $folder_name ] );
                wpmf_custom_logger( "✏️ Rinominata cartella Post #{$post_id} → \"{$folder_name}\"" );
            }
        }
    }
    if ( $saved_folder_id ) {
        $attachments = get_children( [ 'post_parent' => $post_id, 'post_type' => 'attachment', 'fields' => 'ids' ] );
        foreach ( $attachments as $att_id ) {
            wp_set_object_terms( $att_id, $saved_folder_id, 'wpmf-category', false );
        }
    }
}, 20, 3 );

// ─────────────────────────────────────────────
// 12. ATTACHMENT HOOK (S3 / upload-first flows)
// ─────────────────────────────────────────────
// Fires when an attachment is added or its post_parent changes (e.g. block
// editor sets post_parent after upload, S3 offload re-saves the attachment).
function wpmf_sync_attachment_to_parent_folder( int $att_id ): void {
    $attachment = get_post( $att_id );
    if ( ! $attachment || $attachment->post_type !== 'attachment' ) return;
    $parent_id = (int) $attachment->post_parent;
    if ( ! $parent_id ) return;
    $parent = get_post( $parent_id );
    if ( ! $parent ) return;
    $post_type = $parent->post_type;
    $is_post   = ( $post_type === 'post' );
    $is_cpt    = ( ! $is_post && $post_type !== 'page' && wpmf_auto_cpt_is_enabled( $post_type ) );
    if ( ! $is_post && ! $is_cpt ) return;
    $folder_id = (int) get_post_meta( $parent_id, '_wpmf_automated_folder_id', true );
    if ( ! $folder_id ) {
        // Parent folder not yet created — trigger save_post logic indirectly.
        $folder_name = wpmf_sanitize_folder_name( get_the_title( $parent_id ) );
        $folder_id   = $is_post
            ? wpmf_build_date_hierarchy( $parent->post_date, $folder_name )
            : wpmf_build_cpt_folder( $post_type );
        if ( $folder_id ) {
            update_post_meta( $parent_id, '_wpmf_automated_folder_id', $folder_id );
            wpmf_custom_logger( "🔗 {$post_type} #{$parent_id} collegato a cartella #{$folder_id} (via allegato #{$att_id})" );
        }
    }
    if ( $folder_id ) {
        $existing = wp_get_object_terms( $att_id, 'wpmf-category', [ 'fields' => 'ids' ] );
        if ( ! is_wp_error( $existing ) && in_array( $folder_id, $existing, true ) ) return;
        wp_set_object_terms( $att_id, $folder_id, 'wpmf-category', false );
        wpmf_custom_logger( "📎 Allegato #{$att_id} assegnato a cartella #{$folder_id} (parent: {$post_type} #{$parent_id})" );
    }
}
add_action( 'add_attachment',      'wpmf_sync_attachment_to_parent_folder' );
add_action( 'attachment_updated',  function ( int $att_id ) {
    wpmf_sync_attachment_to_parent_folder( $att_id );
}, 10, 1 );

// ─────────────────────────────────────────────
// 13. CESTINO E RIPRISTINO
// ─────────────────────────────────────────────
add_action( 'wp_trash_post', function ( int $post_id ) {
    $post = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'post' ) return;
    $folder_id = (int) get_post_meta( $post_id, '_wpmf_automated_folder_id', true );
    if ( ! $folder_id ) return;
    $trash_root = wpmf_get_or_create_folder( __( 'DeletedPosts', WPMF_TD ), 0, '#95a5a6' );
    wp_update_term( $folder_id, 'wpmf-category', [ 'parent' => $trash_root ] );
    wpmf_custom_logger( "🗑️ Spostato in DeletedPosts (Post #{$post_id})" );
} );

add_action( 'untrash_post', function ( int $post_id ) {
    $post = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'post' ) return;
    $folder_id = (int) get_post_meta( $post_id, '_wpmf_automated_folder_id', true );
    if ( ! $folder_id ) return;
    $year      = date( 'Y', strtotime( $post->post_date ) );
    $month_num = date( 'm', strtotime( $post->post_date ) );
    $month_map = wpmf_month_map();
    $month     = $month_map[ $month_num ];
    $year_id   = wpmf_get_or_create_folder( $year, 0, '#5d6d7e' );
    $month_id  = wpmf_get_or_create_folder( $month['name'], $year_id, $month['color'] );
    $s         = wpmf_auto_get_settings();
    if ( ! empty( $s['post_name_folder'] ) ) {
        $folder_name = wpmf_sanitize_folder_name( get_the_title( $post_id ) );
        wp_update_term( $folder_id, 'wpmf-category', [ 'parent' => $month_id, 'name' => $folder_name ] );
    } else {
        wp_update_term( $folder_id, 'wpmf-category', [ 'parent' => $month_id ] );
    }
    wpmf_custom_logger( "♻️ Ripristinato in {$year}/{$month['name']} (Post #{$post_id})" );
} );
