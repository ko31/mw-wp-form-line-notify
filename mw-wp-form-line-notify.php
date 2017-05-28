<?php
/**
 * Plugin Name: MW WP Form LINE Notify
 * Plugin URI:  https://github.com/ko31/mw-wp-form-line-notify
 * Description: This plugin send LINE Notify a message sent from MW WP Form
 * Version:     0.9.0
 * Author:      Ko Takagi
 * Author URI:  http://go-sign.info/
 * License:     GPLv2
 * Text Domain: mw-wp-form-line-notify
 * Domain Path: /languages
 */

/*  Copyright (c) 2017 Ko Takagi (http://go-sign.info/)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

$mwWpFormLineNotify = new mw_wp_form_line_notify();
$mwWpFormLineNotify->register();

class mw_wp_form_line_notify {

    private $version = '';
    private $langs = '';
    private $plugin_name = '';

    function __construct()
    {
        $data = get_file_data(
            __FILE__,
            array('ver' => 'Version', 'langs' => 'Domain Path')
        );
        $this->version = $data['ver'];
        $this->langs = $data['langs'];
        $this->plugin_name = 'mw-wp-form-line-notify';
    }

    public function register()
    {
        add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
    }

    public function plugins_loaded()
    {
        load_plugin_textdomain(
            'mw-wp-form-line-notify',
            false,
            dirname( plugin_basename( __FILE__ ) ) . $this->langs
        );

        $this->add_filters();

        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        if ( isset($_REQUEST['page']) && $_REQUEST['page'] == $this->plugin_name ) {
            add_action( 'admin_init', array( $this, 'admin_init' ) );
            add_action( 'admin_notices', array( $this, 'admin_notices' ) );
        }
    }

    public function add_filters()
    {
        $options = get_option( $this->plugin_name );
        $form_key = $options['form_key'];
        $keys = explode( ",", $form_key );
        foreach( $keys as $key ) {
            add_filter( 'mwform_admin_mail_mw-wp-form-' . $key,  array( $this, 'mwform_admin_mail_line_notify' ), 10 ,3 );
        }
    }

    public function mwform_admin_mail_line_notify( $Mail, $values, $Data ) {
        $options = get_option( $this->plugin_name );
        $access_token = $options['access_token'];
        $message = $options['message'];
        $message = str_replace( "{subject}", $Mail->subject, $message );
        $message = str_replace( "{body}", $Mail->body, $message );

        $url = 'https://notify-api.line.me/api/notify';
        $response = wp_remote_post( $url, array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer '.$access_token,
            ),
            'body' => array(
                'message' => $message,
            ),
        ));

        return $Mail;
    }

    public function admin_menu()
    {
        add_submenu_page(
            'edit.php?post_type=mw-wp-form',
            __( 'Line Notify', 'line-notify' ),
            __( 'Line Notify', 'line-notify' ),
            'edit_pages',
            $this->plugin_name,
            array( $this, 'edit_page' )
        );
    }

    public function admin_init()
    {
        if ( isset($_POST['mw-wp-form-line-notify-nonce']) && $_POST['mw-wp-form-line-notify-nonce'] ) {
            if ( check_admin_referer( 'mw-wp-form-line-notify', 'mw-wp-form-line-notify-nonce' ) ) {
                $e = new WP_Error();
                $access_token = $_POST['access_token'];
                $form_key = $_POST['form_key'];
                $message = $_POST['message'];
                if ( $form_key ) {
                    $form_keys = array();
                    $keys = explode( ",", $form_key );
                    foreach( $keys as $key ) {
                        if ( preg_match( '/^[0-9]+$/', $key ) ) {
                            $form_keys[] = $key;
                        } else {
                            $e->add( 'error', esc_html__( 'Form key contains invalid value.', 'mw-wp-form-line-notify' ) );
                            set_transient( 'mw-wp-form-line-notify-errors', $e->get_error_messages(), 5 );
                            break;
                        }
                    }
                    $form_key = implode( ',', $keys );
                }
                if ( !$e->get_error_code() ) {
                    $options = get_option( $this->plugin_name );
                    $options['access_token'] = $access_token;
                    $options['form_key'] = $form_key;
                    $options['message'] = $message;
                    update_option( $this->plugin_name, $options );
                    set_transient( 'mw-wp-form-line-notify-updated', true, 5 );
                }
            }
        }
    }

    public function admin_notices()
    {
?>
        <?php if ( $notices = get_transient( 'mw-wp-form-line-notify-errors' ) ): ?>
            <div class="error">
            <ul>
            <?php foreach ( $notices as $notice ): ?>
                <li><?php echo esc_html( $notice );?></li>
            <?php endforeach; ?>
            </ul>
            </div>
        <?php endif; ?>
        <?php if ( $notices = get_transient( 'mw-wp-form-line-notify-updated' ) ): ?>
            <div class="updated">
            <ul>
                <li><?php echo esc_html__( 'Settings has been updated.', 'mw-wp-form-line-notify' );?></li>
            </ul>
            </div>
        <?php endif; ?>
<?php
    }

    public function edit_page()
    {
        if ( isset($_POST['mw-wp-form-line-notify-nonce']) && $_POST['mw-wp-form-line-notify-nonce'] ) {
            $access_token = $_POST['access_token'];
            $form_key = $_POST['form_key'];
            $message = $_POST['message'];
        } else {
            $options = get_option( $this->plugin_name );
            $access_token = isset( $options['access_token'] ) ? $options['access_token'] : '';
            $form_key = isset( $options['form_key'] ) ? $options['form_key'] : '';
            $message = isset( $options['message'] ) ? $options['message'] : '';
        }

?>
<div id="mw-wp-form-line-notify" class="wrap">
<h2>MW WP Form Line Notify</h2>

<form method="post" action="<?php echo esc_attr($_SERVER['REQUEST_URI']); ?>">
<?php wp_nonce_field( 'mw-wp-form-line-notify', 'mw-wp-form-line-notify-nonce' ); ?>

<table class="form-table">
<tbody>
<tr>
<th scope="row"><label for="access_token"><?php echo esc_html__( 'LINE Notify Access Token', 'mw-wp-form-line-notify' );?></label></th>
<td>
<input name="access_token" type="text" id="access_token" placeholder="<?php echo esc_html__( 'Input your access token', 'mw-wp-form-line-notify' );?>" value="<?php echo esc_html( $access_token );?>" class="regular-text">
</td>
</tr>
<tr>
<th scope="row"><label for="form_key"><?php echo esc_html__( 'MW WP Form Key', 'mw-wp-form-line-notify' );?></label></th>
<td>
<input name="form_key" type="text" id="form_key" placeholder="<?php echo esc_html__( 'Input MW WP Form key', 'mw-wp-form-line-notify' );?>" value="<?php echo esc_html( $form_key );?>" class="regular-text">
<p><?php echo esc_html__( 'If you want to set up some forms, input form keys separated by commas.', 'mw-wp-form-line-notify' );?><p>
</td>
</tr>
<tr>
<th scope="row"><label for="message"><?php echo esc_html__( 'Message', 'mw-wp-form-line-notify' );?></label></th>
<td>
<textarea name="message" id="massage" class="regular-text code" placeholder="<?php echo esc_html__( 'Please enter the message to send', 'mw-wp-form-line-notify' );?>" rows="5"><?php echo esc_html( $message );?></textarea>
<p><?php printf( esc_html__( '%s is converted the email subject. %s is converted the email body', 'mw-wp-form-line-notify' ), '<kbd>{subject}</kbd>', '<kbd>{body}</kbd>' );?><p>
</td>
</tr>
</tbody>
</table>

<p class="submit">
<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_html__( 'Update', 'mw-wp-form-line-notify' );?>">
</p>
</form>
</div><!-- #mw-wp-form-line-notify -->
<?php
    }

} // end class mw-wp-form-line-notify

// EOF
