<?php
/**
 * Plugin Name: Frontend Post Dashboard
 * Description: Lightweight frontend author dashboard with post submission, draft management, and GitHub update checks.
 * Version: 4.5
 * Author: Claus Dietrich
 * Text Domain: frontend-post-dashboard
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

// ==========================================
// 1. I18N (LANGUAGE SUPPORT)
// ==========================================
add_action('init', function() {
    load_plugin_textdomain('frontend-post-dashboard', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

// ==========================================
// 2. GITHUB UPDATE-CHECKER INTEGRATION
// ==========================================
$puc_path = plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';
if (file_exists($puc_path)) {
    require_once $puc_path;

    try {
        $repo_url = 'https://github.com/Claus-Dietrich/frontend-post-dashboard/';
        $fpdUpdateChecker = null;

        if (class_exists('YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
            $fpdUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
                $repo_url,
                __FILE__,
                'frontend-post-dashboard'
            );
        } elseif (class_exists('Puc_v4_Factory')) {
            $fpdUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
                $repo_url,
                __FILE__,
                'frontend-post-dashboard'
            );
        }

        if ($fpdUpdateChecker && method_exists($fpdUpdateChecker, 'getVcsApi')) {
            $fpdUpdateChecker->getVcsApi()->enableReleaseAssets();
        }
    } catch (\Throwable $e) {
        error_log('FPD Update Checker: ' . $e->getMessage());
    }
}

// ==========================================
// 3. THEME COLORS & EDITOR CONFIGURATION
// ==========================================
function fpd_get_theme_colors() {
    $theme_colors = [];

    if (function_exists('wp_get_global_settings')) {
        $palette = wp_get_global_settings(['color', 'palette', 'theme']);
        if (!empty($palette) && is_array($palette)) {
            foreach ($palette as $f) {
                if (!empty($f['color'])) {
                    $theme_colors[] = [
                        'name'  => !empty($f['name']) ? $f['name'] : __('Theme Color', 'frontend-post-dashboard'),
                        'color' => $f['color']
                    ];
                }
            }
        }
    }

    if (empty($theme_colors)) {
        $support = get_theme_support('editor-color-palette');
        if (!empty($support) && is_array($support[0])) {
            foreach ($support[0] as $f) {
                if (!empty($f['color'])) {
                    $theme_colors[] = [
                        'name'  => !empty($f['name']) ? $f['name'] : __('Theme Color', 'frontend-post-dashboard'),
                        'color' => $f['color']
                    ];
                }
            }
        }
    }

    return $theme_colors;
}

add_filter('mce_buttons', function($buttons) {
    $buttons[] = 'fontsizeselect';
    $buttons[] = 'forecolor';
    $buttons[] = 'backcolor';
    return $buttons;
});

add_filter('tiny_mce_before_init', function($init_array) {
    $standard_palette = [
        '000000', 'Black', '993300', 'Burnt Orange', '333300', 'Dark Olive',
        '003300', 'Dark Green', '003366', 'Dark Azure', '000080', 'Navy Blue',
        '333399', 'Indigo', '333333', 'Very Dark Gray', '800000', 'Maroon',
        'FF6600', 'Orange', '808000', 'Olive', '008000', 'Green',
        '008080', 'Teal', '0000FF', 'Blue', '666699', 'Grayish Blue',
        '808080', 'Gray', 'FF0000', 'Red', 'FF9900', 'Amber',
        '99CC00', 'Yellow Green', '339966', 'Sea Green', '33CCCC', 'Turquoise',
        '3366FF', 'Royal Blue', '800080', 'Purple', '999999', 'Medium Gray',
        'FF00FF', 'Magenta', 'FFCC00', 'Gold', 'FFFF00', 'Yellow',
        '00FF00', 'Lime', '00FFFF', 'Cyan', '00CCFF', 'Sky Blue',
        '993366', 'Plum', 'FFFFFF', 'White'
    ];

    $custom_colors = fpd_get_theme_colors();
    $prefix_palette = [];

    if (!empty($custom_colors)) {
        foreach ($custom_colors as $f) {
            $hex = strtoupper(ltrim($f['color'], '#'));
            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }
            $prefix_palette[] = $hex;
            $prefix_palette[] = $f['name'];
        }
        $final_palette = array_merge($prefix_palette, $standard_palette);
    } else {
        $final_palette = $standard_palette;
    }

    $init_array['textcolor_map'] = json_encode($final_palette);
    $init_array['textcolor_rows'] = 5;
    $init_array['textcolor_cols'] = 8;

    return $init_array;
});

add_action('wp_enqueue_scripts', function() {
    if (is_user_logged_in() && current_user_can('upload_files')) {
        wp_enqueue_media();
    }
});

// Wrap video shortcode
add_filter('wp_video_shortcode', function($output) {
    $output = preg_replace('/style="width:\s*[0-9]+px;?"/i', '', $output);
    return '<div class="fpd-video-responsive-wrap">' . $output . '</div>';
}, 20);

// CSS rules against layout breakouts
add_action('wp_head', function() {
    if (is_singular('post')) {
        echo '<style>
            .entry-content img, article img, .post-content img {
                max-width: 100% !important;
                height: auto !important;
                box-sizing: border-box !important;
            }
            .alignleft { float: left; margin: 0.5em 1.5em 1em 0; max-width: 50%; }
            .alignright { float: right; margin: 0.5em 0 1em 1.5em; max-width: 50%; }
            .aligncenter { display: block; margin-left: auto; margin-right: auto; clear: both; }

            .fpd-video-responsive-wrap,
            .entry-content .wp-video,
            article .wp-video {
                width: 100% !important;
                max-width: var(--theme-block-max-width, var(--theme-normal-container-max-width, 750px)) !important;
                margin-left: auto !important;
                margin-right: auto !important;
                margin-top: 25px !important;
                margin-bottom: 25px !important;
                clear: both !important;
                position: relative !important;
                display: block !important;
                box-sizing: border-box !important;
            }

            .fpd-video-responsive-wrap .mejs-container,
            .entry-content .mejs-container,
            article .mejs-container {
                width: 100% !important;
                max-width: 100% !important;
                position: relative !important;
                clear: both !important;
            }

            .fpd-video-responsive-wrap video,
            .entry-content video,
            article video {
                width: 100% !important;
                max-width: 100% !important;
                height: auto !important;
                display: block !important;
            }

            .entry-content iframe,
            article iframe {
                max-width: var(--theme-block-max-width, var(--theme-normal-container-max-width, 750px)) !important;
                width: 100% !important;
                aspect-ratio: 16 / 9;
                height: auto !important;
                margin: 20px auto !important;
                display: block !important;
                clear: both !important;
            }
        </style>';
    }
});

// ==========================================
// 4. ACTIONS & POST PROCESSING
// ==========================================
add_action('init', function() {
    if (isset($_GET['fpd_lock_page'])) {
        $cookie_name = 'wp-postpass_' . COOKIEHASH;
        setcookie($cookie_name, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
        setcookie($cookie_name, '', time() - 3600, SITECOOKIEPATH, COOKIE_DOMAIN);
        setcookie($cookie_name, '', time() - 3600, '/');
        wp_redirect(remove_query_arg('fpd_lock_page'));
        exit;
    }

    if (isset($_GET['fpd_delete_post']) && is_user_logged_in()) {
        $del_id = intval($_GET['fpd_delete_post']);
        check_admin_referer('fpd_delete_' . $del_id);
        
        $post = get_post($del_id);
        if ($post && ($post->post_author == get_current_user_id() || current_user_can('delete_others_posts'))) {
            wp_trash_post($del_id);
            wp_redirect(add_query_arg(['tab' => 'my_articles', 'fpd_msg' => 'deleted'], remove_query_arg(['fpd_delete_post', '_wpnonce'])));
            exit;
        }
    }

    $is_draft   = isset($_POST['fpd_submit_draft']);
    $is_publish = isset($_POST['fpd_submit_publish']);

    if ($is_draft || $is_publish) {
        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            return;
        }

        if (!isset($_POST['fpd_nonce']) || !wp_verify_nonce($_POST['fpd_nonce'], 'fpd_post_action')) {
            return;
        }

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $edit_id        = isset($_POST['edit_post_id']) ? intval($_POST['edit_post_id']) : 0;
        $titel          = sanitize_text_field($_POST['post_titel']);
        $kat_select     = sanitize_text_field($_POST['post_kategorie']);
        $neue_kat       = sanitize_text_field($_POST['neue_kategorie_name']);
        $text           = wp_kses_post($_POST['post_inhalt']);
        $post_status    = $is_draft ? 'draft' : 'publish';
        $comment_status = isset($_POST['post_kommentare']) ? 'open' : 'closed';

        $kategorie_ids = [];
        if ($kat_select === 'new' && !empty($neue_kat)) {
            $kat_exists = term_exists($neue_kat, 'category');
            if ($kat_exists) {
                $kategorie_ids[] = is_array($kat_exists) ? (int)$kat_exists['term_id'] : (int)$kat_exists;
            } else {
                $created = wp_insert_term($neue_kat, 'category');
                if (!is_wp_error($created)) {
                    $kategorie_ids[] = (int)$created['term_id'];
                }
            }
        } elseif (is_numeric($kat_select) && (int)$kat_select > 0) {
            $kategorie_ids[] = (int)$kat_select;
        }

        $post_data = [
            'post_title'     => $titel,
            'post_content'   => $text,
            'post_status'    => $post_status,
            'post_category'  => $kategorie_ids,
            'comment_status' => $comment_status
        ];

        if ($edit_id > 0) {
            $existing_post = get_post($edit_id);
            if ($existing_post && ($existing_post->post_author == get_current_user_id() || current_user_can('edit_others_posts'))) {
                $post_data['ID'] = $edit_id;
                $target_id = wp_update_post($post_data);
                $msg_code = ($post_status === 'draft') ? 'draft_updated' : 'updated';
            } else {
                return;
            }
        } else {
            $post_data['post_author'] = get_current_user_id();
            $target_id = wp_insert_post($post_data);
            $msg_code = ($post_status === 'draft') ? 'draft_created' : 'created';
        }

        if ($target_id) {
            if (!empty($_POST['beitragsbild_media_id'])) {
                set_post_thumbnail($target_id, (int)$_POST['beitragsbild_media_id']);
            } elseif (!empty($_FILES['post_beitragsbild']['name'])) {
                $thumb_id = media_handle_upload('post_beitragsbild', $target_id);
                if (!is_wp_error($thumb_id)) {
                    set_post_thumbnail($target_id, $thumb_id);
                }
            } elseif (isset($_POST['beitragsbild_entfernt']) && $_POST['beitragsbild_entfernt'] === '1') {
                delete_post_thumbnail($target_id);
            }
        }

        wp_redirect(add_query_arg(['tab' => 'my_articles', 'fpd_msg' => $msg_code], remove_query_arg(['edit_post', 'fpd_msg'])));
        exit;
    }
});

// ==========================================
// 5. FRONTEND DASHBOARD SHORTCODE
// ==========================================
function fpd_render_dashboard() {
    ob_start();
    ?>
    <style>
        .fpd-box { max-width: 820px; margin: 25px auto; padding: 25px 30px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); font-family: inherit; box-sizing: border-box; }
        .fpd-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 14px; margin-bottom: 18px; gap: 15px; flex-wrap: wrap; }
        .fpd-header h2 { margin: 0; font-size: 22px; }
        .fpd-btn-group { display: flex; gap: 8px; align-items: center; }
        .fpd-lock-btn { background: #64748b; color: #fff !important; text-decoration: none; padding: 7px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; }
        .fpd-logout-btn { background: #ef4444; color: #fff !important; text-decoration: none; padding: 7px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; }
        
        .fpd-tabs { display: flex; border-bottom: 2px solid #e2e8f0; margin-bottom: 22px; gap: 5px; }
        .fpd-tab-link { padding: 10px 18px; text-decoration: none !important; font-weight: 600; font-size: 15px; color: #64748b; border-bottom: 2px solid transparent; margin-bottom: -2px; }
        .fpd-tab-link.active { color: #0284c7; border-bottom: 2px solid #0284c7; }
        
        .fpd-group { margin-bottom: 20px; }
        .fpd-group label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px; color: #334155; }
        .fpd-group input[type="text"], .fpd-group select { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 15px; }
        .fpd-media-btn { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 8px 14px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; color: #334155; margin-left: 8px; white-space: nowrap; }
        .fpd-hint { font-size: 12px; color: #64748b; margin-top: 5px; display: block; }
        .fpd-preview { display: flex; align-items: center; gap: 10px; margin-top: 8px; }
        .fpd-preview img { max-height: 70px; border-radius: 4px; border: 1px solid #cbd5e1; }
        .fpd-checkbox-label { display: flex !important; align-items: center; gap: 8px; cursor: pointer; font-weight: 500 !important; }
        .fpd-checkbox-label input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; }
        
        .fpd-submit-row { display: flex; gap: 12px; align-items: center; margin-top: 25px; flex-wrap: wrap; }
        .fpd-btn-draft { background: #475569; color: #fff; border: none; padding: 12px 20px; border-radius: 6px; font-size: 15px; font-weight: 600; cursor: pointer; transition: background 0.1s; }
        .fpd-btn-draft:hover { background: #334155; }
        .fpd-btn-publish { background: #0284c7; color: #fff; border: none; padding: 12px 24px; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; flex-grow: 1; transition: background 0.1s; }
        .fpd-btn-publish:hover { background: #0369a1; }
        .fpd-btn-cancel { background: #f1f5f9; color: #64748b !important; text-decoration: none; padding: 12px 18px; border-radius: 6px; font-size: 15px; font-weight: 600; border: 1px solid #cbd5e1; }
        .fpd-btn-cancel:hover { background: #e2e8f0; }

        .fpd-alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
        .fpd-alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        
        #fpd_new_cat_wrapper { display: none; margin-top: 8px; }
        .wp-editor-wrap { border: 1px solid #cbd5e1; border-radius: 6px; }

        .fpd-palette-wrap { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; }
        .fpd-palette-title { font-size: 12px; font-weight: 600; color: #475569; margin-right: 4px; }
        .fpd-color-swatch { width: 22px; height: 22px; border-radius: 4px; border: 1px solid rgba(0,0,0,0.2); display: inline-block; cursor: pointer; transition: transform 0.1s; }
        .fpd-color-swatch:hover { transform: scale(1.2); }

        .fpd-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .fpd-table th { text-align: left; padding: 10px 12px; background: #f8fafc; border-bottom: 2px solid #e2e8f0; font-size: 13px; color: #475569; }
        .fpd-table th.sortable { cursor: pointer; user-select: none; transition: background 0.1s; }
        .fpd-table th.sortable:hover { background: #f1f5f9; color: #0284c7; }
        .fpd-table th .sort-icon { font-size: 11px; margin-left: 5px; color: #94a3b8; }
        .fpd-table th.sortable:hover .sort-icon { color: #0284c7; }
        .fpd-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; font-size: 14px; vertical-align: middle; }
        .fpd-badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .fpd-badge-live { background: #dcfce7; color: #166534; }
        .fpd-badge-draft { background: #fef3c7; color: #92400e; }
        .fpd-action-link { font-weight: 600; font-size: 13px; text-decoration: none; margin-left: 8px; }
        .fpd-action-edit { color: #0284c7 !important; }
        .fpd-action-del { color: #ef4444 !important; }
    </style>

    <?php
    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        $login_url = wp_login_url(get_permalink());
        ?>
        <div class="fpd-box" style="text-align: center; padding: 40px 20px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Login Required', 'frontend-post-dashboard'); ?></h2>
            <p style="color: #64748b; margin-bottom: 25px;">
                <?php esc_html_e('This area is restricted to registered authors. Please sign in to continue:', 'frontend-post-dashboard'); ?>
            </p>
            <a href="<?php echo esc_url($login_url); ?>" class="fpd-btn-publish" style="display: inline-block; width: auto; text-decoration: none; padding: 12px 32px;">
                <?php esc_html_e('Proceed to Login &rarr;', 'frontend-post-dashboard'); ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }

    $current_user = wp_get_current_user();
    $kategorien   = get_categories(['hide_empty' => false]);
    $theme_colors = fpd_get_theme_colors();
    
    $lock_seite_url = add_query_arg('fpd_lock_page', '1');
    $logout_wp_url  = wp_logout_url(get_permalink());
    $neu_url        = remove_query_arg(['edit_post', 'tab', 'fpd_msg']);

    $aktiver_tab = isset($_GET['tab']) && $_GET['tab'] === 'my_articles' ? 'my_articles' : 'editor';
    $edit_post_id = isset($_GET['edit_post']) ? intval($_GET['edit_post']) : 0;
    
    $edit_post = null;
    if ($edit_post_id > 0) {
        $edit_post = get_post($edit_post_id);
        if ($edit_post && ($edit_post->post_author == $current_user->ID || current_user_can('edit_others_posts'))) {
            $aktiver_tab = 'editor';
        } else {
            $edit_post = null;
        }
    }
    ?>

    <div class="fpd-box">
        <div class="fpd-header">
            <div>
                <h2><?php esc_html_e('Author Dashboard', 'frontend-post-dashboard'); ?></h2>
                <span style="font-size:13px; color:#64748b;">
                    <?php printf(esc_html__('Logged in as %s', 'frontend-post-dashboard'), '<strong>' . esc_html($current_user->display_name) . '</strong>'); ?>
                </span>
            </div>
            <div class="fpd-btn-group">
                <a href="<?php echo esc_url($lock_seite_url); ?>" class="fpd-lock-btn"><?php esc_html_e('Lock Page Access', 'frontend-post-dashboard'); ?></a>
                <a href="<?php echo esc_url($logout_wp_url); ?>" class="fpd-logout-btn"><?php esc_html_e('Log Out', 'frontend-post-dashboard'); ?></a>
            </div>
        </div>

        <?php if (isset($_GET['fpd_msg'])): ?>
            <div class="fpd-alert fpd-alert-success">
                <?php 
                switch ($_GET['fpd_msg']) {
                    case 'created': esc_html_e('✓ Your article was successfully published!', 'frontend-post-dashboard'); break;
                    case 'draft_created': esc_html_e('✓ Your article was saved as a draft!', 'frontend-post-dashboard'); break;
                    case 'updated': esc_html_e('✓ The article was updated and is live!', 'frontend-post-dashboard'); break;
                    case 'draft_updated': esc_html_e('✓ The draft was successfully updated!', 'frontend-post-dashboard'); break;
                    case 'deleted': esc_html_e('✓ The article was moved to the trash.', 'frontend-post-dashboard'); break;
                }
                ?>
            </div>
        <?php endif; ?>

        <div class="fpd-tabs">
            <a href="<?php echo esc_url($neu_url); ?>" 
               class="fpd-tab-link <?php echo ($aktiver_tab === 'editor') ? 'active' : ''; ?>">
                <?php echo $edit_post ? esc_html__('✏️ Edit Article', 'frontend-post-dashboard') : esc_html__('+ New Article', 'frontend-post-dashboard'); ?>
            </a>
            <a href="<?php echo esc_url(add_query_arg('tab', 'my_articles', remove_query_arg(['edit_post', 'fpd_msg']))); ?>" 
               class="fpd-tab-link <?php echo ($aktiver_tab === 'my_articles') ? 'active' : ''; ?>">
                <?php esc_html_e('📋 My Articles', 'frontend-post-dashboard'); ?>
            </a>
        </div>

        <?php if ($aktiver_tab === 'my_articles'): ?>
            <!-- TAB: ARTIKELLISTE -->
            <?php
            $author_posts = get_posts([
                'author'         => $current_user->ID,
                'post_status'    => ['publish', 'draft', 'pending'],
                'posts_per_page' => 100,
                'orderby'        => 'date',
                'order'          => 'DESC'
            ]);
            ?>

            <?php if (empty($author_posts)): ?>
                <p style="text-align: center; color: #64748b; padding: 25px 0;"><?php esc_html_e('You have not published any articles yet.', 'frontend-post-dashboard'); ?></p>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="fpd-table" id="fpd_sortable_table">
                        <thead>
                            <tr>
                                <th style="width: 50px;"><?php esc_html_e('Image', 'frontend-post-dashboard'); ?></th>
                                <th class="sortable" onclick="fpdSortTable(1, 'text')">
                                    <?php esc_html_e('Title', 'frontend-post-dashboard'); ?> <span class="sort-icon" id="sort_icon_1">⇅</span>
                                </th>
                                <th class="sortable" onclick="fpdSortTable(2, 'text')">
                                    <?php esc_html_e('Status', 'frontend-post-dashboard'); ?> <span class="sort-icon" id="sort_icon_2">⇅</span>
                                </th>
                                <th class="sortable" onclick="fpdSortTable(3, 'date')">
                                    <?php esc_html_e('Date', 'frontend-post-dashboard'); ?> <span class="sort-icon" id="sort_icon_3">⇅</span>
                                </th>
                                <th style="text-align: right;"><?php esc_html_e('Actions', 'frontend-post-dashboard'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($author_posts as $p): 
                                $edit_url   = add_query_arg('edit_post', $p->ID, remove_query_arg(['tab', 'fpd_msg']));
                                $del_url    = wp_nonce_url(add_query_arg('fpd_delete_post', $p->ID), 'fpd_delete_' . $p->ID);
                                $view_url   = get_permalink($p->ID);
                                $thumb_url  = get_the_post_thumbnail_url($p->ID, 'thumbnail');
                                $post_ts    = get_post_time('U', false, $p->ID);
                                ?>
                                <tr>
                                    <td>
                                        <?php if ($thumb_url): ?>
                                            <img src="<?php echo esc_url($thumb_url); ?>" style="width:40px; height:40px; object-fit:cover; border-radius:4px; border:1px solid #cbd5e1;">
                                        <?php else: ?>
                                            <div style="width:40px; height:40px; background:#f1f5f9; border-radius:4px; border:1px solid #cbd5e1; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:10px;"><?php esc_html_e('No image', 'frontend-post-dashboard'); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($p->post_title ? $p->post_title : __('(Untitled)', 'frontend-post-dashboard')); ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($p->post_status === 'publish'): ?>
                                            <span class="fpd-badge fpd-badge-live"><?php esc_html_e('Live', 'frontend-post-dashboard'); ?></span>
                                        <?php else: ?>
                                            <span class="fpd-badge fpd-badge-draft"><?php esc_html_e('Draft', 'frontend-post-dashboard'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-timestamp="<?php echo esc_attr($post_ts); ?>" style="color:#64748b; font-size:13px;">
                                        <?php echo get_the_date('Y-m-d', $p->ID); ?>
                                    </td>
                                    <td style="text-align: right; white-space:nowrap;">
                                        <a href="<?php echo esc_url($view_url); ?>" target="_blank" rel="noopener noreferrer" class="fpd-action-link" style="color: #64748b !important;" title="<?php esc_attr_e('View in new tab', 'frontend-post-dashboard'); ?>"><?php esc_html_e('View ↗', 'frontend-post-dashboard'); ?></a>
                                        <a href="<?php echo esc_url($edit_url); ?>" class="fpd-action-link fpd-action-edit"><?php esc_html_e('Edit', 'frontend-post-dashboard'); ?></a>
                                        <a href="<?php echo esc_url($del_url); ?>" class="fpd-action-link fpd-action-del" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this article?', 'frontend-post-dashboard'); ?>');"><?php esc_html_e('Delete', 'frontend-post-dashboard'); ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- TAB: ARTIKEL-EDITOR -->
            <?php
            $val_titel      = $edit_post ? $edit_post->post_title : '';
            $val_inhalt     = $edit_post ? $edit_post->post_content : '';
            $val_comments   = $edit_post ? ($edit_post->comment_status === 'open') : true;
            $val_kats       = $edit_post ? wp_get_post_categories($edit_post->ID) : [];
            $val_kat_id     = !empty($val_kats) ? (int)$val_kats[0] : 0;
            $val_thumb_id   = $edit_post ? get_post_thumbnail_id($edit_post->ID) : '';
            $val_thumb_url  = $val_thumb_id ? wp_get_attachment_url($val_thumb_id) : '';
            ?>

            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('fpd_post_action', 'fpd_nonce'); ?>
                <?php if ($edit_post): ?>
                    <input type="hidden" name="edit_post_id" value="<?php echo esc_attr($edit_post->ID); ?>">
                <?php endif; ?>

                <div class="fpd-group">
                    <label><?php esc_html_e('Article Title *', 'frontend-post-dashboard'); ?></label>
                    <input type="text" name="post_titel" value="<?php echo esc_attr($val_titel); ?>" placeholder="<?php esc_attr_e('Enter headline here...', 'frontend-post-dashboard'); ?>" required>
                </div>

                <div class="fpd-group">
                    <label><?php esc_html_e('Category', 'frontend-post-dashboard'); ?></label>
                    <select name="post_kategorie" id="fpd_cat_select" onchange="fpdCategoryChange(this.value)">
                        <?php foreach ($kategorien as $kat): ?>
                            <option value="<?php echo esc_attr($kat->term_id); ?>" <?php selected($val_kat_id, $kat->term_id); ?>>
                                <?php echo esc_html($kat->name); ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="new"><?php esc_html_e('+ Create new category...', 'frontend-post-dashboard'); ?></option>
                    </select>
                    <div id="fpd_new_cat_wrapper">
                        <input type="text" name="neue_kategorie_name" id="fpd_new_cat_input" placeholder="<?php esc_attr_e('Name of the new category', 'frontend-post-dashboard'); ?>">
                    </div>
                </div>

                <div class="fpd-group">
                    <label><?php esc_html_e('Featured Image (Post Thumbnail)', 'frontend-post-dashboard'); ?></label>
                    <div style="display:flex; align-items:center;">
                        <input type="file" name="post_beitragsbild" id="fpd_input_file_image" accept="image/*">
                        <button type="button" class="fpd-media-btn" onclick="fpdOpenMediaDialog()"><?php esc_html_e('Select from Media Library', 'frontend-post-dashboard'); ?></button>
                    </div>
                    <input type="hidden" name="beitragsbild_media_id" id="fpd_media_id_image" value="<?php echo esc_attr($val_thumb_id); ?>">
                    <input type="hidden" name="beitragsbild_entfernt" id="fpd_media_removed_flag" value="0">
                    
                    <div id="fpd_preview_image" class="fpd-preview" style="<?php echo $val_thumb_url ? 'display:flex;' : 'display:none;'; ?>">
                        <img id="fpd_img_preview" src="<?php echo esc_url($val_thumb_url); ?>" alt="<?php esc_attr_e('Preview', 'frontend-post-dashboard'); ?>">
                        <button type="button" onclick="fpdClearMedia()" style="color:#ef4444; background:none; border:none; cursor:pointer; font-size:12px;"><?php esc_html_e('Remove', 'frontend-post-dashboard'); ?></button>
                    </div>
                    <span class="fpd-hint"><?php esc_html_e('This image controls the card preview in your article archive.', 'frontend-post-dashboard'); ?></span>
                </div>

                <div class="fpd-group">
                    <label><?php esc_html_e('Content, Images & Media', 'frontend-post-dashboard'); ?></label>
                    
                    <?php if (!empty($theme_colors)): ?>
                    <div class="fpd-palette-wrap">
                        <span class="fpd-palette-title"><?php esc_html_e('Website Colors:', 'frontend-post-dashboard'); ?></span>
                        <?php foreach ($theme_colors as $f): ?>
                            <span class="fpd-color-swatch" 
                                  style="background-color: <?php echo esc_attr($f['color']); ?>;" 
                                  title="<?php echo esc_attr($f['name'] . ' (' . $f['color'] . ')'); ?>"
                                  onclick="fpdApplyColorToEditor('<?php echo esc_js($f['color']); ?>')"></span>
                        <?php endforeach; ?>
                        <span style="font-size:11px; color:#64748b; margin-left:4px;"><?php esc_html_e('(Select text + click color)', 'frontend-post-dashboard'); ?></span>
                    </div>
                    <?php endif; ?>

                    <span class="fpd-hint" style="margin-bottom:8px;">
                        <?php esc_html_e('Use "Add Media" to embed and resize images or videos seamlessly.', 'frontend-post-dashboard'); ?>
                    </span>
                    
                    <?php 
                    wp_editor($val_inhalt, 'post_inhalt', [
                        'media_buttons' => true,
                        'textarea_rows' => 16,
                        'teeny'         => false,
                        'quicktags'     => true
                    ]); 
                    ?>
                </div>

                <div class="fpd-group">
                    <label class="fpd-checkbox-label">
                        <input type="checkbox" name="post_kommentare" value="1" <?php checked($val_comments, true); ?>>
                        <span><?php esc_html_e('Allow comments on this article', 'frontend-post-dashboard'); ?></span>
                    </label>
                </div>

                <div class="fpd-submit-row">
                    <button type="submit" name="fpd_submit_draft" class="fpd-btn-draft">
                        💾 <?php esc_html_e('Save as Draft', 'frontend-post-dashboard'); ?>
                    </button>

                    <button type="submit" name="fpd_submit_publish" class="fpd-btn-publish">
                        🚀 <?php echo $edit_post ? esc_html__('Publish Changes (Live)', 'frontend-post-dashboard') : esc_html__('Publish Article (Live)', 'frontend-post-dashboard'); ?>
                    </button>
                    
                    <?php if ($edit_post): ?>
                        <a href="<?php echo esc_url($neu_url); ?>" class="fpd-btn-cancel">
                            <?php esc_html_e('Cancel', 'frontend-post-dashboard'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
    function fpdCategoryChange(val) {
        var wrapper = document.getElementById('fpd_new_cat_wrapper');
        var input = document.getElementById('fpd_new_cat_input');
        if (!wrapper || !input) return;
        if (val === 'new') {
            wrapper.style.display = 'block';
            input.required = true;
            input.focus();
        } else {
            wrapper.style.display = 'none';
            input.required = false;
        }
    }

    function fpdOpenMediaDialog() {
        var mediaFrame = wp.media({
            title: '<?php echo esc_js(__('Select Featured Image', 'frontend-post-dashboard')); ?>',
            button: { text: '<?php echo esc_js(__('Use as Featured Image', 'frontend-post-dashboard')); ?>' },
            multiple: false,
            library: { type: 'image' }
        });

        mediaFrame.on('select', function() {
            var attachment = mediaFrame.state().get('selection').first().toJSON();
            document.getElementById('fpd_media_id_image').value = attachment.id;
            document.getElementById('fpd_img_preview').src = attachment.url;
            document.getElementById('fpd_preview_image').style.display = 'flex';
            document.getElementById('fpd_input_file_image').value = '';
            document.getElementById('fpd_media_removed_flag').value = '0';
        });

        mediaFrame.open();
    }

    function fpdClearMedia() {
        document.getElementById('fpd_media_id_image').value = '';
        document.getElementById('fpd_img_preview').src = '';
        document.getElementById('fpd_preview_image').style.display = 'none';
        document.getElementById('fpd_media_removed_flag').value = '1';
    }

    function fpdApplyColorToEditor(hexColor) {
        if (typeof tinymce !== 'undefined') {
            var editor = tinymce.get('post_inhalt');
            if (editor && !editor.isHidden()) {
                editor.focus();
                editor.execCommand('ForeColor', false, hexColor);
            }
        }
    }

    var currentSortCol = -1;
    var currentSortAsc = true;

    function fpdSortTable(colIndex, type) {
        var table = document.getElementById('fpd_sortable_table');
        if (!table) return;
        var tbody = table.querySelector('tbody');
        var rows = Array.from(tbody.querySelectorAll('tr'));

        if (currentSortCol === colIndex) {
            currentSortAsc = !currentSortAsc;
        } else {
            currentSortCol = colIndex;
            currentSortAsc = true;
        }

        [1, 2, 3].forEach(function(i) {
            var icon = document.getElementById('sort_icon_' + i);
            if (icon) {
                if (i === colIndex) {
                    icon.textContent = currentSortAsc ? '▲' : '▼';
                    icon.style.color = '#0284c7';
                } else {
                    icon.textContent = '⇅';
                    icon.style.color = '#94a3b8';
                }
            }
        });

        rows.sort(function(rowA, rowB) {
            var cellA = rowA.children[colIndex];
            var cellB = rowB.children[colIndex];

            var valA, valB;
            if (type === 'date') {
                valA = parseInt(cellA.getAttribute('data-timestamp'), 10) || 0;
                valB = parseInt(cellB.getAttribute('data-timestamp'), 10) || 0;
                return currentSortAsc ? valA - valB : valB - valA;
            } else {
                valA = cellA.innerText.trim().toLowerCase();
                valB = cellB.innerText.trim().toLowerCase();
                if (valA < valB) return currentSortAsc ? -1 : 1;
                if (valA > valB) return currentSortAsc ? 1 : -1;
                return 0;
            }
        });

        rows.forEach(function(row) {
            tbody.appendChild(row);
        });
    }
    </script>
    <?php
    return ob_get_clean();
}

add_shortcode('frontend_post_dashboard', 'fpd_render_dashboard');
add_shortcode('kunden_artikel_formular', 'fpd_render_dashboard');