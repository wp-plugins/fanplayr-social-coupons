<?php
	/**
	* Plugin Name: Fanplayr Social Coupons
	* Plugin URI: http://fanplayr.com/
	* Description: A plugin that adds Fanplayr Social Coupons to your WP e-Commerce shopping cart. See also: <a href="http://fanplayr.com" target="_blank">Fanplayr.com</a> | <a href="https://getsatisfaction.com/fanplayr/" target="_blank">Support</a>
	* Version: 1.0.1
	* Author: Fanplayr Inc.
	* Author URI: http://fanplayr.com/
	**/
  
	define(FANPLAYR_VERSION, '1.0.1');
	
	// ------------------------------------------------------------------------------------------------------------------------------------
	// activate / deactivate	
	
	/* Runs when plugin is activated */
	register_activation_hook(__FILE__,'fanplayr_wpc_install'); 

	/* Runs on plugin deactivation*/
	register_deactivation_hook( __FILE__, 'fanplayr_wpc_remove' );

	function fanplayr_wpc_install()
	{
		add_option("fanplayr_config_secret", '', '', 'no');
		add_option("fanplayr_config_acckey", '', '', 'no');
		add_option("fanplayr_config_shop_id", '', '', 'no');
		add_option("fanplayr_config_widget_keys", '', '', 'no');
	}

	function fanplayr_wpc_remove()
	{
		delete_option('fanplayr_config_secret');
		delete_option('fanplayr_config_acckey');
		delete_option('fanplayr_config_shop_id');
		delete_option('fanplayr_config_widget_keys');
	}

	// ------------------------------------------------------------------------------------------------------------------------------------
	// helpers
	function fanplayr_wpc_jsonMessage($isError, $message, $extras = array())
	{
		global $wp_version;

		$extras['error'] = $isError;
		$extras['message'] = $message;
		$extras['version'] = FANPLAYR_VERSION;
		$extras['wordpress_version'] = $wp_version;
		
		return json_encode($extras);
	}
	
	// postVars as k/v array OR 
	function fanplayr_wpc_httpGetContent($url, $vars = null)
	{
		$ch = curl_init();
		
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1) ;
		
		if ($vars != null) {
			// WTF ?
			if (is_array($vars)) $vars = str_replace('&amp;', '&', http_build_query($vars));
			//curl_setopt($ch, CURLOPT_POSTFIELDS, $vars);
			//curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_URL, $url.'?'.$vars);
		}
		
		$r = curl_exec($ch);
		curl_close($ch);
		
		return $r;
	}
	
	// ------------------------------------------------------------------------------------------------------------------------------------
	// admin only
	if ( is_admin() )
	{
		add_action('admin_menu', 'fanplayr_wpc_admin_menu');

		// add to menu
		function fanplayr_wpc_admin_menu()
		{
			$fpLog = '<span id="fanplayr-social-coupons-menu-item" style="padding-left: 0px;"><div style="border-radius: 1px 1px 1px 1px; display: block; float: left; width: 16px; height: 16px; background-color: rgb(146, 200, 62); margin-right: 5px;"><div style="color: rgb(255, 255, 255); font-weight: bold; font-family: Arial,Helvetica,sans-serif; margin-left: 5px; font-size: 12px;">F</div></div>Fanplayr</span>';
			add_options_page('Fanplayr Social Coupons', $fpLog, 'administrator', 'fanplayr', 'fanplayr_wpc_html_page');
		}
		
		// print page
		function fanplayr_wpc_html_page()
		{
			?>
				<div class="wrap">
					<div id="fanplayr-heading-icon" class="icon32"></div>
					<h2>Fanplayr Social Coupons</h2>
					
					<? echo fanplayr_wpc_display_form(); ?>
				</div>
			<?
		}

		function fanplayr_wpc_display_form()
		{
			// ------------------------------------------------------------------------
			// get local details
			$secret = get_option('fanplayr_config_secret');
			$accKey = get_option('fanplayr_config_acckey');
			$shopId = get_option('fanplayr_config_shop_id');

			$plugin_data = get_plugin_data( __FILE__ );
			$version = FANPLAYR_VERSION;
			
			if (empty($secret)) {
				$secret = md5(uniqid("Things are fine, seriously. I love this!", true));
				update_option('fanplayr_config_secret', $secret);
			}

			// setup vars in case we need 'em
			// ------------------------------------------------------------------------
			// get current admin user info
			global $user_ID;
			$userData = get_userdata($user_ID);

			// get details to send along ...
			$firstname = $userData->user_firstname;
			$lastname = $userData->user_lastname;
			$email = $userData->user_email;

			$shopName = get_option('blogname');
			$shopCountry = get_option('base_country'); // needs mapping, probably 2 letter?

			$adminUrl = 'http://'.$_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
			$adminUrl = substr($adminUrl, 0, strpos($adminUrl, 'options-general.php?page=fanplayr'));
		
			$shopUrl = get_option('siteurl') . '/';

			$pluginUrl = plugin_dir_url(__FILE__);
			$skinDir = $pluginUrl . 'res/';
			
			$queryString = 'secret=' . urlencode($secret);
			$queryString .= '&email=' . urlencode($email);
			$queryString .= '&name=' . urlencode($firstname . ' ' . $lastname);
			$queryString .= '&shopUrl=' . urlencode($shopUrl);
			$queryString .= '&shopName=' . urlencode($shopName);
			$queryString .= '&adminUrl=' . urlencode($adminUrl);

			$queryString .= '&country=' . urlencode($shopCountry);

			// setup our general always needed HTML
			// ------------------------------------------------------------------------
			$generalHtml = <<<EOT
				<script>
					var c = document.createElement("link");
					c.setAttribute("rel", "stylesheet");
					c.setAttribute("type", "text/css");
					c.setAttribute("href", '{$skinDir}fanplayr_socialcoupons.css');
					document.getElementsByTagName("head")[0].appendChild(c);
				</script>
				<script type="text/javascript" src="{$skinDir}jquery-1.7.2.min.js"></script>
				<script type="text/javascript" src="{$skinDir}fanplayr_socialcoupons.js"></script>
				<script>
					$.noConflict()(function($) {
						// set up some vars we need
						Fanplayr.configAccKey = "{$accKey}";
						Fanplayr.configSecret = "{$secret}";
						Fanplayr.configShopId = "{$shopId}";
						Fanplayr.configShopUrl = "{$shopUrl}";
					});
				</script>
EOT;
			
			// actual logic time !
			// ------------------------------------------------------------------------

			// the HTML to output to the admin control
			$ouputHtml = '';

			if (empty($secret) || empty($accKey) || empty($shopId)) {

				// our install HTML
				$installHtml = <<<EOT
					<script>
						fanplayrJQuery().ready(function() {
							var $ = fanplayrJQuery;
							var fanplayrInstallModal = new FanplayrModal('fanplayr_install_modal');
							$('#fanplayr-install-button').on('click', function(e) {
								e.preventDefault();
								fanplayrInstallModal.load('{$skinDir}fanplayr_join.html?{$queryString}');
							});
						});
					</script>
					<div id="fanplayr-install-wrapper">
						<p>
							<img src="{$skinDir}images/fanplayr_logo.png" width="200" height="65" alt="Fanplayr Logo" title="Fanplayr" />
						</p>
						<div id="fanplayr-install-description">
							<p>Welcome to Fanplayr, the best way to add Social Couponing to your Wordpress Store.</p>
							<p>Click on the button below to get started by linking your Fanplayr account to your Wordpress Store. If you don't have an account yet you will have a chance to create one. Easy!</p>
						</div>
						<a href="#" id="fanplayr-install-button"><div class="fanplayr-icon fanplayr-add"></div>Link to Fanplayr Social Coupons</a>
					</div>
EOT;
				$outputHtml = $installHtml;
			}else {
				$errorGettingInstallData = false;
				try {
					$m = json_decode(fanplayr_wpc_httpGetContent('http://my.fanplayr.com/api.wordpressCheckInstall/', array(
						'acc_key' => $accKey,
						'shop_id' => $shopId,
						'secret' => $secret,
						'version' => $version
					)));
				}catch (Exception $e) {
					$errorGettingInstallData = true;
				}
				// just give 'em an error
				if ($errorGettingInstallData) {
					$outputHtml = 'Sorry, there was an error getting information about your installation. Please refresh to try again.';
				}else{
					// the server said there was an error
					if ($m->error) {
						$outputHtml = 'ERROR: ' . $m->message . ' - This may be due to a wrongly linked account. To unlink your account and try again <a href="#" onclick="Fanplayr.unlink();">click here</a>.';
					// all is good, let's continue!
					}else{
					
						// if it's a new version warn 'em!
						$newVersionWarningHtml = '';
						
						if ($m->newVersion) {
							$newVersionWarningHtml = $m->newVersion;
						}

						try {
							$campData = addslashes(fanplayr_wpc_httpGetContent('http://my.fanplayr.com/api.wordpressGetCampaigns/', array(
								'acc_key' => $accKey,
								'shop_id' => $shopId,
								'secret' => $secret,
								'version' => $version
							)));
						}catch (Exception $e) {
							$campData = '';
						}

						$outputHtml = '';
						
						if ($newVersionWarningHtml){
							$outputHtml .= '<div id="fanplayr-new-version">' . $newVersionWarningHtml . '</div>';
						}else {
							$outputHtml .= '';
						}
						
						$wpcActive = is_plugin_active('wp-e-commerce/wp-shopping-cart.php');
						if (!$wpcActive) {
							$outputHtml .= '<div id="fanplayr-no-active-wpc">WP-eCommerce is not installed, or not active. Please install / activate before using Fanplayr. Please see <a href="http://www.fanplayr.com/resources/using-fanplayr-with-wp-ecommerce" target="_blank">this post</a> for more information.</div>';
						}

						$outputHtml .= "<script> fanplayrCampData = null; try { fanplayrCampData = ";
						$outputHtml .= $campData == '' ? 'null;' : 'fanplayrJQuery.parseJSON("'.$campData.'");';
						$outputHtml .= "} catch(e){} </script>";

						$outputHtml .= <<<EOT
							<script>
								fanplayrJQuery().ready(function() {
									var $ = fanplayrJQuery;
									
									$('#fanplayr-none-wrapper, #fanplayr-draft-wrapper, #fanplayr-head-wrapper').css('display','none');
									
									Fanplayr.fillCampaignList($('#fanplayr-campaign-list'), fanplayrCampData);

									if (Fanplayr.hasDraftCampaigns || Fanplayr.hasPublishedCampaigns || Fanplayr.hasRunningCampaigns) {
										if (Fanplayr.hasPublishedCampaigns || Fanplayr.hasRunningCampaigns) {
											$('#fanplayr-head-wrapper').css('display', 'block');
										}else {
											$('#fanplayr-draft-wrapper').css('display', 'block');
										}
									}
								});
								function refreshPage() {
									window.location.reload();
								}
							</script>
							
							<div id="fanplayr-none-wrapper">
								<p>
									<img src="{$skinDir}images/fanplayr_logo.png" width="200" height="65" alt="Fanplayr Logo" title="Fanplayr" />
								</p>
								<div id="fanplayr-none-description">
									<p>Your Store has been linked to Fanplayr, but you still need to create a campaign. Click below to get started.</p>
									<p>You will have to <a href="#" onclick="refreshPage();">refresh</a> the page to see campaigns once you have created them.</p>
								</div>
								<a href="http://my.fanplayr.com/dashboard.campaign.items/" id="fanplayr-start-button" target="_blank"><div class="fanplayr-icon fanplayr-icon-star"></div>Create a Fanplayr Campaign</a>
							</div>
							<script>
								fanplayrJQuery().ready(function() {
									if (fanplayrCampData == null || fanplayrCampData.campaigns.length == 0)
										fanplayrJQuery('#fanplayr-none-wrapper').css('display', 'block');
								});
							</script>

							<div id="fanplayr-draft-wrapper">
								<p>
									<img src="{$skinDir}images/fanplayr_logo.png" width="200" height="65" alt="Fanplayr Logo" title="Fanplayr" />
								</p>
								<div id="fanplayr-draft-description">
									<p>You've got a campaign but it's not quite ready to show on your store. Edit a current campaign, and once it's published you can show it on your Store.</p>
								</div>
							</div>

							<div id="fanplayr-head-wrapper">
								<p>
									<img src="{$skinDir}images/fanplayr_logo.png" width="200" height="65" alt="Fanplayr Logo" title="Fanplayr" />
								</p>
								<div id="fanplayr-head-description">
									<p>Awesome. Looks like there's campaigns you can add to your Store. Just click the buttons below to add and remove them to your Store.</p>
								</div>
							</div>
							
							<table cellspacing="0" id="fanplayr-campaign-list"></table>
EOT;
					}
				}
			}

			//
			// Return
			return $generalHtml . $outputHtml;
		}
	}
	
	// ------------------------------------------------------------------------------------------------------------------------------------
	// API
	// do this before print, but after other stuff
	add_action('init', 'fanplayr_wpc_init', 101);
	
	function fanplayr_wpc_init()
	{
		$dir = dirname(__FILE__);
		
		if (isset($_REQUEST['fanplayr']))
		{
			require_once('Fanplayr.class.php');
			
			$api = new FanplayrApi();
		
			if (isset($_REQUEST['coupon'])) {
				if (isset($_REQUEST['add'])) {
					$api->applyCoupon();
				}else{
					echo fanplayr_wpc_jsonMessage(true, 'Please use a valid method.');
				}
			}else if(isset($_REQUEST['compy'])) {
				if (isset($_REQUEST['setinstalldata'])) {
					$api->setInstallData();
				} elseif (isset($_REQUEST['joincomplete'])) {
					$api->joinComplete();
				} elseif (isset($_REQUEST['unlink'])) {
					$api->unlink();
				} elseif (isset($_REQUEST['getrules'])) {
					$api->getRules();
				} elseif (isset($_REQUEST['addwidget'])) {
					$api->addWidget();
				} elseif (isset($_REQUEST['removewidget'])) {
					$api->removeWidget();
				}else {
					echo fanplayr_wpc_jsonMessage(true, 'Please use a valid method.');
				}
			}else {
				echo fanplayr_wpc_jsonMessage(true, 'Please use a valid method.');
			}
			
			exit(1);
		}
	}
	
	// ------------------------------------------------------------------------------------------------------------------------------------
	// Print it out
	// do this after everything else
	add_action('wp_head', 'fanplayr_wpc_print', 100);
	
	function fanplayr_wpc_curPageURL() {
		$isHTTPS = (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on");
		$port = (isset($_SERVER["SERVER_PORT"]) && ((!$isHTTPS && $_SERVER["SERVER_PORT"] != "80") || ($isHTTPS && $_SERVER["SERVER_PORT"] != "443")));
		$port = ($port) ? ':'.$_SERVER["SERVER_PORT"] : '';
		$url = ($isHTTPS ? 'https://' : 'http://').$_SERVER["SERVER_NAME"].$port.$_SERVER["REQUEST_URI"];
		return $url;
	}

	function fanplayr_wpc_print()
	{
		$_SESSION['fanplayr_last_url'] = fanplayr_wpc_curPageURL();

		try {
			$fanplayr_widget_keys = json_decode(get_option("fanplayr_config_widget_keys"));
			
			echo '<script>';
			foreach($fanplayr_widget_keys as $k => $v) {
				?>window.fplr = (function(d, s, k) {var js = d.createElement(s), fjs = d.getElementsByTagName(s)[0], f = window.fplr || (f = {guid: 1, config: {}, _r: [], _s:[], ready: function(a) { f._r.push(a) }, show: function(a) { f._s.push(a) }}); js.async = true; js.src = 'http://my.fanplayr.com/website/' + k + '/?v=2'; fjs.parentNode.insertBefore(js, fjs); document.write('<div id="fplr-' + f.guid + '"></div>'); f.config[f.guid++] = {key: k}; return f; })(document, 'script', '<?=$v?>');<?
			}
			echo '</script>';
		}catch(Exception $e){}
	}
	
	// -------------------------------------------------------------------------------------------------------------------------------------
	// Short codes
	add_filter( 'the_content', 'fanplayr_wpc_transaction_results', 13 );
	
	function fanplayr_enc($str, $key)
	{
		for ($i = 0; $i < strlen($str); $i++)
		{
			$char = substr($str, $i, 1);
			$keychar = substr($key, ($i % strlen($key))-1, 1);
			$char = chr(ord($char)+ord($keychar));
			$result.=$char;
		}
		return rawurlencode(base64_encode($result));
	}
	
	function fanplayr_wpc_transaction_results($content = '')
	{
		global $wpdb, $post;
	
		$postId = $wpdb->get_var( "SELECT id FROM `" . $wpdb->posts . "` WHERE `post_content` LIKE '%[transactionresults]%'  AND `post_type` = 'page' LIMIT 1" );
		
		//if ( preg_match( "/\[fanplayr_track\]/", $content ) )
		if ( (int)$postId == (int)$post->ID )
		{
			$output = '';
			$sessionId = $_GET['sessionid'];
			$purchaseLog = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `" . WPSC_TABLE_PURCHASE_LOGS . "` WHERE `sessionid`= %s LIMIT 1", $sessionId), ARRAY_A );

			$email = wpsc_get_buyers_email($purchaseLog['id']);
			
			$currencyCode = $wpdb->get_results("SELECT `code` FROM `".WPSC_TABLE_CURRENCY_LIST."` WHERE `id`='".get_option('currency_type')."' LIMIT 1",ARRAY_A);
			$localCurrencyCode = $currencyCode[0]['code'];
			
			//$output .= '<pre>' . $email . '</pre>';
			//$output .= '<pre>' . print_r($purchaseLog, true) . '</pre>';
			//$output .= '<pre>' . print_r($localCurrencyCode, true) . '</pre>';
			//$output .= '<pre>' . fanplayr_wpc_get_checkout_field($purchaseLog['id'], 'billingfirstname') . '</pre>';
			//$output .= '<pre>' . fanplayr_wpc_get_checkout_field($purchaseLog['id'], 'billinglastname') . '</pre>';
	
			$orderNumber = ''. $purchaseLog['id'];
			$orderEmail = ''. $email;
			$orderDate = ''. date("Y-m-d H:i:s", $purchaseLog['date']);
			$currency = ''. $localCurrencyCode;
			$orderTotal = ''. number_format((float)$purchaseLog['totalprice'] + (float)$purchaseLog['discount_value'], 2);

			$firstName = ''. fanplayr_wpc_get_checkout_field($purchaseLog['id'], 'billingfirstname');
			$lastName = ''. fanplayr_wpc_get_checkout_field($purchaseLog['id'], 'billinglastname');
			$customerEmail = ''. $email;
			//$customerId = $order->getCustomerId();
			$discountCode = ''. $purchaseLog['discount_data'];
			if (!$discountCode) $discountCode = '';
			$discountAmount = ''. number_format($purchaseLog['discount_value'], 2);

			$vars = '';
			$vars .= 'orderNumber=' . rawurlencode($orderNumber);
			$vars .= '&orderEmail=' . rawurlencode($orderEmail);
			$vars .= '&orderDate=' . rawurlencode($orderDate);
			$vars .= '&currency=' . rawurlencode($currency);
			$vars .= '&orderTotal=' . rawurlencode($orderTotal);
			$vars .= '&firstName=' . rawurlencode($firstName);
			$vars .= '&lastName=' . rawurlencode($lastName);
			$vars .= '&customerEmail=' . rawurlencode($customerEmail);
			//$vars .= '&customerId=' . rawurlencode($customerId);
			$vars .= '&customerId=' . '';
			$vars .= '&discountAmount=' . rawurlencode($discountAmount);
			$vars .= '&shopType=' . rawurlencode('wordpress');
			$vars .= '&discountCode=' . rawurlencode($discountCode);
			//$vars .= '&accountKey=' . rawurlencode(get_option('fanplayr_config_acckey'));
			$vars .= '&hash=' . rawurlencode(md5($orderNumber . $orderEmail . $orderDate . $orderTotal . get_option('fanplayr_config_acckey') . get_option('fanplayr_config_secret')));

			$vars = 'enc=' . fanplayr_enc($vars, md5(get_option('fanplayr_config_secret'))) . '&accountKey=' . rawurlencode(get_option('fanplayr_config_acckey'));
			
			$text = $vars;
			
			$output .= '
				<script>
					(function(d, s) {
						   if (!window.fp_sales_orders){
							   var js = d.createElement(s), fjs = d.getElementsByTagName(s)[0];
							   window.fp_sales_orders = \'' . $vars . '\'; js.async = true;
							   js.src = \'//d1q7pknmpq2wkm.cloudfront.net/js/my.fanplayr.com/fp_sales_orders.js\'; fjs.parentNode.insertBefore(js, fjs);
						   }
					})(document, \'script\'); 
				</script>
			';

			//return preg_replace( "/(<p>)*\[fanplayr_track\](<\/p>)*/", $output, $content );
			
			return $content . $output;
		} else {
			return $content;
		}
	}
	
	function fanplayr_wpc_get_checkout_field($purchaseId, $field){
		global $wpdb;
		$formField = $wpdb->get_results( "SELECT `id`,`type` FROM `" . WPSC_TABLE_CHECKOUT_FORMS . "` WHERE `unique_name` IN ('" . $field . "') AND `active` = '1' ORDER BY `checkout_order` ASC LIMIT 1", ARRAY_A );
		return $wpdb->get_var( $wpdb->prepare( "SELECT `value` FROM `" . WPSC_TABLE_SUBMITED_FORM_DATA . "` WHERE `log_id` = %d AND `form_id` = '" . $formField[0]['id'] . "' LIMIT 1", $purchaseId ) );
	}
?>