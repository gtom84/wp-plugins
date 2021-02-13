<?php
/**
 * @package   Toret Email Attachments
 * @author    Vladislav Musílek
 * @license   GPL-2.0+
 * @link      http://toret.cz
 * @copyright 2016 Toret.cz
 */

class Toret_EA {

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since   1.0.0
	 *
	 * @var     string
	 */
	const VERSION = '1.0.0';

	/**
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	protected $plugin_slug = 'toret-email-attachments';

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 *
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Initialize the plugin by setting localization and loading public scripts
	 * and styles.
	 *
	 * @since     1.0.0
	 */
	private function __construct() {

		// Load plugin text domain
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// Activate plugin when new blog is added
		add_action( 'wpmu_new_blog', array( $this, 'activate_new_site' ) );

		//Add email attachments
		add_filter( 'woocommerce_email_attachments', array( $this, 'add_attachments' ), 10, 3 );

 	}

	/**
	 * Return the plugin slug.
	 *
	 * @since    1.0.0
	 *
	 * @return    Plugin slug variable.
	 */
	public function get_plugin_slug() {
		return $this->plugin_slug;
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Fired when the plugin is activated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Activate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       activated on an individual blog.
	 */
	public static function activate( $network_wide ) {

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide  ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_activate();
				}

				restore_current_blog();

			} else {
				self::single_activate();
			}

		} else {
			self::single_activate();
		}

	}

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Deactivate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       deactivated on an individual blog.
	 */
	public static function deactivate( $network_wide ) {

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_deactivate();

				}

				restore_current_blog();

			} else {
				self::single_deactivate();
			}

		} else {
			self::single_deactivate();
		}

	}

	/**
	 * Fired when a new site is activated with a WPMU environment.
	 *
	 * @since    1.0.0
	 *
	 * @param    int    $blog_id    ID of the new blog.
	 */
	public function activate_new_site( $blog_id ) {

		if ( 1 !== did_action( 'wpmu_new_blog' ) ) {
			return;
		}

		switch_to_blog( $blog_id );
		self::single_activate();
		restore_current_blog();

	}

	/**
	 * Get all blog ids of blogs in the current network that are:
	 * - not archived
	 * - not spam
	 * - not deleted
	 *
	 * @since    1.0.0
	 *
	 * @return   array|false    The blog ids, false if no matches.
	 */
	private static function get_blog_ids() {

		global $wpdb;

		// get an array of blog ids
		$sql = "SELECT blog_id FROM $wpdb->blogs
			WHERE archived = '0' AND spam = '0'
			AND deleted = '0'";

		return $wpdb->get_col( $sql );

	}

	
	/**
	 * Fired for each blog when the plugin is activated.
	 *
	 * @since    1.0.0
	 */
	private static function single_activate() {

    }

	/**
	 * Fired for each blog when the plugin is deactivated.
	 *
	 * @since    1.0.0
	 */
	private static function single_deactivate() {

	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		$domain = $this->plugin_slug;
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, TORETEADIR . 'languages/' . $domain . '-' . $locale . '.mo' );

	}


	/**
	 * Add attachments into email
	 *
	 * @since    1.0.0
	 */
	public function add_attachments($attachments, $email_id, $order) {

		//if( $email_id != 'customer_processing_order' ){ return $attachments; }
		$excluded_customer_email_ids = array( 'customer_reset_password', 'customer_new_account' );
		$excluded_admin_email_ids = array( 'new_order', 'cancelled_order', 'failed_order' );

		if( in_array( $email_id, $excluded_admin_email_ids ) ){ return $attachments; }
		if( in_array( $email_id, $excluded_customer_email_ids ) ){ return $attachments; }
		
		
		//$attachments, $payment_method_id
		$main_attachments = get_option('toret-ea-option');

		$country = get_post_meta( $order->id, '_billing_country', true );

		if( $country == 'CZ'){
			
			$attachments = $this->get_attachments_by_country($attachments, $main_attachments, 'cs');
			$attachments = $this->get_products_attachments( $attachments, $order, 'cs' );
			
		}elseif( $country == 'SK'){

			$attachments = $this->get_attachments_by_country($attachments, $main_attachments, 'sk');
			$attachments = $this->get_products_attachments( $attachments, $order, 'sk' );
		
		}else{

			$attachments = $this->get_attachments_by_country($attachments, $main_attachments, 'en');
			$attachments = $this->get_products_attachments( $attachments, $order, 'en' );

		}

		return $attachments;

	}

	/**
	 * Get attachments by country
	 *
	 * @since 1.0.0
	 */
	private function get_attachments_by_country($attachments, $main_attachments, $code){
		
		if( !empty( $main_attachments['email-attachment-first-'.$code] ) ){
			$file_id = $this->get_image_id_from_url( $main_attachments['email-attachment-first-'.$code] );
			$file_path = get_attached_file( $file_id );
			$attachments[] = $file_path;
		}
		if( !empty( $main_attachments['email-attachment-second-'.$code] ) ){
			$file_id = $this->get_image_id_from_url( $main_attachments['email-attachment-second-'.$code] );
			$file_path = get_attached_file( $file_id );
			$attachments[] = $file_path;
		}

		return $attachments;

	}

	/**
	 * Add attachments for product
	 *
	 * @since    1.0.0
	 */
	private function get_products_attachments( $attachments, $order, $code ) {

		$order_items = $order->get_items();

		foreach($order_items as $item){

			$file = get_post_meta( $item['product_id'], 'product-email-attachment-'.$code, true );
			
			if( !empty( $file ) ){
				$file_id = $this->get_image_id_from_url( $file );
				$file_path = get_attached_file( $file_id );
				$attachments[] = $file_path;
			}

		}

		return $attachments;

	}
	

	/**
	 *
	 *
	 *
	 */
	private function get_image_id_from_url( $image_url ) {
    	global $wpdb;
    	$attachment = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid='%s';", $image_url )); 
        
        return $attachment[0]; 
	}


}//End class
