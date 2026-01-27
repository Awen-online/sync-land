<?php

//add author to gravity forms
add_filter( 'gform_field_value_author_email', 'populate_post_author_email' );
function populate_post_author_email( $value ) {
    global $post;
 
    $author_email = get_the_author_meta( 'email', $post->post_author );
 
    return $author_email;
}

/**
 * Override the item ID that is edited by the Gravity Form when using a Pods feed.
 *
 * @param int    $edit_id  Edit ID.
 * @param string $pod_name Pod name.
 * @param int    $form_id  GF Form ID.
 * @param array  $feed     GF Form feed array.
 * @param array  $form     GF Form array.
 * @param array  $options  Pods GF options.
 * @param Pods   $pod      Pods object.
 */
function my_custom_pods_gf_edit_id( $edit_id, $pod_name, $form_id, $feed, $form, $options, $pod ) {
	// Only change the edit_id if this is for the form ID 123.
//	if ( 5 !== (int) $form_id ) {
//		return $edit_id;
//	}
	// Check access rights, adjust this as needed.
	if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
		return $edit_id;
	}
	// Check if the edit_id passed into the URL was set.
	if ( ! isset( $_GET['artist_edit_id'] ) ) {
		return $edit_id;
	}
	// Force the edit_id to one from the URL.
	$edit_id = absint( $_GET['artist_edit_id'] );
	// Let's add the filter so we tell Pods to prepopulate the form with this item's data.
	add_filter( 'pods_gf_addon_prepopulate', 'my_custom_pods_gf_prepopulate', 10, 7 );
	return $edit_id;
}
add_filter( 'pods_gf_addon_edit_id', 'my_custom_pods_gf_edit_id', 10, 7 );
/**
 * Override whether to prepopulate the form wit the item being edited by the Gravity Form when using a Pods feed.
 *
 * @param bool   $prepopulate Whether to prepopulate or not.
 * @param string $pod_name    Pod name.
 * @param int    $form_id     GF Form ID.
 * @param array  $feed        GF Form feed array.
 * @param array  $form        GF Form array.
 * @param array  $options     Pods GF options.
 * @param Pods   $pod         Pods object.
 */
function my_custom_pods_gf_prepopulate( $prepopulate, $pod_name, $form_id, $feed, $form, $options, $pod ) {
	// We added this filter when checking if they can edit, so we can trust this filter context.
	// Always prepopulate the form with the item we are editing.
	return true;
}

//TODO: VALIDATE IF AN IMAGE IS ABOVE 1000px
//add_filter( 'gform_validation', 'post_to_third_party' );
//function post_to_third_party( $validation_result ) {
// 
//    $form  = $validation_result['form'];
//    $entry = GFFormsModel::get_current_lead();
// 
//    $post_url = 'https://www.freemusic.land/my-account/album-upload/';
//    $body     = array(
//        'first_name' => rgar( $entry, '1.3' ),
//        'last_name'  => rgar( $entry, '1.6' ),
//        'message'    => rgar( $entry, '3' ),
//    );
//    GFCommon::log_debug( 'gform_validation: body => ' . print_r( $body, true ) );
// 
//    $request  = new WP_Http();
//    $response = $request->post( $post_url, array( 'body' => $body ) );
//    GFCommon::log_debug( 'gform_validation: response => ' . print_r( $response, true ) );
// 
//    if ( 1==1) {
//        // validation failed
//        $validation_result['is_valid'] = false;
// 
//        //finding Field with ID of 1 and marking it as failed validation
//        foreach ( $form['fields'] as &$field ) {
// 
//            //NOTE: replace 1 with the field you would like to validate
//            if ( $field->id == '12' ) {
//                $field->failed_validation  = true;
//                $field->validation_message = 'This field is invalid!';
//                break;
//            }
//        }
//    }
// 
//    //Assign modified $form object back to the validation result
//    $validation_result['form'] = $form;
// 
//    return $validation_result;
//}

add_action('gform_pre_process', 'log_gravity_forms_pre_process', 10, 1);

function log_gravity_forms_pre_process($form) {
    // Logging the entire form data before any processing or validation
    GFCommon::log_debug("Form Data Before Processing for Form ID {$form['id']}: " . print_r($form, true));

    // Log specific field values from $_POST data
    $log_message = "Field Values Before Processing for Form ID {$form['id']}:\n";
    foreach ($form['fields'] as $field) {
        $field_id = $field['id'];
        if (isset($_POST["input_{$field_id}"])) {
            $log_message .= "Field ID {$field_id} ({$field['label']}): " . sanitize_text_field($_POST["input_{$field_id}"]) . "\n";
        }
    }
    GFCommon::log_debug($log_message);

    // The function doesn't need to return anything since it's hooked to an action
}