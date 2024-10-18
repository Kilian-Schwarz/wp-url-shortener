<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'short_urls';

// Abrufen aller Kurz-URLs
$short_urls = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
?>
<div class="mm-wrap">
    <h1>URL Shortener Verwaltung</h1>

    <h2>Neuen Kurzlink erstellen</h2>
    <form id="wp-us-create-form" class="wp-admin-url-shortener-form">
        <table class="form-table">
            <tr>
                <th scope="row"><label for="target_url">Ziel-URL</label></th>
                <td><input type="url" name="target_url" id="target_url" class="regular-text" placeholder="https://example.com" required></td>
            </tr>
            <tr>
                <th scope="row"><label for="custom_code">Eigener Code (optional)</label></th>
                <td><input type="text" name="custom_code" id="custom_code" class="regular-text" placeholder="DeinCode"></td>
            </tr>
            <tr>
                <th scope="row"><label for="expiration_date">Ablaufdatum (optional)</label></th>
                <td><input type="datetime-local" name="expiration_date" id="expiration_date"></td>
            </tr>
        </table>
        <p class="submit">
            <button type="submit" class="mm-upload-button">Kurzlink erstellen</button>
        </p>
    </form>
    <div id="wp-us-create-result"></div>

    <h2>Alle Kurzlinks</h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th class="column-shortcode">Kurzcode</th>
                <th class="column-link">Link</th>
                <th class="column-created">Erstellungsdatum</th>
                <th class="column-actions">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($short_urls): ?>
                <?php foreach ($short_urls as $url): ?>
                    <tr id="wp-us-row-<?php echo esc_attr($url->id); ?>">
                        <td class="column-shortcode"><?php echo esc_html($url->short_code); ?></td>
                        <td class="column-link">
                            <div class="mm-link-field">
                                <input type="text" value="<?php echo esc_url(home_url('/') . $url->short_code); ?>" readonly>
                                <button class="copy-link" data-link="<?php echo esc_url(home_url('/') . $url->short_code); ?>">Kopieren</button>
                            </div>
                        </td>
                        <td class="column-created"><?php echo esc_html(date('d.m.Y H:i', strtotime($url->created_at))); ?></td>
                        <td class="column-actions">
                            <a href="<?php echo admin_url('admin.php?page=wp-admin-url-shortener-details&id=' . intval($url->id)); ?>" class="mm-upload-button">Details</a>
                            <button class="mm-remove-button wp-us-delete" data-id="<?php echo esc_attr($url->id); ?>">Löschen</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4">Keine Kurzlinks gefunden.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>