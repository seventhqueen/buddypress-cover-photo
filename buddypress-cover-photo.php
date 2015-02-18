<?php
/**
 * Plugin Name: BuddyPress Cover Photo
 * Version:1.0.4
 * Author: SeventhQueen
 * Author URI: http://seventhqueen.com
 * Plugin URI: http://seventhqueen.com
 * License: GPL 
 * 
 * Description: Allows Users to upload Cover photo to their profiles
 */

class BPCoverPhoto {

    function __construct() {

        //load textdomain
        add_action('bp_loaded', array($this, 'load_textdomain'), 2);
        //setup nav
        add_action('bp_xprofile_setup_nav', array($this, 'setup_nav'));

        add_action( 'bp_before_member_header', array( $this, 'add_profile_cover' ), 20 );

        //inject custom css class to body
        add_filter( 'body_class', array( $this, 'get_body_class' ), 30 );

        //add css for background change
        add_action( 'wp_head', array( $this, 'inject_css' ));
        add_action('wp_print_scripts', array($this, 'inject_js'));
        add_action( 'wp_ajax_bpcp_delete_cover', array( $this, 'ajax_delete_current_cover' ) );

    }

    //inject custom class for profile pages
    function get_body_class($classes){
        if( bp_is_user() && bpcp_get_image() ) {
            $classes[] = 'is-user-profile';
        }
        return $classes;
    }

    function add_profile_cover () {

        $output = '';

        if ( bp_is_my_profile() || is_super_admin() ) {
            if ( bpcp_get_image() ) {
                $message = __("Change Cover", 'bpcp');
            } else {
                $message = __("Add Cover", 'bpcp');
            }

            $output .= '<div class="profile-cover-action">';
            $output .= '<a href="' . bp_displayed_user_domain() . 'profile/change-cover/" class="button">' . $message . '</a>';
            $output .= '</div>';
        }
        if ( bpcp_get_image() ) {
            $output .= '<div class="profile-cover-inner"></div>';
        }

        echo $output;

    }

    //translation
    function load_textdomain() {
        load_plugin_textdomain('bpcp', false, dirname(plugin_basename(__FILE__)) . "/languages/");
    }

    //add a sub nav to My profile for adding cover
    function setup_nav() {
        global $bp;
        $profile_link = bp_loggedin_user_domain() . $bp->profile->slug . '/';
        bp_core_new_subnav_item(
            array(
                'name' => __('Change Cover', 'bpcp'),
                'slug' => 'change-cover',
                'parent_url' => $profile_link,
                'parent_slug' => $bp->profile->slug,
                'screen_function' => array($this, 'screen_change_cover'),
                'user_has_access' => (bp_is_my_profile() || is_super_admin()),
                'position' => 40
            )
        );

    }

    //screen function
    function screen_change_cover() {
        global $bp;
        //if the form was submitted, update here
        if (!empty($_POST['bpcp_save_submit'])) {
            if (!wp_verify_nonce($_POST['_wpnonce'], 'bp_upload_profile_cover')) {
                die(__('Security check failed', 'bpcp'));
            }

            $current_option = $_POST['cover_pos'];
            $allowed_options = array('center', 'bottom', 'top');

            if( in_array( $current_option, $allowed_options ) ) {
                $user_id = bp_loggedin_user_id();
                if ( is_super_admin() && ! bp_is_my_profile() ) {
                    $user_id = bp_displayed_user_id();
                }

                bp_update_user_meta( $user_id, 'profile_cover_pos', $current_option );
            }

            //handle the upload
            if ($this->handle_upload()) {
                bp_core_add_message(__('Cover photo uploaded successfully!', 'bpcp'));
                @setcookie( 'bp-message', false, time() - 1000, COOKIEPATH );
            }
        }

        //hook the content
        add_action('bp_template_title', array($this, 'page_title'));
        add_action('bp_template_content', array($this, 'page_content'));
        bp_core_load_template(apply_filters('bp_core_template_plugin', 'members/single/plugins'));
    }

    //Change Cover Page title
    function page_title() {
        echo __('Add/Update Your Profile Cover Image', 'bpcp');
    }

