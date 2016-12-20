<?php
/**
 * Plugin Name: BuddyPress Cover Photo
 * Version: 1.1
 * Author: SeventhQueen
 * Author URI: http://seventhqueen.com
 * Plugin URI: http://seventhqueen.com
 * Inspired by Brajesh Singh - https://github.com/sbrajesh/bp-custom-background-for-user-profile
 * License: GPL 
 * 
 * Description: Allows Users to upload Cover photo to their profiles and to Groups
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
