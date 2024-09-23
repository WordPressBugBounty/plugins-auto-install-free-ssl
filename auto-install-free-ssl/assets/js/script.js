var $ = jQuery;



function aifs_confirm(txt){
    if(confirm(txt)) {
        aifs_spanner();
        return true;
    }
    else{
        return false;
    }
}

function aifs_confirm_initiate(txt){
    var email = $("#admin_email").val();

    if(typeof email !== 'undefined' && !email){
        alert(  aifs_js_variable.admin_email );
        return false;
    }
 
    if(!$('input#agree_to_le_terms').is(':checked')){
        alert( aifs_js_variable.le_terms );
        return false;
    }

    else if(!$('input#agree_to_freessl_tech_tos_pp').is(':checked')){
        alert( aifs_js_variable.freessl_tech_tos_pp );
        return false;
    }

    else {
        aifs_spanner();
        return true;
    }

    /*
    else if(confirm(txt)) {
        aifs_spanner();
        return true;
    }
    else{
        return false;
    } */

}

function aifs_spanner(){
    $("div.spanner").addClass("show");
    $("div.overlay").addClass("show");
}

/* toggle-switch for AJAX settings */
jQuery(document).ready(function($) {
    // Check if the 'enable_ssl_renewal_reminder' checkbox is checked
    var isChecked = $('input[data-option="enable_ssl_renewal_reminder"]').is(':checked');

    if(!isChecked){
        $('#aifs-renewal-email').hide();
    }

    $('.aifs-toggle-switch-container input').change(function() {
        var checkbox = $(this);
        var option_name = checkbox.data('option');
        var option_value = checkbox.is(':checked') ? 1 : 0;
        var nonce = $('#settings-nonce').val();

        // Define confirmation message for different checkboxes
        var confirmationMessage = '';
        if (option_name === 'enable_ssl_renewal_reminder' && !checkbox.is(':checked')) {
            confirmationMessage = 'Turning off this option may compromise your website\'s security if you forget to renew the SSL certificate before expiration.\n' +
                'This plugin will automatically send you reminders by email and dashboard notification before your SSL certificate expires.\n' +
                'Are you sure you would like to stop the SSL renewal reminders?';
        } else if (option_name === 'delete_plugin_data_on_deactivation' && checkbox.is(':checked')) {
            confirmationMessage = 'Are you sure you would like to delete the plugin data upon deletion of the plugin?';
        }

        // Display confirmation dialog if confirmation message is defined
        if (confirmationMessage !== '') {
            if (confirm(confirmationMessage)) {
                sendAjaxRequest(option_name, option_value, nonce, checkbox);
            } else {
                // If user cancels, revert checkbox state
                checkbox.prop('checked', !checkbox.is(':checked'));
            }
        } else {
            sendAjaxRequest(option_name, option_value, nonce, checkbox);
        }
    });

    function sendAjaxRequest(option_name, option_value, nonce, checkbox) {
        $.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                action: 'aifs_update_option',
                option_name: option_name,
                option_value: option_value,
                nonce: nonce // Include nonce value in the request
            },
            success: function(response) {
                //alert(response); // Display PHP echo messages using alert()
                if(response == 'update_success'){
                    alert('Settings successfully updated!');

                    if(option_name === 'enable_ssl_renewal_reminder'){
                        var email = $('#aifs-renewal-email');
                        if(checkbox.is(':checked')){
                            email.show();
                        }
                        else{
                            email.hide();
                        }
                    }
                }
                else{
                    checkbox.prop('checked', !checkbox.is(':checked'));

                    if(response == 'update_failed'){
                        alert('The settings update failed! Please refresh the page and try again.');
                    }
                    else if(response == 'unauthorized'){
                        alert('Unauthorized access');
                    }
                    else if(response == 'no_option'){
                        alert('Option name or value not provided. Please refresh the page and try again.');
                    }
                    else if(response == 'invalid_nonce'){
                        alert('Invalid nonce. Please refresh the page and try again.');
                    }
                }
            },
            error: function(xhr, status, error) {
                checkbox.prop('checked', !checkbox.is(':checked'));
                alert('Oops! There was an error updating setting! Please refresh the page and try again.\n\n' + xhr.responseText + '\n' + status + '\n' + error);

            }
        });
    }
});
