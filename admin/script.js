// admin/script.js
jQuery(document).ready(function($){
    // Handler für das Erstellen von Kurzlinks
    $('#wp-us-create-form').on('submit', function(e){
        e.preventDefault();
        var target_url = $('#target_url').val();
        var custom_code = $('#custom_code').val();
        var expiration_date = $('#expiration_date').val();

        // Validierung auf Client-Seite
        if (!target_url) {
            $('#wp-us-create-result').html('<span style="color: red;">Bitte gib eine gültige Ziel-URL ein.</span>');
            return;
        }

        $('#wp-us-create-result').html('<span style="color: #ECEFF4;">Erstelle Kurzlink...</span>');

        $.ajax({
            url: wp_us_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'create_shortlink',
                nonce: wp_us_ajax.nonce,
                target_url: target_url,
                custom_code: custom_code,
                expiration_date: expiration_date
            },
            success: function(response){
                if(response.success){
                    $('#wp-us-create-result').html('<span style="color: #A3E635;">' + response.data.message + ' <a href="' + response.data.url + '" target="_blank">' + response.data.url + '</a></span>');
                    // Seite neu laden, um den neuen Link anzuzeigen
                    setTimeout(function(){
                        location.reload();
                    }, 1500);
                } else {
                    $('#wp-us-create-result').html('<span style="color: #F87171;">' + response.data + '</span>');
                }
            },
            error: function(){
                $('#wp-us-create-result').html('<span style="color: #F87171;">Ein Fehler ist aufgetreten.</span>');
            }
        });
    });

    // Handler für das Löschen von Kurzlinks
    $('.wp-us-delete').on('click', function(){
        if(!confirm('Bist du sicher, dass du diesen Kurzlink löschen möchtest?')){
            return;
        }

        var button = $(this);
        var id = button.data('id');

        $.ajax({
            url: wp_us_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'delete_shortlink',
                nonce: wp_us_ajax.nonce,
                id: id
            },
            success: function(response){
                if(response.success){
                    $('#wp-us-row-' + id).fadeOut(500, function(){
                        $(this).remove();
                    });
                } else {
                    alert(response.data);
                }
            },
            error: function(){
                alert('Ein Fehler ist aufgetreten.');
            }
        });
    });

    // Handler für das Kopieren von Links
    $('.copy-link').on('click', function(){
        var link = $(this).data('link');
        var tempInput = $("<input>");
        $("body").append(tempInput);
        tempInput.val(link).select();
        document.execCommand("copy");
        tempInput.remove();
        // Benutzerfeedback anzeigen ohne Alert
        $('<div class="notice notice-success inline"><p>Link kopiert!</p></div>')
            .insertAfter($(this))
            .delay(1500)
            .fadeOut(500, function(){
                $(this).remove();
            });
    });

    // Handler für das Aktualisieren von Kurzlinks auf der Detailseite
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
});