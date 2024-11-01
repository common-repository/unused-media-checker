<?php
/**
 * Unused Media Checker
 *
 * @package   Unused Media Checker
 * @author    DerWebfuchs.de <plugin_support@derwebfuchs.de>
 * @license   GPL-2.0+
 * @link      https://derwebfuchs.de/wordpress-mediathek-aufraumen-unused-media-checker/
 *
 * @wordpress-plugin
 * Plugin Name:       Unused Media Checker
 * Plugin URI:        https://derwebfuchs.de/wordpress-mediathek-aufraumen-unused-media-checker/
 * Description:       This plugin checks for unused media in your WordPress media library.
 * Version:           1.2.0
 * Author:            DerWebfuchs.de
 * Author URI:        https://derwebfuchs.de/wordpress-mediathek-aufraumen-unused-media-checker/
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 */
 

// Enqueue styles and scripts
add_action('admin_enqueue_scripts', 'umchk_enqueue_assets');

function umchk_enqueue_assets() {
    wp_register_style('umchk_style', plugins_url('style.css', __FILE__));
    wp_enqueue_style('umchk_style');
    
    wp_register_script('umchk_script', plugins_url('script.js', __FILE__), array('jquery'), null, true);
    wp_enqueue_script('umchk_script');
}

// Add menu page
add_action('admin_menu', 'umchk_menu');

function umchk_menu() {
    add_media_page(
        'Unused Media Checker',
        'Unused Media Checker',
        'manage_options',
        'unused-media-checker',
        'umchk_page_content'
    );
}