    //Upload page content
    function page_content() {
        ?>

        <form name="bpcp_change" id="bpcp_change" method="post" class="standard-form" enctype="multipart/form-data">

            <?php
            $image_url = bpcp_get_image();
            if ( ! empty( $image_url ) ): ?>
                <div id="bg-delete-wrapper">

                    <div class="current-cover">
                        <img src="<?php echo $image_url; ?>" alt="current cover photo"/>
                    </div>
                    <br>
                    <a href='#' id='bpcp-del-image' data-buid="<?php echo bp_displayed_user_id();?>" class='btn btn-default btn-xs'><?php _e('Delete', 'bpcp'); ?></a>
                </div>
            <?php endif; ?>

            <p><?php _e('If you want to change your profile cover, please upload a new image.', 'bpcp'); ?></p>
            <label for="bpcp_upload">
                <input type="file" name="file" id="bpcp_upload" class="settings-input"/>
            </label>

            <h3 style="padding-bottom:0px;margin-top: 20px;">
                <?php _e("Please choose your background repeat option", "bpcp");?>
            </h3>

            <div style="clear:both;">
                <?php

                $selected = bpcp_get_image_position();
                $cover_options = array('center' => 'Center', 'top' => 'Top', 'bottom' => "Bottom");

                foreach( $cover_options as $key => $label ):
                    ?>
                    <label class="radio">
                        <input type="radio" name="cover_pos" id="cover_pos<?php echo $key; ?>" value="<?php echo $key; ?>" <?php echo checked($key,$selected); ?>> <?php echo $label;?>
                    </label>
                <?php
                endforeach;

                ?>
            </div>
            
            <br/>
            <br/>

            <?php wp_nonce_field("bp_upload_profile_cover"); ?>
            <input type="hidden" name="action" id="action" value="bp_upload_profile_cover"/>

            <p class="submit">
                <input type="submit" id="bpcp_save_submit" name="bpcp_save_submit" class="button" value="<?php _e('Save', 'bpcp') ?>"/>
            </p>
        </form>
    <?php
    }

    //handles upload, a modified version of bp_core_avatar_handle_upload(from bp-core/bp-core-avatars.php)
    function handle_upload() {

        //include core files
        require_once(ABSPATH . '/wp-admin/includes/file.php');
        $max_upload_size = $this->get_max_upload_size();
        $max_upload_size = $max_upload_size * 1024;//convert kb to bytes
        $file = $_FILES;

        //I am not changing the domain of erro messages as these are same as bp, so you should have a translation for this
        $uploadErrors = array(
            0 => __('There is no error, the file uploaded with success', 'buddypress'),
            1 => __('Your image was bigger than the maximum allowed file size of: ', 'buddypress') . size_format($max_upload_size),
            2 => __('Your image was bigger than the maximum allowed file size of: ', 'buddypress') . size_format($max_upload_size),
            3 => __('The uploaded file was only partially uploaded', 'buddypress'),
            4 => __('No file was uploaded', 'buddypress'),
            6 => __('Missing a temporary folder', 'buddypress')
        );

        if (isset($file['error']) && $file['error']) {
            bp_core_add_message(sprintf(__('Your upload failed, please try again. Error was: %s', 'buddypress'), $uploadErrors[$file['file']['error']]), 'error');
            return false;
        }

        if (!($file['file']['size'] < $max_upload_size)) {
            bp_core_add_message(sprintf(__('The file you uploaded is too big. Please upload a file under %s', 'buddypress'), size_format($max_upload_size)), 'error');
            return false;
        }

        if ((!empty($file['file']['type']) && !preg_match('/(jpe?g|gif|png)$/i', $file['file']['type'])) || !preg_match('/(jpe?g|gif|png)$/i', $file['file']['name'])) {
            bp_core_add_message(__('Please upload only JPG, GIF or PNG photos.', 'buddypress'), 'error');
            return false;
        }

        $uploaded_file = wp_handle_upload($file['file'], array('action' => 'bp_upload_profile_cover'));

        //if file was not uploaded correctly
        if (!empty($uploaded_file['error'])) {
            bp_core_add_message(sprintf(__('Upload Failed! Error was: %s', 'buddypress'), $uploaded_file['error']), 'error');
            return false;
        }

        $user_id = bp_loggedin_user_id();
        if ( is_super_admin() && ! bp_is_my_profile() ) {
            $user_id = bp_displayed_user_id();
        }

        //assume that the file uploaded successfully
        //delete any previous uploaded image
        self::delete_cover_for_user( $user_id );

        //save in user_meta
        bp_update_user_meta( $user_id, 'profile_cover', $uploaded_file['url'] );
        bp_update_user_meta( $user_id, 'profile_cover_file_path', $uploaded_file['file'] );

        @setcookie( 'bp-message', false, time() - 1000, COOKIEPATH );
        
        do_action('bpcp_cover_uploaded', $uploaded_file['url']);//allow to do some other actions when a new background is uploaded
        return true;

    }

