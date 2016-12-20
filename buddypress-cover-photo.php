<?php
/*
Plugin Name: BuddyPress Cover Photo
Plugin URI: http://seventhqueen.com
Description: Allows Users to upload Cover photo to their Profiles and Groups and define default photos for both sections.
Version: 1.1.4
Author: SeventhQueen
Author URI: http://seventhqueen.com
License: GPL
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /languages
Text Domain: bpcp
*/

/*
Based on initial work of Brajesh Singh custom background plugin
*/

add_action( 'bp_include', 'sq_bp_cover_photo_init' );
function sq_bp_cover_photo_init()
{
    if ( function_exists( 'bp_is_active' ) ) {
        include_once 'profile-cover.php';
        include_once 'group-cover.php';

        $bp_cover_photo = new BPCoverPhoto();
    }
}

//load textdomain
add_action( 'plugins_loaded', 'kleo_bpcp_load_textdomain' );
function kleo_bpcp_load_textdomain() {
    load_plugin_textdomain( 'bpcp', false, dirname(plugin_basename(__FILE__)) . "/languages/" );
}

/**
 * Class BPCP_Utils
 * Some Upload file utils used in the plugin
 */
Class BPCP_Utils {

    public static function get_max_upload_size() {
        $max_file_sizein_kb = get_site_option('fileupload_maxk');//it wil be empty for standard WordPress


        if (empty($max_file_sizein_kb)) {//check for the server limit since we are on single wp

            $max_upload_size = (int)(ini_get('upload_max_filesize'));
            $max_post_size = (int)(ini_get('post_max_size'));
            $memory_limit = (int)(ini_get('memory_limit'));
            $max_file_sizein_mb = min($max_upload_size, $max_post_size, $memory_limit);
            $max_file_sizein_kb = $max_file_sizein_mb * 1024;//convert mb to kb
        }
        return apply_filters('bpcp_max_upload_size', $max_file_sizein_kb);

    }

    //handles upload, a modified version of bp_core_avatar_handle_upload(from bp-core/bp-core-avatars.php)
    public static function handle_upload( $name = 'file', $action = 'bp_upload_profile_cover' )
    {

        //include core files
        require_once(ABSPATH . '/wp-admin/includes/file.php');
        $max_upload_size = self::get_max_upload_size();
        $max_upload_size = $max_upload_size * 1024;//convert kb to bytes
        $file = $_FILES;

        //I am not changing the domain of error messages as these are same as bp, so you should have a translation for this
        $uploadErrors = array(
            0 => __('There is no error, the file uploaded with success', 'buddypress'),
            1 => __('Your image was bigger than the maximum allowed file size of: ', 'buddypress') . size_format($max_upload_size),
            2 => __('Your image was bigger than the maximum allowed file size of: ', 'buddypress') . size_format($max_upload_size),
            3 => __('The uploaded file was only partially uploaded', 'buddypress'),
            4 => __('No file was uploaded', 'buddypress'),
            6 => __('Missing a temporary folder', 'buddypress')
        );

        if (isset($file['error']) && $file['error']) {
            bp_core_add_message(sprintf(__('Your upload failed, please try again. Error was: %s', 'buddypress'), $uploadErrors[$file[$name]['error']]), 'error');
            return false;
        }

        if (!($file[$name]['size'] < $max_upload_size)) {
            bp_core_add_message(sprintf(__('The file you uploaded is too big. Please upload a file under %s', 'buddypress'), size_format($max_upload_size)), 'error');
            return false;
        }

        if ((!empty($file[$name]['type']) && !preg_match('/(jpe?g|gif|png)$/i', $file[$name]['type'])) || !preg_match('/(jpe?g|gif|png)$/i', $file[$name]['name'])) {
            bp_core_add_message(__('Please upload only JPG, GIF or PNG photos.', 'buddypress'), 'error');
            return false;
        }

        return wp_handle_upload( $file[$name], array( 'action' => $action, 'test_form' => FALSE ) );
    }

}


/**
 * Your setting main function
 */
