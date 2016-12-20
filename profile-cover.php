<?php
/**
 * Class BPCoverPhoto
 * Adds profile cover functionality
 * @author Seventhqueen
 * @version 1.0
 */


class BPCoverPhoto {

    function __construct() {

        //setup nav
        add_action( 'bp_xprofile_setup_nav', array( $this, 'setup_nav' ) );

        function bpcp_remove_theme_profile_cover_link() {
            remove_action('bp_before_member_header', 'kleo_bp_cover_html', 20);
        }
        add_action( 'after_setup_theme', 'bpcp_remove_theme_profile_cover_link', 99 );
        add_action( 'bp_before_member_header', array( $this, 'add_profile_cover' ), 20 );

        //inject custom css class to body
        add_filter( 'body_class', array( $this, 'get_body_class' ), 30 );

        //add css for background change
        add_action( 'wp_head', array( $this, 'inject_css' ));
        add_action( 'wp_print_scripts', array( $this, 'inject_js' ) );
        add_action( 'wp_ajax_bpcp_delete_cover', array( $this, 'ajax_delete_current_cover' ) );

    }

    //inject custom class for profile pages
    function get_body_class($classes){
        if( bp_is_user() ) {

            $default_cover = bp_get_option( 'bpcp-profile-default' );
            if ( $default_cover ) {
                $classes[] = 'bp-default-cover';
            }

            if ( bpcp_get_image() || $default_cover ) {
                $classes[] = 'is-user-profile';
            }
        }
        return $classes;
    }

    function add_profile_cover () {

        global $bp;
        $output = '';

        if ( bp_is_my_profile() || is_super_admin() ) {
            if ( bpcp_get_image() ) {
                $message = __("Change Cover", 'bpcp');
            } else {
                $message = __("Add Cover", 'bpcp');
            }

            $output .= '<div class="profile-cover-action">';
            $output .= '<a href="' . bp_displayed_user_domain() . $bp->profile->slug . '/change-cover/" class="button">' . $message . '</a>';
            $output .= '</div>';
        }

        $default_cover = bp_get_option( 'bpcp-profile-default' );

        if ( bpcp_get_image() || $default_cover ) {
            $output .= '<div class="profile-cover-inner"></div>';
        }

        echo $output;

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
        if ( ! empty($_POST['bpcp_save_submit']) ) {
            if ( ! wp_verify_nonce($_POST['_wpnonce'], 'bp_upload_profile_cover') ) {
                die( __('Security check failed', 'bpcp') );
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
            if (isset($_FILES['file']) && $_FILES['file']['name'] != '' && $this->handle_upload()) {
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
                $cover_options = array('center' => __('Center', 'bpcp'), 'top' => __('Top', 'bpcp'), 'bottom' => __("Bottom", 'bpcp'));

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
    function handle_upload( ) {

        $uploaded_file = BPCP_Utils::handle_upload();

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

    //inject css
    function inject_css() {

        if( ! bp_is_user() ) {
            return false;
        }

        $profile_cover_html_tag = apply_filters( 'bpcp_profile_tag', 'div#item-header' );

        /* Default cover check */
        $default_cover = bp_get_option( 'bpcp-profile-default' );
        if ( $default_cover ) {
            ?>
            <style type="text/css">
                body.buddypress.bp-default-cover <?php echo $profile_cover_html_tag; ?>,
                .bp-default-cover #buddypress <?php echo $profile_cover_html_tag; ?> {
                    background-image: url("<?php echo $default_cover; ?>");
                    background-repeat: no-repeat;
                    background-size: cover;
                    background-position: center center;
                }
            </style>

        <?php
        }

        /* User cover */
        $image_url = bpcp_get_image();
        if ( empty( $image_url ) || apply_filters('bpcp_iwilldo_it_myself', false) ) {
            return;
        }
        $position = bpcp_get_image_position();

        ?>
        <style type="text/css">
            body.buddypress.is-user-profile <?php echo $profile_cover_html_tag; ?>,
            .is-user-profile #buddypress <?php echo $profile_cover_html_tag; ?>{
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
    static function delete_cover_for_user( $user_id = null ) {

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