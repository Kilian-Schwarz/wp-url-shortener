<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="notice notice-error"><p>Ungültige Kurzlink-ID.</p></div>';
    return;
}

$id = intval($_GET['id']);
global $wpdb;
$table_name = $wpdb->prefix . 'short_urls';
$click_table = $wpdb->prefix . 'short_url_clicks';

// Abrufen des spezifischen Kurzlinks
$short_url = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));

if (!$short_url) {
    echo '<div class="notice notice-error"><p>Kurzlink nicht gefunden.</p></div>';
    return;
}

// Abrufen der Klick-Details
$clicks = $wpdb->get_results($wpdb->prepare("SELECT * FROM $click_table WHERE short_url_id = %d ORDER BY clicked_at DESC", $id));
?>
<div class="mm-wrap">
    <h1>Kurzlink Details</h1>

    <div class="mm-details-container">
        <div class="mm-form-section">
            <h2>Bearbeiten</h2>
            <form id="wp-us-update-form" class="wp-admin-url-shortener-form">
                <input type="hidden" name="id" id="id" value="<?php echo esc_attr($short_url->id); ?>">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="short_code">Kurzcode</label></th>
                        <td><input type="text" name="short_code" id="short_code" class="regular-text" value="<?php echo esc_attr($short_url->short_code); ?>" placeholder="DeinCode" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="target_url">Ziel-URL</label></th>
                        <td><input type="url" name="target_url" id="target_url" class="regular-text" value="<?php echo esc_attr($short_url->target_url); ?>" placeholder="https://example.com" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="expiration_date">Ablaufdatum (optional)</label></th>
                        <td><input type="datetime-local" name="expiration_date" id="expiration_date" value="<?php echo $short_url->expiration_date ? esc_attr(date('Y-m-d\TH:i', strtotime($short_url->expiration_date))) : ''; ?>"></td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="mm-upload-button">Aktualisieren</button>
                </p>
            </form>
            <div id="wp-us-update-result"></div>
        </div>

        <div class="mm-qr-section">
            <h2>QR-Code</h2>
            <div class="mm-qr-code">
                <?php
                // QR-Code generieren
                $short_url_full = home_url('/') . $short_url->short_code;
                $qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($short_url_full);
                ?>
                <img src="<?php echo esc_url($qr_code_url); ?>" alt="QR Code">
            </div>
        </div>
    </div>

    <h2>Erweiterte Statistiken</h2>
    <?php if ($clicks): ?>
        <div class="mm-stats-container">
            <canvas id="mm-stats-chart-<?php echo esc_attr($short_url->id); ?>" class="mm-stats-chart"></canvas>
        </div>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>IP-Adresse</th>
                    <th>User-Agent</th>
                    <th>Datum/Zeit</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clicks as $click): ?>
                    <tr>
                        <td><?php echo esc_html($click->id); ?></td>
                        <td><?php echo esc_html($click->ip_address); ?></td>
                        <td><?php echo esc_html($click->user_agent); ?></td>
                        <td><?php echo esc_html(date('d.m.Y H:i', strtotime($click->clicked_at))); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Keine Klicks für diesen Kurzlink.</p>
    <?php endif; ?>
</div>

<script>
    jQuery(document).ready(function($){
        // Handler für das Aktualisieren von Kurzlinks
        $('#wp-us-update-form').on('submit', function(e){
            e.preventDefault();
            var id = $('#id').val();
            var short_code = $('#short_code').val();
            var target_url = $('#target_url').val();
            var expiration_date = $('#expiration_date').val();

            // Validierung auf Client-Seite
            if (!target_url) {
                $('#wp-us-update-result').html('<span style="color: red;">Bitte gib eine gültige Ziel-URL ein.</span>');
                return;
            }

            $('#wp-us-update-result').html('<span style="color: #ECEFF4;">Aktualisiere Kurzlink...</span>');

            $.ajax({
                url: wp_us_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'update_shortlink',
                    nonce: wp_us_ajax.nonce,
                    id: id,
                    short_code: short_code,
                    target_url: target_url,
                    expiration_date: expiration_date
                },
                success: function(response){
                    if(response.success){
                        $('#wp-us-update-result').html('<span style="color: #A3E635;">' + response.data + '</span>');
                        // Seite neu laden, um die Änderungen anzuzeigen
                        setTimeout(function(){
                            location.reload();
                        }, 1500);
                    } else {
                        $('#wp-us-update-result').html('<span style="color: #F87171;">' + response.data + '</span>');
                    }
                },
                error: function(){
                    $('#wp-us-update-result').html('<span style="color: #F87171;">Ein Fehler ist aufgetreten.</span>');
                }
            });
        });

        // Diagramm für Klickstatistik
        var ctx = document.getElementById('mm-stats-chart-<?php echo esc_attr($short_url->id); ?>').getContext('2d');
        var data = {
            labels: [<?php
                $labels = array();
                foreach ($clicks as $click) {
                    $labels[] = "'" . esc_js(date('d.m.Y H:i', strtotime($click->clicked_at))) . "'";
                }
                echo implode(',', $labels);
            ?>],
            datasets: [{
                label: 'Klicks',
                data: [<?php
                    $data = array();
                    foreach ($clicks as $click) {
                        $data[] = '1';
                    }
                    echo implode(',', $data);
                ?>],
                backgroundColor: 'rgba(33, 150, 243, 0.6)',
                borderColor: 'rgba(33, 150, 243, 1)',
                borderWidth: 1
            }]
        };
        var chart = new Chart(ctx, {
            type: 'bar',
            data: data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Datum/Zeit'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Klicks'
                        },
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Klick';
                            }
                        }
                    }
                }
            }
        });
    });
</script>