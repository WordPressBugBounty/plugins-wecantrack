jQuery( document ).ready( function( $ ) {
    "use strict";

    var busy = 0,
        current_key = '',
        $form = $('#wecantrack_ajax_form'),
        $api_key = $('#wecantrack_api_key'),
        $loading = $('#wecantrack_loading'),
        $submit_type = $('#wecantrack_submit_type'),
        $submit_verified = $('#submit-verified'),
        $plugin_status = $('.wecantrack-plugin-status input[name="wecantrack_plugin_status"]'),
        $session_enabler = $('.wecantrack-session-enabler');

    function toggle_session_enabler() {
        var checked_val = $('.wecantrack-plugin-status input[name="wecantrack_plugin_status"]:checked').val();

        if (checked_val === '1') {
            $session_enabler.addClass('hidden');
        } else if (checked_val === '0') {
            $session_enabler.removeClass('hidden');
        }
    }

    $plugin_status.click(function () {
        toggle_session_enabler();
    });

    // gets API response through backend and checks how far the user onboarding process is
    function check_prerequisites(response) {

        if (
            typeof response.total_active_network_accounts === 'undefined'
            ||
            typeof response.has_website === 'undefined'
            ||
            typeof response.features === 'undefined'
        ) {
            error_message(params.lang_request_wrong);
            return;
        }

        $('.wecantrack-prerequisites').removeClass('hidden');

        if (response.total_active_network_accounts > 0) {
            $('.wecantrack-prerequisites .wecantrack-preq-network-account i').removeClass('dashicons-no').addClass('dashicons-yes');
            $('.wecantrack-prerequisites .wecantrack-preq-network-account span').html(params.lang_added_one_active_network);
        } else {
            $('.wecantrack-prerequisites .wecantrack-preq-network-account i').removeClass('dashicons-yes').addClass('dashicons-no');
            $('.wecantrack-prerequisites .wecantrack-preq-network-account span').html('<a target="_blank" href="https://app.wecantrack.com/user/data-source/networks">' + params.lang_not_added_one_active_network + '</a>');
        }

        if (response.has_website) {
            $('.wecantrack-prerequisites .wecantrack-preq-feature i').removeClass('dashicons-no').addClass('dashicons-yes');
            $('.wecantrack-prerequisites .wecantrack-preq-feature span').text(params.lang_website_added);
        } else {
            $('.wecantrack-prerequisites .wecantrack-preq-feature i').removeClass('dashicons-yes').addClass('dashicons-no');
            $('.wecantrack-prerequisites .wecantrack-preq-feature span').html('<a target="_blank" href="https://app.wecantrack.com/user/websites/create?website=' + params.site_url + '">'+ params.lang_website_not_added + '</a>');
        }
    }

    // return bool. if user went through the important onboarding process
    function user_passed_prerequisites() {
        return $('.wecantrack-prerequisites .dashicons-yes').length >= 2;
    }

    // resetting the whole form e.g. if the key fails
    function reset_form() {
        $('.wecantrack-prerequisites').addClass('hidden');
        $('.wecantrack-prerequisites .dashicons-yes').removeClass('dashicons-yes');
        $('.wecantrack-prerequisites .dashicons-no').removeClass('dashicons-no');
        $('.wecantrack-snippet, .wecantrack-session-enabler, .wecantrack-plugin-status, #wecantrack_ajax_form .submit').addClass('hidden');
        $('#wecantrack_ajax_form .submit').addClass('hidden');
    }

    $submit_verified.click(function () {
        $submit_type.val(params.lang_verified);
    });

    // submitting the form to validate API Key and or save information
    $form.submit( function(event) {
        clear_messages();

        event.preventDefault(); // Prevent the default form submit.
        if (busy) return;
        busy = 1;
        current_key = '';
        $loading.show();
        // serialize the form data
        var key = $api_key.val(),
            ajax_form_data = $form.serialize();
        //add our own ajax check as X-Requested-With is not always reliable
        ajax_form_data = ajax_form_data+'&ajaxrequest=true&submit=Submit+Form';

        $.ajax({
            url: params.ajaxurl, // domain/wp-admin/admin-ajax.php
            type: 'post',
            data: ajax_form_data
        }).done( function( response ) { // response from the PHP action
            response = JSON.parse(response);
            if (typeof response.error !== 'undefined' && response.error.indexOf('Unauthorised') !== -1) {
                error_message(params.lang_invalid_api_key);
                reset_form();
            } else if (typeof response.error !== 'undefined' ) {
                error_message(response.error);
                reset_form();
            } else {
                success_message(params.lang_valid_api_key + '<br>' + params.lang_changes_saved);
                current_key = key;
                check_prerequisites(response);
            }
        }).fail( function() { // something went wrong
            error_message(params.lang_something_went_wrong);
        }).always( function() { // after all this time?
            // event.target.reset();
            busy = 0;
            $loading.hide();
            if (user_passed_prerequisites()) {
                $('.wecantrack-snippet.hidden, .wecantrack-plugin-status.hidden, #wecantrack_ajax_form .submit').removeClass('hidden');

                if ($('.wecantrack-plugin-status input[name="wecantrack_plugin_status"]:checked').val() === '0') {
                    $('.wecantrack-session-enabler.hidden').removeClass('hidden');
                }
            }
        });
    });

    // auto submits form when api key is already filled in
    if ($api_key.val().length > 30) {
        $form.submit();
    }

    function clear_messages() {
        $("#wecantrack_form_feedback_top").html("");
        $("#wecantrack_form_feedback_bottom").html("");
    }

    // display error message (overwrites)
    function error_message(message, position = 'top') {
        clear_messages();
        if (position === 'top') {
            $("#wecantrack_form_feedback_top").html("<h2 class='wecantrack-text-danger'>" + message + "</h2><br>");
        } else {
            $("#wecantrack_form_feedback_bottom").html("<h2 class='wecantrack-text-danger'>" + message + "</h2><br>");
        }
    }

    // display success message (overwrites)
    function success_message(message, position = 'top') {
        clear_messages();
        if (position === 'top') {
            $("#wecantrack_form_feedback_top").html("<h2 class='wecantrack-text-success'>"+message+"</h2><br>");
        } else {
            $("#wecantrack_form_feedback_bottom").html("<h2 class='wecantrack-text-success'>"+message+"</h2><br>");
        }
    }

    $session_enabler.find('input').keypress(function (e) {
        var key = e.which;
        if(key === 13)  // the enter key code
        {
            $submit_verified.click();
            return false;
        }
    });
});