// Main content function
function umchk_page_content() {
    require_once(ABSPATH . 'wp-load.php');

    // Nonce verification for delete_selected
    if (isset($_POST['delete_selected']) && isset($_POST['media_ids']) && is_array($_POST['media_ids'])) {
        $nonce = isset($_POST['delete_unused_media_nonce']) ? sanitize_text_field(wp_unslash($_POST['delete_unused_media_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'delete_unused_media')) {
            echo '<p>Ungültige Anfrage. Bitte versuchen Sie es erneut.</p>';
            return;
        }
        $media_ids = array_map('intval', $_POST['media_ids']);
        umchk_delete_selected_media($media_ids);
    }

    // Nonce verification for pagination
    $paged = isset($_GET['paged']) ? sanitize_text_field(wp_unslash($_GET['paged'])) : 1;
    if (isset($_GET['_wpnonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));
        if (!wp_verify_nonce($nonce, 'pagination_nonce')) {
            echo '<p>Ungültige Anfrage. Bitte versuchen Sie es erneut.</p>';
            return;
        }
    }

    $unused_media = umchk_get_unused_media();

    if (!empty($unused_media)) {
        umchk_render_unused_media($unused_media, $paged);
    } else {
        echo '<p>Es wurden keine ungenutzten Medien gefunden.</p>';
    }
}

// Query for unused media
function umchk_get_unused_media() {
    $attached_media_ids = umchk_get_attached_media_ids();
    $attached_thumbnail_ids = umchk_get_attached_thumbnail_ids();

    $media_args = array(
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => -1,
    );

    $media_query = new WP_Query($media_args);

    $unused_media = array();

    if ($media_query->have_posts()) {
        while ($media_query->have_posts()) {
            $media_query->the_post();
            $media_id = get_the_ID();
            if (!isset($attached_media_ids[$media_id]) && !isset($attached_thumbnail_ids[$media_id])) {
                $unused_media[] = $media_id;
            }
        }
    }

    wp_reset_postdata();

    return $unused_media;
}

// Render pagination links
function umchk_render_pagination_links($current_page) {
    $items_per_page = 20;
    $total_items = count(umchk_get_unused_media());
    $total_pages = ceil($total_items / $items_per_page);

    // Nonce-Field für Seitennummerierung
    $pagination_nonce = wp_create_nonce('pagination_nonce');

    $pagination_links = paginate_links(array(
        'base'    => add_query_arg(array('paged' => '%#%', '_wpnonce' => $pagination_nonce)),
        'format'  => '',
        'current' => $current_page,
        'total'   => $total_pages,
    ));

    if ($pagination_links) {
        echo '<div class="pagination">';
        echo wp_kses_post($pagination_links);
        echo '</div>';
    }
}

// Render media item
function umchk_render_media_item($media_data) {
    ?>
    <div class="unused-media-item">
        <input type="checkbox" name="media_ids[]" value="<?php echo esc_attr($media_data['id']); ?>">
        <a href="<?php echo esc_url($media_data['details_url']); ?>">
            <img src="<?php echo esc_url($media_data['url']); ?>" alt="<?php echo esc_attr($media_data['title']); ?>" title="<?php echo esc_attr($media_data['title']); ?>" style="max-width: 100px; max-height: 100px;">
        </a>
        <p><?php echo esc_html($media_data['title']); ?></p>
        <button type="submit" name="delete_selected" value="<?php echo esc_attr($media_data['id']); ?>">Löschen</button>
    </div>
    <?php
}

// Render unused media
function umchk_render_unused_media($unused_media, $current_page) {
    ?>
    <h2>Ungenutzte Medien:</h2>
    <div class="unused-media-container">
        <form method="post" action="<?php echo esc_url(admin_url('upload.php?page=unused-media-checker')); ?>">
            <?php wp_nonce_field('delete_unused_media', 'delete_unused_media_nonce'); ?>
            <input type="hidden" name="redirect_url" value="unused-media-checker">
            <div class="action-buttons">
                <input type="checkbox" id="select-all-checkbox">
                <label for="select-all-checkbox">Alle auswählen</label>
            </div>
            <div class="unused-media-grid">
                <?php umchk_render_pagination_links($current_page); ?>
                <div class="media-row">
                    <?php $itemsPerPage = 20; ?>
                    <?php $start_index = ($current_page - 1) * $itemsPerPage; ?>
                    <?php $end_index = min($start_index + $itemsPerPage, count($unused_media)); ?>
                    <?php $numCols = min(5, ceil(($end_index - $start_index) / 4)); ?>
                    <?php for ($i = $start_index; $i < $end_index; $i++) : ?>
                        <?php $media_data = umchk_get_media_data($unused_media[$i]); ?>
                        <div class="unused-media-item">
                            <input type="checkbox" name="media_ids[]" value="<?php echo esc_attr($media_data['id']); ?>">
                            <a href="<?php echo esc_url($media_data['details_url']); ?>">
                                <img src="<?php echo esc_url($media_data['url']); ?>" alt="<?php echo esc_attr($media_data['title']); ?>" title="<?php echo esc_attr($media_data['title']); ?>" style="max-width: 100px; max-height: 100px;">
                            </a>
                            <p><?php echo esc_html($media_data['title']); ?></p>
                            <button type="submit" name="delete_selected" value="<?php echo esc_attr($media_data['id']); ?>">Löschen</button>
                        </div>
                        <?php if (($i + 1 - $start_index) % $numCols == 0 && $i + 1 != $end_index) : ?>
                            </div><div class="media-row"> <!-- Neue Reihe nach jedem $numCols. Bild -->
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
                <?php umchk_render_pagination_links($current_page); ?>
            </div>
            <?php umchk_render_action_buttons(); ?>
        </form>
    </div>
    <?php
}

// Render action buttons
function umchk_render_action_buttons() {
    ?>
    <div class="action-buttons">
        <button type="submit" name="delete_selected">Löschen</button>
    </div>
    <?php
}

// Delete selected media
function umchk_delete_selected_media($media_ids_to_delete) {
    $nonce = isset($_POST['delete_unused_media_nonce']) ? sanitize_text_field(wp_unslash($_POST['delete_unused_media_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'delete_unused_media')) {
        echo '<p>Ungültige Anfrage. Bitte versuchen Sie es erneut.</p>';
        return;
    }

    foreach ($media_ids_to_delete as $media_id) {
        wp_delete_attachment($media_id, true);
    }
    echo '<p>Ausgewählte Medien wurden erfolgreich gelöscht.</p>';

    // Inline JavaScript to refresh the page after deletion
    echo '<script type="text/javascript">';
    echo 'window.location.href = "' . esc_url(admin_url('upload.php?page=unused-media-checker')) . '";';
    echo '</script>';
}

// Get attached media IDs
function umchk_get_attached_media_ids() {
    $args = array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
    );

    $query = new WP_Query($args);
    $attached_media_ids = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $attachments = get_attached_media('', get_the_ID());
            foreach ($attachments as $attachment) {
                $attached_media_ids[$attachment->ID] = $attachment->ID;
            }
        }
    }

    wp_reset_postdata();

    return $attached_media_ids;
}

// Get attached thumbnail IDs
function umchk_get_attached_thumbnail_ids() {
    $args = array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_key'       => '_thumbnail_id',
    );

    $query = new WP_Query($args);
    $attached_thumbnail_ids = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $thumbnail_id = get_post_thumbnail_id(get_the_ID());
            $attached_thumbnail_ids[$thumbnail_id] = $thumbnail_id;
        }
    }

    wp_reset_postdata();

    return $attached_thumbnail_ids;
}

// Get media data
function umchk_get_media_data($media_id) {
    $media_url = esc_url(wp_get_attachment_url($media_id));
    $media_title = esc_attr(get_the_title($media_id));
    $media_details_url = esc_url(get_edit_post_link($media_id));

    return array(
        'id'            => $media_id,
        'url'           => $media_url,
        'title'         => $media_title,
        'details_url'   => $media_details_url,
    );
}
?>