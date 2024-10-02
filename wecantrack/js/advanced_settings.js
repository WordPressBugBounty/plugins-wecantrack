jQuery( document ).ready( function( $ ) {
    "use strict";

    var $form = $('#wecantrack_ajax_form'),
        $loading = $('#wecantrack_loading');

    var busy = 0;

    // submitting the form to validate API Key and or save information
    $form.submit( function(event) {
        clear_messages();

        event.preventDefault(); // Prevent the default form submit.
        if (busy) return;
        busy = 1;
        $loading.show();
        // serialize the form data
        var ajax_form_data = $form.serialize();
        //add our own ajax check as X-Requested-With is not always reliable
        ajax_form_data = ajax_form_data+'&ajaxrequest=true&submit=Submit+Form';

        $.ajax({
            url: params.ajaxurl, // domain/wp-admin/admin-ajax.php
            type: 'post',
            data: ajax_form_data
        }).done( function( response ) { // response from the PHP action
            response = JSON.parse(response);
            console.log(response);
            if (typeof response.error !== 'undefined') {
                error_message(params.lang_invalid_request + ': ' + response.error);
            } else {
                //success
                success_message(params.lang_changes_saved);
            }
        }).fail( function(r) { // something went wrong
            error_message(params.lang_something_went_wrong);
        }).always( function() { // after all this time?
            // event.target.reset();
            busy = 0;
            $loading.hide();
        });
    });

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
});