function bp_plugin_admin_settings() {

    /* This is how you add a new section to BuddyPress settings */
    add_settings_section(
    /* the id of your new section */
        'bpcp_section',

        /* the title of your section */
        __( 'Cover Photo Settings',  'bpcp' ),

        /* the display function for your section's description */
        'bpcp_setting_callback_section',

        /* BuddyPress settings */
        'buddypress'
    );

    /* Default Profile cover field */
    add_settings_field(
    /* the option name you want to use for your plugin */
        'bpcp-profile-default',

        /* The title for your setting */
        __( 'Default Profile Cover', 'bpcp' ),

        /* Display function */
        'bpcp_profile_field_callback',

        /* BuddyPress settings */
        'buddypress',

        /* Your plugins section id */
        'bpcp_section'
    );

    /*
       Register Profile default field setting
    */
    register_setting(
    /* BuddyPress settings */
        'buddypress',

        /* the option name you want to use for your plugin */
        'bpcp-profile-default',

        /* the validation function you use before saving your option to the database */
        'strval'
    );

    /* Default Group cover field */
    add_settings_field(
    /* the option name you want to use for your plugin */
        'bpcp-group-default',

        /* The title for your setting */
        __( 'Default Group Cover', 'bpcp' ),

        /* Display function */
        'bpcp_group_field_callback',

        /* BuddyPress settings */
        'buddypress',

        /* Your plugins section id */
        'bpcp_section'
    );

    /*
       Register Group default field setting
    */
    register_setting(
    /* BuddyPress settings */
        'buddypress',

        /* the option name you want to use for your plugin */
        'bpcp-group-default',

        /* the validation function you use before saving your option to the database */
        'strval'
    );

}

/**
 * You need to hook bp_register_admin_settings to register your settings
 */
add_action( 'bp_register_admin_settings', 'bp_plugin_admin_settings' );

/**
 * This is the display function for your section's description
 */
function bpcp_setting_callback_section() {
    ?>
    <p class="description"><?php _e( 'Define a default profile or group cover image', 'bpcp' );?></p>
<?php
}


/**
 * This is the display function for profile default cover
 */
function bpcp_profile_field_callback() {

    /* if you use bp_get_option(), then you are sure to get the option for the blog BuddyPress is activated on */
    $bp_plugin_option_value = bp_get_option( 'bpcp-profile-default' );

    if (! $bp_plugin_option_value ) {
        $bp_plugin_option_value = '';
    }

    // jQuery
    wp_enqueue_script('jquery');
    // This will enqueue the Media Uploader script
    wp_enqueue_media();
    ?>

    <div>
        <input type="text" name="bpcp-profile-default" id="bpcp-profile-default" value="<?php echo $bp_plugin_option_value; ?>" class="regular-text">
        <input type="button" name="upload-btn" id="upload-btn2" class="button-secondary" value="Upload Image">
    </div>

    <script type="text/javascript">
        jQuery(document).ready(function($){
            $('#upload-btn2').click(function(e) {
                e.preventDefault();
                var image = wp.media({
                    title: 'Upload Image',
                    // mutiple: true if you want to upload multiple files at once
                    multiple: false
                }).open()
                    .on('select', function(e){
                        // This will return the selected image from the Media Uploader, the result is an object
                        var uploaded_image = image.state().get('selection').first();
                        // We convert uploaded_image to a JSON object to make accessing it easier
                        // Output to the console uploaded_image
                        //console.log(uploaded_image);
                        var image_url = uploaded_image.toJSON().url;
                        // Let's assign the url value to the input field
                        $('#bpcp-profile-default').val(image_url);
                    });
            });
        });
    </script>


<?php
}


/**
 * This is the display function for your field
 */
function bpcp_group_field_callback() {

    /* if you use bp_get_option(), then you are sure to get the option for the blog BuddyPress is activated on */
    $bp_plugin_option_value = bp_get_option( 'bpcp-group-default' );

    if (! $bp_plugin_option_value ) {
        $bp_plugin_option_value = '';
    }

    // jQuery
    wp_enqueue_script('jquery');
    // This will enqueue the Media Uploader script
    wp_enqueue_media();
    ?>

    <div>
        <input type="text" name="bpcp-group-default" id="bpcp-group-default" value="<?php echo $bp_plugin_option_value; ?>" class="regular-text">
        <input type="button" name="upload-btn" id="upload-btn" class="button-secondary" value="Upload Image">
    </div>

    <script type="text/javascript">
        jQuery(document).ready(function($){
            $('#upload-btn').click(function(e) {
                e.preventDefault();
                var image = wp.media({
                    title: 'Upload Image',
                    // mutiple: true if you want to upload multiple files at once
                    multiple: false
                }).open()
                    .on('select', function(e){
                        // This will return the selected image from the Media Uploader, the result is an object
                        var uploaded_image = image.state().get('selection').first();
                        // We convert uploaded_image to a JSON object to make accessing it easier
                        // Output to the console uploaded_image
                        //console.log(uploaded_image);
                        var image_url = uploaded_image.toJSON().url;
                        // Let's assign the url value to the input field
                        $('#bpcp-group-default').val(image_url);
                    });
            });
        });
    </script>


<?php
}