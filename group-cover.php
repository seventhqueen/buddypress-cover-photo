<?php
/**
 * Class BPCP_Group_Cover
 * Adds group cover functionality
 * @author Seventhqueen
 * @since 1.1
 */


/**
 * The class_exists() check is recommended, to prevent problems during upgrade
 * or when the Groups component is disabled
 */
if ( class_exists( 'BP_Group_Extension' ) ) :

    class BPCP_Group_Cover extends BP_Group_Extension {

        /**
         * Your __construct() method will contain configuration options for
         * your extension, and will pass them to parent::init()
         */
        function __construct() {
            $args = array(
                'slug' => 'group-cover',
                'name' => __( 'Cover Photo', 'bpcp' ),
                'access' => 'noone',
                'show_tab' => 'noone',
                'screens' => array(
                    'create' => array(
                        'position' => '21'
                    )
                )
            );
            parent::init( $args );

            function bpcp_remove_theme_group_cover_link() {
                remove_action('bp_before_group_header', 'kleo_bp_group_cover_html', 20);
            }
            add_action( 'after_setup_theme', 'bpcp_remove_theme_group_cover_link', 99 );
            add_action( 'bp_before_group_header', array( $this, 'add_cover' ), 20 );

            //inject custom css class to body
            add_filter( 'body_class', array( $this, 'get_body_class' ), 30 );

            //add css for background change
            add_action( 'wp_head', array( $this, 'inject_css' ));
            add_action( 'wp_print_scripts', array( $this, 'inject_js' ) );
            add_action( 'wp_ajax_bpcp_delete_group_cover', array( $this, 'ajax_delete_current_cover' ) );
        }

        //inject custom class for profile pages
        function get_body_class( $classes ){

            $default_cover = bp_get_option( 'bpcp-group-default' );
            if( $this->group_id > 0 ) {
                if ( $default_cover ) {
                    $classes[] = 'bp-default-cover';
                }
            }

            if( $this->get_cover() || $default_cover ) {
                $classes[] = 'is-user-profile';
            }
            return $classes;
        }

        function add_cover () {

            $output = '';

            if ( is_user_logged_in() ) {

                $user_ID = get_current_user_id();

                if (groups_is_user_mod($user_ID, $this->group_id) || groups_is_user_admin($user_ID, $this->group_id)) {
                    if ($this->get_cover()) {
                        $message = __("Change Cover", 'bpcp');
                    } else {
                        $message = __("Add Cover", 'bpcp');
                    }

                    $group = groups_get_group(array('group_id' => $this->group_id));
                    $group_permalink = trailingslashit(bp_get_root_domain() . '/' . bp_get_groups_root_slug() . '/' . $group->slug . '/');

                    $output .= '<div class="profile-cover-action">';
                    $output .= '<a href="' . trailingslashit($group_permalink . 'admin') . $this->slug . '" class="button">' . $message . '</a>';
                    $output .= '</div>';
                }
            }

            $default_cover = bp_get_option( 'bpcp-group-default' );

            if ( $this->get_cover() || $default_cover ) {
                $output .= '<div class="profile-cover-inner"></div>';
            }

            echo $output;

        }

        public function get_cover() {

            if ( $this->group_id == 0 ) {
                return false;
            }
            $image_url = groups_get_groupmeta( $this->group_id, 'bpcp_group_cover' );
            $image_url = apply_filters( 'bpcp_get_group_image', $image_url, $this->group_id );

            return $image_url;
        }

        //inject css
        function inject_css() {

            $group_cover_html_tag = apply_filters( 'bpcp_group_tag', 'div#item-header' );

            /* Default cover check */
            $default_cover = bp_get_option( 'bpcp-group-default' );
            if ( $this->group_id > 0 && $default_cover ) {
                ?>
                <style type="text/css">
                    body.buddypress.bp-default-cover <?php echo $group_cover_html_tag; ?>,
                    .bp-default-cover #buddypress <?php echo $group_cover_html_tag; ?>{
                        background-image: url("<?php echo $default_cover; ?>");
                        background-repeat: no-repeat;
                        background-size: cover;
                        background-position: center center;
                    }
                </style>

            <?php
            }

            $image_url = $this->get_cover();
            if ( empty ( $image_url ) ) {
                return;
            }

            $position = $this->get_cover_position();

            ?>
            <style type="text/css">
                body.buddypress.is-user-profile <?php echo $group_cover_html_tag; ?>,
                .is-user-profile #buddypress <?php echo $group_cover_html_tag; ?>{
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
            if ( bp_is_group_admin_screen( $this->slug ) ) {
                wp_enqueue_script('bpcp-js', plugin_dir_url(__FILE__) . 'bpcp.js', array('jquery'));
            }
        }

        //ajax delete the existing image
        function ajax_delete_current_cover() {
            //validate nonce
            if ( ! wp_verify_nonce( $_POST['_wpnonce'], "bp_group_extension_group-cover_edit" )) {
                die('Error');
            }

            $user_ID = bp_loggedin_user_id();
            if ( groups_is_user_mod( $user_ID, $this->group_id ) || groups_is_user_admin( $user_ID, $this->group_id ) ) {
                self::delete_cover( $this->group_id );
                $message = '<p>' . __('Cover photo deleted successfully!', 'bpcp') . '</p>';
                echo $message;
            }

            exit(0);

        }

        function get_cover_position( $group_id = 0 ) {
            if( $group_id == 0 ) {
                $group_id = $this->group_id;
            }
            if( $group_id == 0 ) {
                return false;
            }

            $current_position = groups_get_groupmeta( $group_id, 'bpcp_group_cover_pos' );

            if( ! $current_position ) {
                $current_position = 'center';
            }

            return $current_position;
        }

        /**
         * settings_screen() is the catch-all method for displaying the content
         * of the edit, create, and Dashboard admin panels
         * @param integer|null $group_id
         */
        function settings_screen( $group_id = NULL ) {

            $image_url = groups_get_groupmeta( $group_id, 'bpcp_group_cover' );

            if ( ! empty( $image_url ) ): ?>
                <div id="bg-delete-wrapper">

                    <div class="current-cover">
                        <img style="width: 100%;" src="<?php echo $image_url; ?>" alt="current cover photo"/>
                    </div>
                    <br>
                    <a href='#' id='bpcp-del-image' data-guid="<?php echo $group_id;?>" class='btn btn-default btn-xs'><?php _e('Delete', 'bpcp'); ?></a>
                </div>
            <?php endif; ?>

            <p><?php _e('If you want to change your group cover, please upload a new image.', 'bpcp'); ?></p>

            <input type="file" name="bpcp_group_cover" id="bpcp_group_cover" class="settings-input">

            <h3 style="padding-bottom:0px;margin-top: 20px;">
                <?php _e("Please choose your background repeat option", "bpcp");?>
            </h3>

            <div style="clear:both;">
                <?php

                $selected = groups_get_groupmeta( $group_id, 'bpcp_group_cover_pos' );
                if( ! $selected ) {
                    $selected = 'center';
                }

                $cover_options = array('center' => __('Center', 'bpcp'), 'top' => __('Top', 'bpcp'), 'bottom' => __("Bottom", 'bpcp'));

                foreach( $cover_options as $key => $label ):
                    ?>
                    <label class="radio">
                        <input type="radio" name="bpcp_group_cover_pos" id="cover_pos<?php echo $key; ?>" value="<?php echo $key; ?>" <?php echo checked($key,$selected); ?>> <?php echo $label;?>
                    </label>
                <?php
                endforeach;

                ?>
            </div>
            <?php if ( ! is_admin() ) : ?>
            <input type="hidden" name="action" id="action" value="upload_group_cover"/>
            <?php endif; ?>


        <?php
        }

        /**
         * settings_screen_save() contains the catch-all logic for saving
         * settings from the edit, create, and Dashboard admin panels
         * @param $group_id int
         */
        function settings_screen_save( $group_id = NULL ) {

            /* Handle background position */
            if ( isset( $_POST['bpcp_group_cover_pos'] ) ) {
                $current_option = $_POST['bpcp_group_cover_pos'];
                $allowed_options = array('center', 'bottom', 'top');

                if ( in_array( $current_option, $allowed_options ) ) {
                    groups_update_groupmeta( $group_id, 'bpcp_group_cover_pos', $current_option );
                }
            }

            /* Handle the file upload */
            if ( isset( $_FILES['bpcp_group_cover']) && $_FILES['bpcp_group_cover']['name'] != '' ) {
                if ( $this->handle_upload( 'bpcp_group_cover', 'upload_group_cover' ) ) {
                    bp_core_add_message(__('Cover photo uploaded successfully!', 'bpcp'));
                    @setcookie('bp-message', false, time() - 1000, COOKIEPATH);
                }
            }

        }

        //handles upload, a modified version of bp_core_avatar_handle_upload(from bp-core/bp-core-avatars.php)
        function handle_upload( $name = '', $action = ''  ) {

            $uploaded_file = BPCP_Utils::handle_upload( $name, $action );

            //if file was not uploaded correctly
            if (!empty($uploaded_file['error'])) {
                bp_core_add_message(sprintf(__('Upload Failed! Error was: %s', 'buddypress'), $uploaded_file['error']), 'error');
                return false;
            }


            //assume that the file uploaded successfully
            //delete any previous uploaded image
            $this->delete_cover( $this->group_id );

            //save in user_meta
            groups_update_groupmeta( $this->group_id, 'bpcp_group_cover', $uploaded_file['url'] );
            groups_update_groupmeta( $this->group_id, 'bpcp_group_cover_file_path', $uploaded_file['file'] );

            @setcookie( 'bp-message', false, time() - 1000, COOKIEPATH );

            do_action('bpcp_group_cover_uploaded', $uploaded_file['url']);//allow to do some other actions when a new background is uploaded
            return true;

        }

        /**
         * Delete Group Cover
         * @param null $group_id
         * @return bool|void
         */
         static function delete_cover( $group_id = null ) {

            if ( ! $group_id ) {
                return false;
            }

            //delete the associated image and send a message
            $old_file_path = groups_get_groupmeta( $group_id, 'bpcp_group_cover_file_path' );
            if ( $old_file_path ) {
                @unlink( $old_file_path ); //remove old files with each new upload
            }
            groups_delete_groupmeta( $group_id, 'bpcp_group_cover_file_path' );
            groups_delete_groupmeta( $group_id, 'bpcp_group_cover' );
        }
    }
    if ( bp_get_option('bpcp-enabled') || version_compare( BP_VERSION, '2.4', '<' ) ) {
        bp_register_group_extension('BPCP_Group_Cover');
    }

endif; // if ( class_exists( 'BP_Group_Extension' ) )

