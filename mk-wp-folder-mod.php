/**
 * PLUGIN NAME: WP Media Folder - Post Folder Sync, Logger & Bulk Cleaner
 * VERSION: 4.3 – Media non assegnati a cartella
 */

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
        'upload.php', 'WPMF Log', 'WPMF Log',
        'manage_options', 'wpmf-automation-log', 'wpmf_render_log_page'
    );
    add_submenu_page(
        'upload.php', 'WPMF Impostazioni', 'WPMF Impostazioni',
        'manage_options', 'wpmf-auto-settings', 'wpmf_render_settings_page'
    );
    add_submenu_page(
        'upload.php', 'WPMF Gestione Cartelle', 'WPMF Gestione Cartelle',
        'manage_options', 'wpmf-folder-manager', 'wpmf_render_folder_manager_page'
    );
} );

// ─────────────────────────────────────────────
// 4. LOG PAGE
// ─────────────────────────────────────────────
function wpmf_render_log_page(): void {
    $log = get_option( 'wpmf_automation_log', [] );
    echo '<div class="wrap"><h1>📜 Log Automazione WP Media Folder</h1>';
    echo '<table class="wp-list-table widefat fixed striped" style="margin-top:20px;">
            <thead><tr><th width="20%">Data/Ora</th><th>Evento</th></tr></thead><tbody>';
    if ( empty( $log ) ) {
        echo '<tr><td colspan="2">Nessun evento.</td></tr>';
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

        $cpt_enabled = [];
        if ( ! empty( $_POST['cpt_enabled'] ) && is_array( $_POST['cpt_enabled'] ) ) {
            foreach ( $_POST['cpt_enabled'] as $cpt ) {
                $cpt_enabled[ sanitize_key( $cpt ) ] = 1;
            }
        }

        update_option( 'wpmf_auto_settings', [
            'folder_name_length' => $length,
            'cpt_enabled'        => $cpt_enabled,
        ] );

        echo '<div class="updated notice is-dismissible"><p>✅ Impostazioni salvate.</p></div>';
    }

    $settings = wpmf_auto_get_settings();
    $cpts     = get_post_types( [ 'public' => true, '_builtin' => false ], 'objects' );
    ?>
    <div class="wrap">
        <h1>⚙️ Impostazioni WPMF Automazione</h1>
        <form method="post">
            <?php wp_nonce_field( 'wpmf_settings_action', 'wpmf_settings_nonce' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="folder_name_length">Lunghezza massima nome cartella</label>
                    </th>
                    <td>
                        <input type="number" id="folder_name_length" name="folder_name_length"
                            value="<?php echo esc_attr( $settings['folder_name_length'] ); ?>"
                            min="5" max="100" style="width:80px;" />
                        <p class="description">Numero massimo di caratteri per i nomi delle cartelle (5–100). Default: 30.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Custom Post Type abilitati</th>
                    <td>
                        <?php if ( empty( $cpts ) ) : ?>
                            <em>Nessun CPT pubblico trovato.</em>
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
                                I CPT selezionati avranno una cartella radice con il nome del tipo.
                                Tutti i media associati verranno inseriti direttamente al suo interno.
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="wpmf_save_settings" value="1" class="button button-primary">
                    💾 Salva impostazioni
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
        $message = "✅ Eliminate <strong>{$deleted}</strong> cartelle vuote (protette con ! mantenute).";
    }

    if ( isset( $_POST['wpmf_do_delete_all'] ) &&
        check_admin_referer( 'wpmf_folder_manager_action', 'wpmf_folder_manager_nonce' ) ) {
        $deleted = wpmf_delete_all_folders_except_protected();
        $message = "✅ Eliminate <strong>{$deleted}</strong> cartelle non protette (protette con ! mantenute).";
    }

    if ( isset( $_POST['wpmf_do_fix_root'] ) &&
        check_admin_referer( 'wpmf_folder_manager_action', 'wpmf_folder_manager_nonce' ) ) {
        $result  = wpmf_fix_root_duplicates();
        $message = "✅ Scansionati <strong>{$result['total_scanned']}</strong> allegati. "
                 . "Corretti <strong>{$result['fixed_duplicates']}</strong> con assegnazioni multiple.";
    }

    if ( isset( $_POST['wpmf_do_assign_unassigned'] ) &&
        check_admin_referer( 'wpmf_folder_manager_action', 'wpmf_folder_manager_nonce' ) ) {
        $moved   = wpmf_assign_unassigned_media();
        $message = "✅ Spostati <strong>{$moved}</strong> media senza cartella in <em>Non assegnati</em>.";
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
    ?>
    <div class="wrap">
        <h1>🗂️ Gestione Cartelle WPMF</h1>
        <p>Le cartelle con <code>!</code> e tutti i loro discendenti sono sempre protetti.</p>

        <div style="display:flex; gap:20px; flex-wrap:wrap; margin:20px 0;">
            <div style="flex:1; min-width:180px; background:#d4edda; border:1px solid #c3e6cb; padding:15px; border-radius:6px;">
                <h3 style="margin-top:0;">🔒 Protette</h3>
                <p style="font-size:2em; margin:0; font-weight:bold;"><?php echo count( $protected_ids ); ?></p>
                <p style="margin:4px 0 0;">cartelle al sicuro</p>
            </div>
            <div style="flex:1; min-width:180px; background:#fff3cd; border:1px solid #ffc107; padding:15px; border-radius:6px;">
                <h3 style="margin-top:0;">🕳️ Vuote</h3>
                <p style="font-size:2em; margin:0; font-weight:bold;"><?php echo count( $empty_ids ); ?></p>
                <p style="margin:4px 0 0;">cartelle senza media</p>
            </div>
            <div style="flex:1; min-width:180px; background:#f8d7da; border:1px solid #f5c6cb; padding:15px; border-radius:6px;">
                <h3 style="margin-top:0;">🗑️ Non protette</h3>
                <p style="font-size:2em; margin:0; font-weight:bold;"><?php echo count( $deletable_ids ); ?></p>
                <p style="margin:4px 0 0;">cartelle eliminabili</p>
            </div>
            <div style="flex:1; min-width:180px; background:#d1ecf1; border:1px solid #bee5eb; padding:15px; border-radius:6px;">
                <h3 style="margin-top:0;">⚠️ Duplicati</h3>
                <p style="font-size:2em; margin:0; font-weight:bold;"><?php echo $duplicate_count; ?></p>
                <p style="margin:4px 0 0;">allegati in più cartelle</p>
            </div>
            <div style="flex:1; min-width:180px; background:#e2d9f3; border:1px solid #c5b3e6; padding:15px; border-radius:6px;">
                <h3 style="margin-top:0;">📭 Non assegnati</h3>
                <p style="font-size:2em; margin:0; font-weight:bold;"><?php echo $unassigned_count; ?></p>
                <p style="margin:4px 0 0;">media senza cartella</p>
            </div>
        </div>

        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <form method="post">
                <?php wp_nonce_field( 'wpmf_folder_manager_action', 'wpmf_folder_manager_nonce' ); ?>
                <button type="submit" name="wpmf_do_assign_unassigned" value="1" class="button button-secondary"
                    <?php echo $unassigned_count === 0 ? 'disabled' : ''; ?>
                    onclick="return confirm('Spostare <?php echo $unassigned_count; ?> media nella cartella &quot;Non assegnati&quot;?');">
                    📭 Raccogli non assegnati (<?php echo $unassigned_count; ?>)
                </button>
            </form>
            <form method="post">
                <?php wp_nonce_field( 'wpmf_folder_manager_action', 'wpmf_folder_manager_nonce' ); ?>
                <button type="submit" name="wpmf_do_fix_root" value="1" class="button button-secondary"
                    <?php echo $duplicate_count === 0 ? 'disabled' : ''; ?>
                    onclick="return confirm('Correggere <?php echo $duplicate_count; ?> allegati con assegnazioni multiple?');">
                    🔧 Correggi duplicati (<?php echo $duplicate_count; ?>)
                </button>
            </form>
            <form method="post">
                <?php wp_nonce_field( 'wpmf_folder_manager_action', 'wpmf_folder_manager_nonce' ); ?>
                <button type="submit" name="wpmf_do_delete_empty" value="1" class="button button-secondary"
                    <?php echo empty( $empty_ids ) ? 'disabled' : ''; ?>
                    onclick="return confirm('Eliminare <?php echo count( $empty_ids ); ?> cartelle vuote?');">
                    🕳️ Elimina cartelle vuote (<?php echo count( $empty_ids ); ?>)
                </button>
            </form>
            <form method="post">
                <?php wp_nonce_field( 'wpmf_folder_manager_action', 'wpmf_folder_manager_nonce' ); ?>
                <button type="submit" name="wpmf_do_delete_all" value="1" class="button button-primary"
                    <?php echo empty( $deletable_ids ) ? 'disabled' : ''; ?>
                    onclick="return confirm('Eliminare TUTTE le <?php echo count( $deletable_ids ); ?> cartelle non protette? Operazione non reversibile.');">
                    🗑️ Elimina cartelle non protette (<?php echo count( $deletable_ids ); ?>)
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
        $actions['wpmf_clean_meta'] = 'Elimina Meta WPMF';
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
            $actions['wpmf_detach_folder'] = 'Scollega cartella WPMF';
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
        printf( '<div class="updated notice is-dismissible"><p>Pulizia completata: rimosso meta WPMF da %d elementi.</p></div>', intval( $_REQUEST['wpmf_meta_cleaned'] ) );
    }
    if ( ! empty( $_REQUEST['wpmf_folder_detached'] ) ) {
        printf( '<div class="updated notice is-dismissible"><p>✅ Cartella WPMF scollegata da %d elementi.</p></div>', intval( $_REQUEST['wpmf_folder_detached'] ) );
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
        '01' => [ 'name' => '01 - Gennaio',   'color' => '#e74c3c' ],
        '02' => [ 'name' => '02 - Febbraio',  'color' => '#e67e22' ],
        '03' => [ 'name' => '03 - Marzo',     'color' => '#f1c40f' ],
        '04' => [ 'name' => '04 - Aprile',    'color' => '#2ecc71' ],
        '05' => [ 'name' => '05 - Maggio',    'color' => '#1abc9c' ],
        '06' => [ 'name' => '06 - Giugno',    'color' => '#3498db' ],
        '07' => [ 'name' => '07 - Luglio',    'color' => '#5dade2' ],
        '08' => [ 'name' => '08 - Agosto',    'color' => '#9b59b6' ],
        '09' => [ 'name' => '09 - Settembre', 'color' => '#e91e63' ],
        '10' => [ 'name' => '10 - Ottobre',   'color' => '#ff5722' ],
        '11' => [ 'name' => '11 - Novembre',  'color' => '#795548' ],
        '12' => [ 'name' => '12 - Dicembre',  'color' => '#00bcd4' ],
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
    $protected = [ $term_id ];
    $children  = get_terms( [ 'taxonomy' => $taxonomy, 'parent' => $term_id, 'hide_empty' => false, 'fields' => 'ids' ] );
    if ( ! empty( $children ) && ! is_wp_error( $children ) ) {
        foreach ( $children as $child_id ) {
            $protected = array_merge( $protected, wpmf_collect_protected_ids( (int) $child_id, $taxonomy ) );
        }
    }
    return $protected;
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

function wpmf_delete_empty_folders(): int {
    $taxonomy = 'wpmf-category';
    if ( ! taxonomy_exists( $taxonomy ) ) return 0;
    $total_deleted = 0;
    do {
        $all_terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'id=>name' ] );
        if ( empty( $all_terms ) || is_wp_error( $all_terms ) ) break;
        $protected_ids = [];
        foreach ( $all_terms as $term_id => $term_name ) {
            if ( str_starts_with( $term_name, '!' ) ) {
                $protected_ids = array_merge( $protected_ids, wpmf_collect_protected_ids( (int) $term_id, $taxonomy ) );
            }
        }
        $protected_ids = array_unique( $protected_ids );
        $deleted_this_pass = 0;
        foreach ( $all_terms as $term_id => $term_name ) {
            if ( in_array( (int) $term_id, $protected_ids, true ) ) continue;
            if ( ! wpmf_folder_is_empty( (int) $term_id ) ) continue;
            $result = wp_delete_term( (int) $term_id, $taxonomy );
            if ( $result && ! is_wp_error( $result ) ) { $deleted_this_pass++; $total_deleted++; }
        }
    } while ( $deleted_this_pass > 0 );
    if ( $total_deleted > 0 ) wpmf_custom_logger( "🕳️ Eliminate {$total_deleted} cartelle vuote." );
    return $total_deleted;
}

