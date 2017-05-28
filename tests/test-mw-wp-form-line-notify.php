<?php
/**
 * Class MwWpFormLineNotifyTest
 *
 * @package Mw_Wp_Form_Line_Notify
 */

class MwWpFormLineNotifyTest extends WP_UnitTestCase {

    function test_mwform_has_filter() {
        $options = array(
            'form_key' => '1,10,100',
        );
        update_option( 'mw-wp-form-line-notify', $options );

        $mw = new mw_wp_form_line_notify();
        $mw->add_filters();

        $this->assertTrue( true === has_filter( 'mwform_admin_mail_mw-wp-form-1' ) );

        $this->assertTrue( true === has_filter( 'mwform_admin_mail_mw-wp-form-10' ) );

        $this->assertTrue( true === has_filter( 'mwform_admin_mail_mw-wp-form-100' ) );
    }
}
