<?php

/**
 * @package    Zásilkovna
 * @author    Woo
 * @license   GPL-2.0+
 * @link      http://woo.cz
 * @copyright 2014 woo
 */
class Woo_Zasilkovna {

    /**
     * Plugin version, used for cache-busting of style and script file references.
     *
     * @since   1.0.0
     *
     * @var     string
     */
    const VERSION = '2.7.5';

    /**
     *
     * @since    1.0.0
     *
     * @var      string
     */
    protected $plugin_slug = 'woo-zasilkovna';

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
        add_action('init', array($this, 'load_plugin_textdomain'));

        // Activate plugin when new blog is added
        add_action('wpmu_new_blog', array($this, 'activate_new_site'));

        $licence = get_option('woo-zasilkovna-licence');
        if (empty($licence)) {
            return false;
        } elseif ($licence == 'inactive') {
            return false;
        }

        //Remove all shipping methods, when free is available
        add_filter('woocommerce_package_rates', array($this, 'hide_shipping_when_free_is_available'), 10, 2);

        //Check default select
        add_action('wp_head', array($this, 'zasilkovna_check_select'));

        //Recalculate cart
        add_action('woocommerce_review_order_after_submit', array($this, 'woo_print_autoload_js'));

        //Přidat info do detailu objednávky
        add_action('woocommerce_order_details_after_order_table', array($this, 'zasilkovna_customer_order_info'));

        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'zasilkovna_admin_customer_order_info'));

        //Add info into email
        add_action('woocommerce_email_after_order_table', array($this, 'zasilkovna_customer_email_info'), 15, 2);

        //Zásilkovna select options
        add_action('woocommerce_review_order_after_shipping', array($this, 'zasilkovna_select_option'), 15, 2);

        //Uložit 
        add_action('woocommerce_checkout_update_order_meta', array($this, 'store_pickup_field_update_order_meta'), 15, 2);

        //Zkontrolovat vybranou pobočku
        add_action('woocommerce_checkout_process', array($this, 'zasilkovna_check_pobocka'));

        //Change email template dir
        add_filter('woocommerce_locate_template', array($this, 'woo_local_template'), 10, 3);

        add_action('init', array('WC_Emails', 'init_transactional_emails'));
        
        //Ulož váhu
	add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'zasilkovna_add_cart_weight' ) );
        
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
        if (null == self::$instance) {
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
    public static function activate($network_wide) {

        if (function_exists('is_multisite') && is_multisite()) {

            if ($network_wide) {

                // Get all blog ids
                $blog_ids = self::get_blog_ids();

                foreach ($blog_ids as $blog_id) {

                    switch_to_blog($blog_id);
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
    public static function deactivate($network_wide) {

        if (function_exists('is_multisite') && is_multisite()) {

            if ($network_wide) {

                // Get all blog ids
                $blog_ids = self::get_blog_ids();

                foreach ($blog_ids as $blog_id) {

                    switch_to_blog($blog_id);
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
    public function activate_new_site($blog_id) {

        if (1 !== did_action('wpmu_new_blog')) {
            return;
        }

        switch_to_blog($blog_id);
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

        return $wpdb->get_col($sql);
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

        $domain = 'zasilkovna';
        $locale = apply_filters('plugin_locale', get_locale(), $domain);

        $load = load_textdomain($domain, WP_LANG_DIR . '/zasilkovna/' . $domain . '-' . $locale . '.mo');

        if ($load === false) {
            load_textdomain($domain, WOOZASILKOVNADIR . 'languages/' . $domain . '-' . $locale . '.mo');
        }
    }

    /**
     * Remove all shipping methods, when free is available
     *
     * since 1.1.0
     */
    public function hide_shipping_when_free_is_available($rates, $package) {

        $old_rates = $rates;

        $zasilkovna_option = get_option('zasilkovna_option');
        if (!empty($zasilkovna_option['doprava_zdarma']) && $zasilkovna_option['doprava_zdarma'] == 'default') {
            return $rates;
        }
        
           

        if (version_compare(WOOCOMMERCE_VERSION, '2.6.0', '>=')) {

            $free = false;
            foreach ($rates as $rate_id => $rate) {
                if ('free_shipping' === $rate->method_id) {
                    $free = true;
                    $free_rate_id = $rate_id;
                    break;
                }
            }

            if ($free === true) {
                if (!empty($zasilkovna_option['doprava_zdarma']) && $zasilkovna_option['doprava_zdarma'] == 'all') {
                    foreach ($rates as $key => $item) {
                        $rates[$key]->cost = 0;
                        $rates[$key]->tax = 0;
                        $rates[$key]->taxes = false;
                    }
                } elseif (!empty($zasilkovna_option['doprava_zdarma']) && $zasilkovna_option['doprava_zdarma'] == 'zasilkovna') {
                    foreach ($rates as $key => $item) {
                        $check_if_s_zasilkovna = explode('>', $key);

                        if (!empty($check_if_s_zasilkovna[0]) && $check_if_s_zasilkovna[0] == 'zasilkovna') {
                            $rates[$key]->cost = 0;
                            $rates[$key]->tax = 0;
                            $rates[$key]->taxes = false;
                        }
                    }
                }
                unset($rates[$free_rate_id]);
            }
        } else {

            if (isset($rates['free_shipping'])) {
                if (!empty($zasilkovna_option['doprava_zdarma']) && $zasilkovna_option['doprava_zdarma'] == 'all') {
                    foreach ($rates as $key => $item) {
                        $rates[$key]->cost = 0;
                        $rates[$key]->tax = 0;
                        $rates[$key]->taxes = false;
                    }
                }
                unset($rates['free_shipping']);
            }
        }

        return $rates = apply_filters('zasilkovna_free_shipping_rates', $rates, $old_rates, $package);
    }

 /**
	 * Check default select
	 *
	 *
	 */   
 	public function zasilkovna_check_select(){
  		
  		if( !is_checkout() ){  return; }

  		if ( WC()->cart->needs_shipping() ){
  		?>
  		<script type="text/javascript">
        	jQuery(document).ready(function($){
	      		jQuery('body').on('click', '#place_order', function() {
		      		var pobocka = jQuery('body .zasilkovna_id').val();
          			if( pobocka == 'default' ){
            			alert('<?php _e('Prosím, vyberte pobočku.','zasilkovna'); ?>');
            			return false;
           			}           
	       		});

	      		jQuery( document.body ).on( 'updated_checkout', function() {
                    if(typeof sessionStorage.zasilkovnaVybranaPobocka !== "undefined") {
                    	jQuery( '.zasilkovna_id' ).val(sessionStorage.zasilkovnaVybranaPobocka);
                   }
                   
                    if(typeof sessionStorage.zasilkovnaPobockaName !== "undefined") {
                    	jQuery( '.packeta-selector-branch-name' ).text(sessionStorage.zasilkovnaPobockaName);
                    }
                });

                jQuery( 'body' ).on( 'click', '.packeta-selector-open', function(e){
                	e.preventDefault();
                });

                window.addEventListener('message', function (e) {
    
                	if( typeof packetWidgetBaseUrl == 'undefined' ){
                		return;
                	}

    				if (e.origin !== packetWidgetBaseUrl) {
      					return;
    				}

    				var data = e.data;
    				console.log(data);

    				if (data.packetaBranchId) {
	      				sessionStorage.zasilkovnaVybranaPobocka = data.packetaBranchId;
    				}
    				if (data.packetaBranchId) {
      					sessionStorage.zasilkovnaPobockaName = data.packetaBranchName;
    				}

    				console.log(sessionStorage.zasilkovnaVybranaPobocka);
                	console.log(sessionStorage.zasilkovnaPobockaName);

    			}, false);		


        	});



  		</script>
  		<?php
  		}

  	}


  	/**
	 *
	 * Recalcute cart
	 *
	 */
	public function woo_print_autoload_js(){
		
		if( !is_checkout() ){  return; }

		$zasilkovna_option = get_option( 'zasilkovna_option');
		if( empty( $zasilkovna_option['api_key'] ) ){ return; } 
		?>
		<script type="text/javascript">
        	jQuery(function($) {
				function APIload() {
					var oldZasilkovna = document.querySelector('#zasilkovna-script');
					if (oldZasilkovna) {
						oldZasilkovna.parentNode.removeChild(oldZasilkovna);
					}

					var ref = window.document.getElementsByTagName("script")[0];
					var script = window.document.createElement("script");
					
					script.src = 'https://widget.packeta.com/v6/www/js/packetaWidget.js';
					script.dataset.apiKey = "<?php echo $zasilkovna_option['api_key']; ?>";
					script.id = 'zasilkovna-script';
					ref.parentNode.insertBefore(script, ref);
				}
				APIload();
				$(document.body).on('change', 'input[name="payment_method"]', function() {
					APIload();
					$('body').trigger('update_checkout');
				});
                                
                                
                            
				$(document.body).on('change', 'input[name="shipping_method[0]"]', function() {
					APIload();
					$('body').trigger('update_checkout');
				});
				jQuery( 'body' ).on( 'click', '.zasilkovna-open', function(e){
					
					APIload();
					e.preventDefault();
					jQuery('.packeta-selector-open').click(); 
                                     });
                                
			});

 		</script><?php 

 		$country = woo_get_customer_country();

          		if( $country == 'SK' ){
                	$packeta_country = 'sk';
                	$packeta_language = 'sk';
          		}elseif( $country == 'CZ' ){
	            	$packeta_country = 'cz';
	            	$packeta_language = 'cs';
          		}elseif( $country == 'PL' ){
	            	$packeta_country = 'pl';
	            	$packeta_language = 'pl';
          		}elseif( $country == 'HU' ){
	            	$packeta_country = 'hu';
	            	$packeta_language = 'hu';
          		}elseif( $country == 'RO' ){
	            	$packeta_country = 'ro';
	            	$packeta_language = 'ro';
          		}elseif( $country == 'BG' ){
	            	$packeta_country = 'bg';
	            	$packeta_language = 'bg';
          		}else{
	                $packeta_country = 'cz';
	                $packeta_language = 'cs';
          		}

		?>
		<script>
        	var packetaSelectorOpen = '.packeta-selector-open';
        	var packetaSelectorBranchName = '.packeta-selector-branch-name';
        	var packetaSelectorBranchId = '.zasilkovna_id';
        	var packetaCountry = '<?php echo $packeta_country; ?>';
        	var packetaWidgetLanguage = '<?php echo $packeta_language; ?>';

            var packetaPrimaryButtonColor = '#71c297';
            var packetaBackgroundColor = '#ffffff';
            var packetaFontColor = '#555555';
          //  var packetaFontFamily = 'Arial';
        </script>

		<?php 

	}
    /**
     *
     * Add info to order detail	
     *
     */
    public function zasilkovna_admin_customer_order_info($order) {

        $order_id = Woo_Order_Compatibility::get_order_id($order);

        $zasilkovna_id = get_post_meta($order_id, 'zasilkovna_id_pobocky', true);
        if (!empty($zasilkovna_id)) {

            $zas = Zasilkovna_Helper::set_services();

            if (!in_array($zasilkovna_id, $zas)) {

                $zasilkovna_mista = get_option('zasilkovna_mista');

                $html = '';
                $html .= '<strong>' . __('Zásilkovna - místo vyzvednutí: ', 'zasilkovna') . '</strong><br />';
                $html .= '<strong>' . __('Název: ', 'zasilkovna') . '</strong>: ';
                $html .= '' . $zasilkovna_mista[$zasilkovna_id]['name'] . '<br />';
                $html .= '<strong>' . __('Místo: ', 'zasilkovna') . '</strong>: ';
                $html .= '' . $zasilkovna_mista[$zasilkovna_id]['place'] . '<br />';
                $html .= '<strong>' . __('Ulice: ', 'zasilkovna') . '</strong>: ';
                $html .= '' . $zasilkovna_mista[$zasilkovna_id]['street'] . '<br />';
                $html .= '<strong>' . __('Město: ', 'zasilkovna') . '</strong>: ';
                $html .= '' . $zasilkovna_mista[$zasilkovna_id]['city'] . '<br />';
                $html .= '<strong>' . __('PSČ: ', 'zasilkovna') . '</strong>: ';
                $html .= '' . $zasilkovna_mista[$zasilkovna_id]['zip'] . '<br />';
                $html .= '<a href="' . $zasilkovna_mista[$zasilkovna_id]['url'] . '" target="_blank" class="button">' . __('Zobrazit detail místa', 'zasilkovna') . '</a><br />';

                $field = get_post_meta($order_id, 'zasilkovna_barcode', true);
                if (!empty($field)) {
                    $html .= '<a class="zasilkovna-sledovani" href="https://www.zasilkovna.cz/vyhledavani?det=' . $field . '" target="_blank" class="button">' . __('Sledujte zásilku online', 'zasilkovna') . '</a><br />';
                }

                echo $html;
            }
        }
    }

    /**
     *
     * Add info to order detail	
     *
     */
    public function zasilkovna_customer_order_info($order) {

        $order_id = Woo_Order_Compatibility::get_order_id($order);

       $zasilkovna_id = (int)get_post_meta( $order_id, 'zasilkovna_id_pobocky', true );
  		$barcode = get_post_meta( $order_id, 'zasilkovna_barcode', true );
  		if( !empty( $zasilkovna_id ) ){
  
  			$zas = Zasilkovna_Helper::set_services();
  
  			if( !in_array( $zasilkovna_id, $zas ) ){

  				$zasilkovna_shipping = get_post_meta( $order_id, 'zasilkovna_id_dopravy', true );
        		$zasilkovna_mista = $this->get_shipping_branches( $order_id, $zasilkovna_shipping );
  				
  				$html = '<table class="shop_table order_details zasilkovna_detail">';
        		
  				$html .= Zasilkovna_Outputs::customer_order_info_table( $zasilkovna_id, $zasilkovna_mista, $zasilkovna_shipping );

  				$html .= '<td>';

      			$html .= Zasilkovna_Outputs::sledovani_link( $order_id, $barcode );
      			
  				$html .= '</td>';
  				$html .= '</tr>';
  				$html .= '</table>';

              	echo $html;
   			
   			}else{

   				if( !empty( $barcode ) ){

   					$html = '<table class="shop_table order_details zasilkovna_detail">';
					$html .= '<tr>';
					$html .= '<th>' . __('Sledujte zásilku online: ','zasilkovna') . '</th>';
					$html .= '<td>';
					$html .= Zasilkovna_Outputs::sledovani_link( $order_id, $barcode );  
					$html .= '</td>';
  					$html .= '</tr>';
  					$html .= '</table>'; 				

  					echo $html;

  				}

   			}          

  		}

  
	}

    /**
     * Add info to email
     *
     * @since 1.0.0
     */
    public function zasilkovna_customer_email_info($order, $is_admin) {

        $order_id = Woo_Order_Compatibility::get_order_id($order);

        $zasilkovna_id = get_post_meta($order_id, 'zasilkovna_id_pobocky', true);
        if (!empty($zasilkovna_id)) {

            $zas = Zasilkovna_Helper::set_services();

            if (!in_array($zasilkovna_id, $zas)) {
                $zasilkovna_mista = get_option('zasilkovna_mista');

                $html = '<p><strong>' . __('Zásilkovna - místo vyzvednutí:', 'zasilkovna') . ' </strong><br />';

                $html .= $zasilkovna_mista[$zasilkovna_id]['name'] . '<br />
                 ' . $zasilkovna_mista[$zasilkovna_id]['place'] . '<br />
                 ' . $zasilkovna_mista[$zasilkovna_id]['street'] . '<br />
                 ' . $zasilkovna_mista[$zasilkovna_id]['city'] . '<br />
                 ' . $zasilkovna_mista[$zasilkovna_id]['zip'] . '<br />
                 <a href="' . $zasilkovna_mista[$zasilkovna_id]['url'] . '" target="_blank">' . __('Zobrazit detail místa', 'zasilkovna') . '</a></p>';

                echo $html;
            }
        }

        $field = get_post_meta($order_id, 'zasilkovna_barcode', true);
        if (!empty($field)) {
            echo '<p><a class="zasilkovna-sledovani" href="https://www.zasilkovna.cz/vyhledavani?det=' . $field . '" target="_blank">' . __('Sledujte zásilku online', 'zasilkovna') . '</a></p>';
        }
    }

    /**
     * Create select option for Zasilkovna branches
     *
     * @since 1.0.0
     */
    
    
    public function zasilkovna_select_option(){
      
       $zasilkovna_option = get_option( 'zasilkovna_option' );
              if ( $zasilkovna_option['hide_packeta'] == 'yes') {
                 
     //   echo 'zakaz dopravy zasilkovny';
        
         $doprava_name = explode('>', WC()->session->chosen_shipping_methods[0]);

            if ( $doprava_name[0] == 'zasilkovna') {
             //   echo 'tests'; 
                
            }
        }
        
      //  unset($rates['shipping_method']);
  //  unset( $rates['shipping_method:z-points'] );
                //  if (!empty($doprava_name[0]) && $doprava_name[0] == 'zasilkovna') {
   //   unset( $rates['shipping_method:0'] ); // shipping method with ID (to find it, see screenshot below)
              
              
   


    	$doprava_name = explode('>',WC()->session->chosen_shipping_methods[0]);

    	if ( !empty($doprava_name[1]) ){
    		if ( $doprava_name[1] == 'z-points' ){
          
          		$zasilkovna_mista = get_option( 'zasilkovna_mista');
          		$zasilkovna_option = get_option( 'zasilkovna_option' );
          		$country = woo_get_customer_country();

          		if( $country == 'SK' ){
                	$ico_url = $this->get_zasilkovna_icon( $zasilkovna_option, 'icon_url_sk' );       
                	$zasilkovna_mista = get_option( 'zasilkovna_mista_sk');
                	$packeta_country = 'sk';
          		}elseif( $country == 'CZ' ){
	                $ico_url = $this->get_zasilkovna_icon( $zasilkovna_option, 'icon_url' );       
                	$zasilkovna_mista = get_option( 'zasilkovna_mista_cz');
                	$packeta_country = 'cz';
          		}elseif( $country == 'PL' ){
	                $ico_url = $this->get_zasilkovna_icon( $zasilkovna_option, 'icon_url_pl' );          
                	$zasilkovna_mista = get_option( 'zasilkovna_mista_pl');
                	$packeta_country = 'pl';
          		}elseif( $country == 'HU' ){
	                $ico_url = $this->get_zasilkovna_icon( $zasilkovna_option, 'icon_url_hu' );          
                	$zasilkovna_mista = get_option( 'zasilkovna_mista_hu');
                	$packeta_country = 'hu';
          		}elseif( $country == 'RO' ){
	                $ico_url = $this->get_zasilkovna_icon( $zasilkovna_option, 'icon_url_ro' );     
                	$zasilkovna_mista = get_option( 'zasilkovna_mista_ro');
                	$packeta_country = 'ro';
                         }elseif( $country == 'AT' ){
	              //  $ico_url = $this->get_zasilkovna_icon( $zasilkovna_option, 'icon_url_at' );     
                	$zasilkovna_mista = get_option( 'zasilkovna_mista_at');
                	$packeta_country = 'at';
                        }elseif( $country == 'UA' ){
	                $ico_url = $this->get_zasilkovna_icon( $zasilkovna_option, 'icon_url_ua' );     
                	$zasilkovna_mista = get_option( 'zasilkovna_mista_ua');
                	$packeta_country = 'ua';
          		}elseif( $country == 'BL' ){
	               // $ico_url = $this->get_zasilkovna_icon( $zasilkovna_option, 'icon_url_bl' );     
                	$zasilkovna_mista = get_option( 'zasilkovna_mista_bl');
                	$packeta_country = 'bl';
          		} else{
	                $ico_url = $this->get_zasilkovna_icon( $zasilkovna_option, 'icon_url' );          
    	            $zasilkovna_mista = get_option( 'zasilkovna_mista_cz');
    	            $packeta_country = 'cz';
          		}

          		if( empty( $ico_url ) ){ $ico_url = WOOZASILKOVNAURL . 'assets/images/zasilkovna.png'; }
                        
                        $zasilkovna_option = get_option( 'zasilkovna_option');  
				echo "<script type=\"text/javascript\">
					jQuery(function($) {
						function APIload() {
							var oldZasilkovna = document.querySelector('#zasilkovna-script');
							if (oldZasilkovna) {
								oldZasilkovna.parentNode.removeChild(oldZasilkovna);
							}
		
							var ref = window.document.getElementsByTagName(\"script\")[0];
							var script = window.document.createElement(\"script\");
							
							script.src = 'https://widget.packeta.com/v6/www/js/packetaWidget.js';
							script.dataset.apiKey = \"" . $zasilkovna_option['api_key'] . "\";
							script.id = 'zasilkovna-script';
							ref.parentNode.insertBefore(script, ref);
						}
						APIload();
					});
		
				 </script>"; 
          
            	echo '<tr>';
              		echo '<th class="zasikovna-ico"><img src="'.$ico_url.'" alt="Zásilkovna" /></th>';
//              		echo '<td class="packeta-widget-btn">';
//                		echo '<a href="#" class="button packeta-widget packeta-selector-open">'.__( 'Vybrat', 'zasilkovna' ) .'</a>';
//              		echo '</td>';
                        echo '<td class="packeta-widget-btn">';
                		echo '<a href="#" class="button zasilkovna-open">'.__( 'Vybrat', 'zasilkovna' ) .'</a><a style="display:none;" href="#" class="button packeta-widget packeta-selector-open">'.__( 'Vybrat', 'zasilkovna' ) .'</a>';
              		echo '</td>';
            	echo '</tr>';
            	echo '<tr>';
              		echo '<th><span class="misto">'.__( 'Vybraná pobočka', 'zasilkovna' ) .'</span></th>';
              		echo '<td>';
                		echo '<div class="packeta-selector-branch-name"></div>';
                		echo '<input type="hidden" name="zasilkovna_id" class="zasilkovna_id" value="default" />';
              		echo '</td>';
            	echo '</tr>';
             

        

        	}elseif( $doprava_name[1] == 'pl-paczkomaty' ){
				$mista = get_option( 'zasilkovna_3060_branches' );
				echo '<tr>';
              		echo '<th class="zasikovna-ico"><img src="'.WOOZASILKOVNAURL . 'assets/images/paczkomaty.png" alt="Zásilkovna" /></th>';
              		echo '<td>';
                		echo '<select name="zasilkovna_id" class="zasilkovna_id" style="width:100%;">';
                    		echo '<option value="default">'.__('Zvolte pobočku', 'zasilkovna').'</option>';
                  		foreach($mista as $key => $item){    
                    		echo '<option value="'.$item['code'].'">'.$item['street'].' '.$item['city'].'</option>';
                  		}    
                		echo '</select>';
              		echo '</td>';
            	echo '</tr>';
			}elseif( $doprava_name[1] == 'ua-nova-posta' ){
				$mista = get_option( 'zasilkovna_3616_branches' );
				echo '<tr>';
              		echo '<th class="zasikovna-ico"></th>';
              		echo '<td>';
                		echo '<select name="zasilkovna_id" class="zasilkovna_id" style="width:100%;">';
                    		echo '<option value="default">'.__('Zvolte pobočku', 'zasilkovna').'</option>';
                  		foreach($mista as $key => $item){    
                    		echo '<option value="'.$item['code'].'">'.$item['street'].' '.$item['city'].'</option>';
                  		}    
                		echo '</select>';
              		echo '</td>';
            	echo '</tr>';
			}
                        
 
                        
                        
                        else{
        		$ids = Zasilkovna_Helper::set_shipping_ids();
        		if( !empty( $ids[$doprava_name[1]] ) ){
       				echo '<input type="hidden" name="zasilkovna_id" value="'.$ids[$doprava_name[1]].'" />';                
       			}
       		} 
    	} //konec uprav z-points zasilkovna 
        
        
        $country = woo_get_customer_country();
        
        if( $country == 'CZ' ){
                 //ceska posta - do ruky
                if( $doprava_name[1] === 'ceska-posta-cz' ){
                          $zasilkovna_prices   = get_option( 'zasilkovna_prices' );
                          $ico_url = Zasilkovna_Helper::isset_shipping( $zasilkovna_prices, 'ceska-posta-cz-url-ikona' ); 
                          $zasilkovna_services = get_option( 'zasilkovna_services' );
                          $title =  $zasilkovna_services['service-label-13'];
                          
                          ?>
		<script type="text/javascript">
        	jQuery(function($) {
				function CPload() {
//	if ($('#billing_address_2').val().length == 0) {
//       $(this).closest('p').addClass('validate-required');
//      $('#billing_address_2').addClass('warning');
//       $('#billing_address_2_field>label').append('<abbr class="required test" title="required">*</abbr>');
//       $('#billing_address_2').attr('required', 'required');
//}

//funkce pro overeni cislo popisneho

$('#place_order').bind('click.orderclick', function (event) {
            // Check DATE input value
            var dateInput = $('#billing_address_1').val().length;
            console.log('date input:' + dateInput);
            if (dateInput == 0) {
                alert("Je potřeba zadat číslo popisné.");
                event.preventDefault();
                $('#billing_address_1').css({
                    'border': 'solid 1px red'
                });
                $('html, body').animate({
                    scrollTop: $("#billing_address_2").offset().top - 130
                }, 1000);
            } else {
                // remove click preventDefault
                $('#place_order').unbind('.orderclick');
                $('#billing_address_1').css({
                    'border': 'none'
                });
                $('#billing_address_1 > label > .required').remove();
            }
				});
                                
				}
				CPload();
                             


				$(document.body).on('change', 'input[name="payment_method"]', function() {
					CPload();
					$('body').trigger('update_checkout');
				});
			});

 		</script><?php 
                
                   if (!empty($ico_url)) {           
                         echo '<tr>';
                           echo '<th class="doprava-ico cp"><img src="'.$ico_url.'" alt="'.$title.'" width="100" border="0" /></th>';   
                           echo '<td>'.$title.'</td>';                           
                           echo '</tr>';
                    } else {
                          echo '<tr>';
                           echo '<th class="doprava-ico"><img src="'.WOOZASILKOVNAURL.'assets/images/cp-balik-do-ruky.png" alt="'.$title.'"  width="100" border="0" alt="cp" ></th>';   
                          echo '<td>'.$title.'</td>';                           
                           echo '</tr>';
                    } 
                 }
                 
                  //ceska posta - doruceni na adresu
                if( $doprava_name[1] === 'doruceni-na-adresu-cz' ){
                          $zasilkovna_prices   = get_option( 'zasilkovna_prices' );
                          $ico_url = Zasilkovna_Helper::isset_shipping( $zasilkovna_prices, 'doruceni-na-adresu-cz-url-ikona' ); 
                          $zasilkovna_services = get_option( 'zasilkovna_services' );
                          $title =  $zasilkovna_services['service-label-106'];

                   if (!empty($ico_url)) {           
                         echo '<tr>';
                           echo '<th class="doprava-ico cpn"><img src="'.$ico_url.'" alt="'.$title.'"  width="100" border="0" /></th>';   
                           echo '<td>'.$title.'</td>';                           
                           echo '</tr>';
                    } else { 
                          echo '<tr>';
                           echo '<th class="doprava-ico"><img src="'.WOOZASILKOVNAURL.'assets/images/cp.jpg" alt="'.$title.'"  width="50" border="0" alt="cp" ></th>';   
                          echo '<td>'.$title.'</td>';                           
                           echo '</tr>';
                    } 
                 }
	} //konec country cz
  
  } //konec funkce
        
    
    /**
     * Získat ikonu Zásilkovny
     *  
     * @since 1.0.0
     */
    function get_zasilkovna_icon($zasilkovna_option, $icon) {

        if (!empty($zasilkovna_option[$icon])) {
            $ico_url = $zasilkovna_option[$icon];
            return $ico_url;
        } else {
           // $ico_url = $zasilkovna_option['icon_url'];
            //  echo '<td><img src="'.WOOZASILKOVNAURL.'assets/images/zasilkovna.png" width="130" border="0" alt="zásilkovna"></td>';
        }

        
    }
    
      function get_zasilkovna_icon_test($zasilkovna_prices, $icon) {

        if (!empty($zasilkovna_prices[$icon])) {
            $ico_url = $zasilkovna_prices[$icon];
            return $ico_url;
        } else {
           // $ico_url = $zasilkovna_option['icon_url'];
            //  echo '<td><img src="'.WOOZASILKOVNAURL.'assets/images/zasilkovna.png" width="130" border="0" alt="zásilkovna"></td>';
        }

        
    }

    /**
     * Uložit id místa
     *  
     * @since 1.0.0
     */
    function store_pickup_field_update_order_meta($order_id) {
        $doprava_name = explode('>', WC()->session->chosen_shipping_methods[0]);

        if (!empty($doprava_name[0]) && $doprava_name[0] == 'zasilkovna') {

            if ($_POST['zasilkovna_id']) {

                update_post_meta($order_id, 'zasilkovna_id_pobocky', esc_attr($_POST['zasilkovna_id']));
                update_post_meta($order_id, 'zasilkovna_id_dopravy', WC()->session->chosen_shipping_methods[0]);
            }
        }
    }

    /**
     * Zkontrolovat vybrání pobočky
     *  
     */
    public function zasilkovna_check_pobocka() {

        if (WC()->cart->needs_shipping() && WC()->cart->show_shipping()) {

            $doprava_name = explode('>', WC()->session->chosen_shipping_methods[0]);

            if (!empty($doprava_name[0]) && $doprava_name[0] == 'zasilkovna') {

                if (!$_POST['zasilkovna_id']) {
                    wc_add_notice(__('Chyba - Prosím, zvolte pobočku pro vybranou dopravu.', 'zasilkovna'), 'error');
                } else {
                    if ($_POST['zasilkovna_id']) {

                        if ($_POST['zasilkovna_id'] == 'default') {
                            wc_add_notice(__('Prosíme, zvolte pobočku pro vybranou dopravu.', 'zasilkovna'), 'error');
                        }
                    }
                }
            }
        }
    }

    /**
     * Force WooCommerce to load email template from plugin
     *
     * @since    1.0.0
     */
    public function woo_local_template($template, $template_name, $template_path) {

        if ($template_name == 'zasilkovna-admin-error-info.php') {
            $template = WOOZASILKOVNADIR . 'includes/emails/zasilkovna-admin-error-info.php';
        } elseif ($template_name == 'zasilkovna-admin-error-info-plain.php') {
            $template = WOOZASILKOVNADIR . 'includes/emails/zasilkovna-admin-error-info-plain.php';
        }

        return $template;
    }
    
       /**
	 * Get shipping branches
	 *
	 * @since    1.0.0
	 */
	public function get_shipping_branches( $order_id, $zasilkovna_shipping ) {
    	
  		if( $zasilkovna_shipping == 'zasilkovna>pl-paczkomaty' ){
  			$zasilkovna_mista = get_option( 'zasilkovna_3060_branches');
  		}elseif( $zasilkovna_shipping == 'zasilkovna>ua-nova-posta' ){
  			$zasilkovna_mista = get_option( 'zasilkovna_3616_branches');
                }elseif( $zasilkovna_shipping == 'zasilkovna>ua-nova-posta' ){
  			$zasilkovna_mista = get_option( 'zasilkovna_4993_branches');
  		}else{
  			$zasilkovna_mista = get_option( 'zasilkovna_mista');
  		}

  		return $zasilkovna_mista;

    }
    
    public function zasilkovna_add_cart_weight( $order_id ) {
		global $woocommerce;

		$weight = $woocommerce->cart->cart_contents_weight;
		update_post_meta( $order_id, '_cart_weight', $weight );
	}

}

//End class