function wpmf_delete_all_folders_except_protected(): int {
    $taxonomy = 'wpmf-category';
    if ( ! taxonomy_exists( $taxonomy ) ) return 0;
    $all_terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'id=>name' ] );
    if ( empty( $all_terms ) || is_wp_error( $all_terms ) ) return 0;
    $protected_ids = [];
    foreach ( $all_terms as $term_id => $term_name ) {
        if ( str_starts_with( $term_name, '!' ) ) {
            $protected_ids = array_merge( $protected_ids, wpmf_collect_protected_ids( (int) $term_id, $taxonomy ) );
        }
    }
    $protected_ids = array_unique( $protected_ids );
    $deleted = 0;
    foreach ( $all_terms as $term_id => $term_name ) {
        if ( in_array( (int) $term_id, $protected_ids, true ) ) continue;
        $result = wp_delete_term( (int) $term_id, $taxonomy );
        if ( $result && ! is_wp_error( $result ) ) { $deleted++; }
        elseif ( is_wp_error( $result ) ) wpmf_custom_logger( '⚠️ Errore eliminazione "' . $term_name . '": ' . $result->get_error_message() );
    }
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
    $folder_id = wpmf_get_or_create_folder( 'Non assegnati', 0, '#95a5a6' );
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

// ─────────────────────────────────────────────
// 11. SINCRONIZZAZIONE POST & CPT → MEDIA
// ─────────────────────────────────────────────
add_action( 'save_post', function ( int $post_id, WP_Post $post, bool $update ) {
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
// 12. CESTINO E RIPRISTINO
// ─────────────────────────────────────────────
add_action( 'wp_trash_post', function ( int $post_id ) {
    $post = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'post' ) return;
    $folder_id = (int) get_post_meta( $post_id, '_wpmf_automated_folder_id', true );
    if ( ! $folder_id ) return;
    $trash_root = wpmf_get_or_create_folder( 'DeletedPosts', 0, '#95a5a6' );
    wp_update_term( $folder_id, 'wpmf-category', [ 'parent' => $trash_root ] );
    wpmf_custom_logger( "🗑️ Spostato in DeletedPosts (Post #{$post_id})" );
} );

add_action( 'untrash_post', function ( int $post_id ) {
    $post = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'post' ) return;
    $folder_id = (int) get_post_meta( $post_id, '_wpmf_automated_folder_id', true );
    if ( ! $folder_id ) return;
    $folder_name = wpmf_sanitize_folder_name( get_the_title( $post_id ) );
    $year        = date( 'Y', strtotime( $post->post_date ) );
    $month_num   = date( 'm', strtotime( $post->post_date ) );
    $month_map   = wpmf_month_map();
    $month       = $month_map[ $month_num ];
    $year_id     = wpmf_get_or_create_folder( $year, 0, '#5d6d7e' );
    $month_id    = wpmf_get_or_create_folder( $month['name'], $year_id, $month['color'] );
    wp_update_term( $folder_id, 'wpmf-category', [ 'parent' => $month_id, 'name' => $folder_name ] );
    wpmf_custom_logger( "♻️ Ripristinato in {$year}/{$month['name']} (Post #{$post_id})" );
} );