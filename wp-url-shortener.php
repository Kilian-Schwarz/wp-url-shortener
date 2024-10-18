<?php
/*
 * Plugin Name: Wordpress URL Shorter
 * Plugin URI: https://github.com/Kilian-Schwarz/wp-url-shortener
 * Description: Shorting URLS
 * Version: 0.0.1
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Tested up to: 6.6
 * Author: Kilian Schwarz
 * Author URI: https://github.com/Kilian-Schwarz
 * License: GPL-3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: maintenance-mode
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Direktzugriff verhindern
}

class WP_Admin_URL_Shortener {
    public function __construct() {
        // Aktivierungs- und Deaktivierungs-Hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Datenbanktabellen erstellen
        add_action('init', array($this, 'create_tables'));

        // Admin-Menü hinzufügen
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Redirect Handling
        add_action('init', array($this, 'handle_redirect'));

        // AJAX für das Erstellen, Löschen und Aktualisieren von Shortlinks
        add_action('wp_ajax_create_shortlink', array($this, 'create_shortlink'));
        add_action('wp_ajax_delete_shortlink', array($this, 'delete_shortlink'));
        add_action('wp_ajax_update_shortlink', array($this, 'update_shortlink'));

        // Enqueue Admin Styles und Skripte
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    // Aktivierung: Tabellen erstellen
    public function activate() {
        $this->create_tables();
    }

    // Deaktivierung (optional)
    public function deactivate() {
        // Optionale Bereinigung
    }

    // Tabellen erstellen
    public function create_tables() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'short_urls';
        $click_table = $wpdb->prefix . 'short_url_clicks';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            short_code VARCHAR(20) NOT NULL UNIQUE,
            target_url TEXT NOT NULL,
            expiration_date DATETIME DEFAULT NULL,
            click_count BIGINT(20) UNSIGNED DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        $sql_clicks = "CREATE TABLE IF NOT EXISTS $click_table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            short_url_id BIGINT(20) UNSIGNED NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            clicked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            FOREIGN KEY (short_url_id) REFERENCES $table_name(id) ON DELETE CASCADE
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($sql_clicks);
    }

    // Admin-Menü hinzufügen
    public function add_admin_menu() {
        add_menu_page(
            'URL Shortener',
            'URL Shortener',
            'manage_options',
            'wp-admin-url-shortener',
            array($this, 'admin_page'),
            'dashicons-admin-links',
            76
        );

        // Detailseite hinzufügen
        add_submenu_page(
            'wp-admin-url-shortener',
            'Kurzlink Details',
            'Kurzlink Details',
            'manage_options',
            'wp-admin-url-shortener-details',
            array($this, 'link_details_page')
        );
    }

    // Enqueue Admin Styles und Skripte
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'toplevel_page_wp-admin-url-shortener' && $hook !== 'wp-admin-url-shortener_page_wp-admin-url-shortener-details') {
            return;
        }
        wp_enqueue_style('wp-admin-url-shortener-styles', plugin_dir_url(__FILE__) . 'admin/styles.css', array(), '1.7');
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.7.1', true);
        wp_enqueue_script('wp-admin-url-shortener-script', plugin_dir_url(__FILE__) . 'admin/script.js', array('jquery', 'chart-js'), '1.7', true);
        wp_localize_script('wp-admin-url-shortener-script', 'wp_us_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('wp_us_nonce')
        ));
    }

    // Admin-Seite
    public function admin_page() {
        include plugin_dir_path(__FILE__) . 'admin/admin-page.php';
    }

    // Detailseite für einen Kurzlink
    public function link_details_page() {
        include plugin_dir_path(__FILE__) . 'admin/link-details.php';
    }

    // Redirect Handling
    public function handle_redirect() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'short_urls';
        $click_table = $wpdb->prefix . 'short_url_clicks';
        $request = trim($_SERVER['REQUEST_URI'], '/');
        $parts = explode('?', $request);
        $short_code = $parts[0];

        if ($short_code) {
            $short_url = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE short_code = %s", $short_code));
            if ($short_url) {
                // Überprüfen ob die URL abgelaufen ist
                if ($short_url->expiration_date && current_time('mysql') > $short_url->expiration_date) {
                    wp_die('Dieser Kurzlink ist abgelaufen.', 'Abgelaufener Link', array('response' => 410));
                }

                // Klickzähler erhöhen
                $wpdb->update(
                    $table_name,
                    array('click_count' => $short_url->click_count + 1),
                    array('id' => $short_url->id),
                    array('%d'),
                    array('%d')
                );

                // Klick-Details speichern
                $ip_address = $this->get_user_ip();
                $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';

                $wpdb->insert(
                    $click_table,
                    array(
                        'short_url_id' => $short_url->id,
                        'ip_address'   => sanitize_text_field($ip_address),
                        'user_agent'   => $user_agent
                    ),
                    array(
                        '%d',
                        '%s',
                        '%s'
                    )
                );

                // Weiterleiten
                wp_redirect(esc_url_raw($short_url->target_url), 301);
                exit;
            }
        }
    }

    // AJAX Handler zum Erstellen von Shortlinks
    public function create_shortlink() {
        check_ajax_referer('wp_us_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung.');
        }

        if (!isset($_POST['target_url']) || !filter_var($_POST['target_url'], FILTER_VALIDATE_URL)) {
            wp_send_json_error('Ungültige URL.');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'short_urls';
        $target_url = esc_url_raw($_POST['target_url']);
        $custom_code = sanitize_text_field($_POST['custom_code']);
        $expiration_date = !empty($_POST['expiration_date']) ? sanitize_text_field($_POST['expiration_date']) : null;

        if ($custom_code) {
            // Prüfen ob der Code bereits existiert
            $existing = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE short_code = %s", $custom_code));
            if ($existing > 0) {
                wp_send_json_error('Der benutzerdefinierte Code ist bereits vergeben.');
            }
            $short_code = $custom_code;
        } else {
            // Zufälligen Code generieren
            do {
                $short_code = wp_generate_password(6, false, false);
                $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE short_code = %s", $short_code));
            } while ($exists > 0);
        }

        $result = $wpdb->insert(
            $table_name,
            array(
                'short_code'     => $short_code,
                'target_url'     => $target_url,
                'expiration_date'=> $expiration_date
            ),
            array(
                '%s',
                '%s',
                '%s'
            )
        );

        if ($result) {
            $short_url = home_url('/') . $short_code;
            wp_send_json_success(array('message' => 'Kurzlink erstellt:', 'url' => $short_url));
        } else {
            wp_send_json_error('Fehler beim Erstellen des Kurzlinks.');
        }
    }

    // AJAX Handler zum Löschen von Shortlinks
    public function delete_shortlink() {
        check_ajax_referer('wp_us_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung.');
        }

        if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
            wp_send_json_error('Ungültige ID.');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'short_urls';
        $id = intval($_POST['id']);

        $deleted = $wpdb->delete($table_name, array('id' => $id), array('%d'));

        if ($deleted) {
            wp_send_json_success('Kurzlink gelöscht.');
        } else {
            wp_send_json_error('Fehler beim Löschen des Kurzlinks.');
        }
    }

    // AJAX Handler zum Aktualisieren von Shortlinks
    public function update_shortlink() {
        check_ajax_referer('wp_us_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung.');
        }

        if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
            wp_send_json_error('Ungültige ID.');
        }

        if (!isset($_POST['target_url']) || !filter_var($_POST['target_url'], FILTER_VALIDATE_URL)) {
            wp_send_json_error('Ungültige URL.');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'short_urls';
        $id = intval($_POST['id']);
        $target_url = esc_url_raw($_POST['target_url']);
        $expiration_date = !empty($_POST['expiration_date']) ? sanitize_text_field($_POST['expiration_date']) : null;

        // Optional: Aktualisieren des Shortcodes (nur wenn gewünscht)
        if (isset($_POST['short_code']) && !empty($_POST['short_code'])) {
            $short_code = sanitize_text_field($_POST['short_code']);
            // Prüfen ob der Code bereits existiert
            $existing = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE short_code = %s AND id != %d", $short_code, $id));
            if ($existing > 0) {
                wp_send_json_error('Der benutzerdefinierte Code ist bereits vergeben.');
            }
            $update_data = array(
                'short_code'     => $short_code,
                'target_url'     => $target_url,
                'expiration_date'=> $expiration_date
            );
            $format = array('%s', '%s', '%s');
        } else {
            $update_data = array(
                'target_url'     => $target_url,
                'expiration_date'=> $expiration_date
            );
            $format = array('%s', '%s');
        }

        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $id),
            $format,
            array('%d')
        );

        if ($result !== false) {
            wp_send_json_success('Kurzlink aktualisiert.');
        } else {
            wp_send_json_error('Fehler beim Aktualisieren des Kurzlinks.');
        }
    }

    // Funktion zur Ermittlung der IP-Adresse des Benutzers
    private function get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return sanitize_text_field($ip);
    }
}

new WP_Admin_URL_Shortener();