    //get the allowed upload size
    //there is no setting on single wp, on multisite, there is a setting, we will adhere to both
    function get_max_upload_size() {
        $max_file_sizein_kb = get_site_option('fileupload_maxk');//it wil be empty for standard wordpress


        if (empty($max_file_sizein_kb)) {//check for the server limit since we are on single wp

            $max_upload_size = (int)(ini_get('upload_max_filesize'));
            $max_post_size = (int)(ini_get('post_max_size'));
            $memory_limit = (int)(ini_get('memory_limit'));
            $max_file_sizein_mb = min($max_upload_size, $max_post_size, $memory_limit);
            $max_file_sizein_kb = $max_file_sizein_mb * 1024;//convert mb to kb
        }
        return apply_filters('bpcp_max_upload_size', $max_file_sizein_kb);


    }

    //inject css
    function inject_css() {
        $image_url = bpcp_get_image();
        if (empty($image_url) || apply_filters('bpcp_iwilldo_it_myself', false)) {
            return;
        }
        $position = bpcp_get_image_position();

        ?>
        <style type="text/css">
            body.buddypress.is-user-profile div#item-header {
                background-image: url("<?php echo $image_url;?>");
                background-repeat: no-repeat;
                background-size: cover;
                background-position: <?php echo $position;?>;
            }
        </style>
    <?php

    }

    //inject js if I am viewing my own profile
    function inject_js() {
        if ( ( bp_is_my_profile() || is_super_admin() ) && bp_is_profile_component() && bp_is_current_action( 'change-cover' ) ) {
            wp_enqueue_script('bpcp-js', plugin_dir_url(__FILE__) . 'bpcp.js', array('jquery'));
        }
    }

    //ajax delete the existing image

    function ajax_delete_current_cover() {
        //validate nonce
        if (!wp_verify_nonce($_POST['_wpnonce'], "bp_upload_profile_cover")) {
            die('what!');
        }

        $user_id = bp_loggedin_user_id();
        if ( isset( $_POST['buid'] ) && (int)$_POST['buid'] != 0 ) {
            if ( bp_loggedin_user_id() != (int)$_POST['buid'] && is_super_admin() ) {
                $user_id = (int)$_POST['buid'];
            }
        }

        self::delete_cover_for_user( $user_id );

        $message = '<p>' . __('Cover photo deleted successfully!', 'bpcp') . '</p>';//feedback but we don't do anything with it yet, should we do something
        echo $message;
        exit(0);

    }

    //reuse it
    function delete_cover_for_user( $user_id = null ) {

        if ( ! $user_id ) {
            $user_id = bp_loggedin_user_id();
        }

        //delete the associated image and send a message
        $old_file_path = get_user_meta( $user_id, 'profile_cover_file_path', true );
        if ($old_file_path) {
            @unlink( $old_file_path );//remove old files with each new upload
        }
        bp_delete_user_meta( $user_id, 'profile_cover_file_path' );
        bp_delete_user_meta( $user_id, 'profile_cover' );
    }
}


/**
 *
 * @param integer $user_id
 * @return string url of the image associated with current user or false
 */

function bpcp_get_image( $user_id = false ){
    if( ! $user_id ) {
        $user_id = bp_displayed_user_id();
    }
    
     if( empty( $user_id ) ) {
         return false;
     }
     $image_url = bp_get_user_meta( $user_id, 'profile_cover', true );
     return apply_filters( 'bpcp_get_image', $image_url, $user_id );
}
function bpcp_get_image_position( $user_id = false ){
    if( !$user_id ) {
        $user_id = bp_displayed_user_id();
    }
    if( empty( $user_id ) ) {
        return false;
    }

    $current_position = bp_get_user_meta( $user_id, 'profile_cover_pos', true);

    if( ! $current_position ) {
        $current_position = 'center';
    }

    return $current_position;
}


add_action( 'init', 'sq_bp_cover_photo_init' );
function sq_bp_cover_photo_init()
{
    if ( function_exists( 'bp_is_active' ) ) {
        $bp_cover_photo = new BPCoverPhoto();
    }
}
