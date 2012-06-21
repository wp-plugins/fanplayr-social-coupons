<?php
	
	if (!function_exists('array_remove')){
		function array_remove( &$array, $val ) {
			foreach ( $array as $i => $v ) {
				if ( $v == $val ) {
					array_splice( $array, $i, 1 );
					return $array;
				}
			}
			return $array;
		}
	}
	
	if (!function_exists('array_remove_all')){
		function array_remove_all( &$array, $val ) {
			$n = array();
			foreach ( $array as $i => $v ) {
				if ( $v != $val ) {
					array_push($n, $v);
				}
			}
			$array = $n;
			return $array;
		}
	}

	class FanplayrApi {
		function __construct() {
		}
		
		/* ---------------------------------------------------------------------------------
			actions
		*/
		public function unlink()
		{
			if (!$this->isPerm()) return;
			
			update_option('fanplayr_config_secret', '');
			update_option('fanplayr_config_acckey', '');
			update_option('fanplayr_config_shop_id', '');
			update_option('fanplayr_config_widget_keys', '');
			
			echo fanplayr_wpc_jsonMessage(false, 'Fanplayr unlink successful.');
		}
		
		public function setInstallData()
		{
			(string)$secret = $_REQUEST['secret'];
			(string)$accKey = $_REQUEST['acckey'];
			(string)$shopId = $_REQUEST['shopid'];
			
			// error, needs more info
			if (empty($secret) || empty($accKey) || empty($shopId)) {
				echo fanplayr_wpc_jsonMessage(true, "Error setting install data. Please provide 'secret', 'acckey' and 'shopid'.");
				return;
			}
			
			// check that the secret is correct
			$actualSecret = get_option('fanplayr_config_secret');
			if ($secret != $actualSecret){
				echo fanplayr_wpc_jsonMessage(true, 'Secret is incorrect.');
				return;
			}
			
			// ok, we can set the new accId
			update_option('fanplayr_config_acckey', $accKey);
			update_option('fanplayr_config_shop_id', $shopId);
			
			// return a good response
			echo fanplayr_wpc_jsonMessage(false, 'Thanks, account ID updated to "' . $accKey . '" and shop ID to "' . $shopId . '".');
		}
		
		public function joinComplete()
		{
			(string)$message = $_REQUEST['message'];
			(string)$error = $_REQUEST['error'];
			
			$adminUrl = get_option('siteurl') . '/wp-admin/';
			$shopUrl = get_option('siteurl') . '/';
			$skinDir = $shopUrl . 'wp-content/plugins/fanplayr-wpc/res/';
			
			$out = <<<EOT
				<html>
					<head>
						<title></title>
						<style>
							#fanplayr-updating-logo {
								width: 200px;
								height: 65px;
								margin: 30px auto;
							}
							#fanplayr-updating-thanks {
								font-family: Helvetica, Arial, Verdana, sans-serif;
								font-size: 120%;
								font-weight: bold;
								text-align:center;
							}
							#fanplayr-updating-spinner {
								width: 43px;
								height: 11px;
								margin: 30px auto;
							}
						</style>
						<script>
							window.top.location.reload(true);
						</script>
					</head>
					<body>
						<div id="fanplayr-updating-logo"><img src="{$skinDir}images/fanplayr_logo.png" width="200" height="65" alt="Fanplayr Logo" title="Fanplayr" /></div>
						<div id="fanplayr-updating-thanks">Thanks. Updating details.</div>
						<div id="fanplayr-updating-spinner"><img src="{$skinDir}images/progress-loader.gif" width="43" height="11" alt="Loading ..." title="Loading ..." /></div>
					</body>
				</html>
EOT;
			echo $out;
		}
		
		public function getRules()
		{
			if (!$this->isPerm()) return;
			
			global $wpdb;
			
			$sql = "SELECT * FROM {$wpdb->prefix}wpsc_coupon_codes";
			$discounts = $wpdb->get_results($sql);
			for ($i = 0; $i < count($discounts); $i++) {
				$discounts[$i]->condition = unserialize($discounts[$i]->condition);
			}
			
			echo fanplayr_wpc_jsonMessage(false, 'Discounts', array('rules'=>$discounts));
		}
		
		public function addWidget()
		{
			$this->addRemoveWidget(false);
		}
		
		public function removeWidget()
		{
			$this->addRemoveWidget(true);
		}

		public function applyCoupon()
		{
			// remember the code
			@$code = $_GET['code'];
			
			// should be able to call it right?
			if (function_exists('wpsc_coupon_price'))
				wpsc_coupon_price($code);
			
			// redirect
			$lastUrl = $_SESSION['fanplayr_last_url'];
			$lastUrl = str_replace('fpshow=1', '', $lastUrl);
			
			if (empty($lastUrl))
				$lastUrl = '../';
			header('Location: ' . $lastUrl);
			
			exit(1);
		}
		
		/* ---------------------------------------------------------------------------------
			helpers
		*/
			
		private function isPerm()
		{
			// required input
			(string)$secret = $_REQUEST['secret'];
			(string)$accKey = $_REQUEST['acckey'];
			
			// error, needs more info
			if (empty($secret) || empty($accKey)) {
				echo fanplayr_wpc_jsonMessage(true, "Error. Needs 'secret' and 'acckey'.");
				return false;
			}
			
			$actualSecret = get_option('fanplayr_config_secret');
			$actualAccKey = get_option('fanplayr_config_acckey');
			
			if ($actualSecret != $secret || $actualAccKey != $accKey) {
				echo fanplayr_wpc_jsonMessage(true, "Error. Either your 'secret' or 'acckey' are incorrect.");
				return false;
			}
			
			return true;
		}

		private function addRemoveWidget($remove = false)
		{
			// required input
			$secret = $_REQUEST['secret'];
			$accKey = $_REQUEST['acckey'];
			$campKey = $_REQUEST['campkey'];
			
			$inform = $_REQUEST['inform'] == '1';

			$shopId = get_option('fanplayr_config_shop_id');
			
			// error, needs more info
			if (empty($secret) || empty($accKey) || empty($campKey)) {
				echo fanplayr_wpc_jsonMessage(true, "Error. Needs 'secret', 'acckey', 'campkey'.");
				return;
			}

			$actualSecret = get_option('fanplayr_config_secret');
			$actualAccKey = get_option('fanplayr_config_acckey');

			if ($actualSecret != $secret || $actualAccKey != $accKey) {
				echo fanplayr_wpc_jsonMessage(true, "Error. Either your 'secret' or 'acckey' are incorrect.");
				return;
			}

			// tell fanplayr about it
			if ($inform) {
				$m = null;
				try {
					global $f;
					$m = json_decode(fanplayr_wpc_httpGetContent('http://my.fanplayr.com/api.wordpressInformWidget/', array(
						'acc_key' => $accKey,
						'shop_id' => $shopId,
						'secret' => $secret,
						'version' => FANPLAYR_VERSION,
						'camp_key' => $campKey,
						'remove' => ($remove ? '1' : '0')
					)));
				}catch (Exception $e) {
					echo fanplayr_wpc_jsonMessage(true, "Errort. Could not inform Fanplayr.");
					return;
				}
				if ($m) {
					if ($m->error) {
						echo fanplayr_wpc_jsonMessage(true, $m->message);
						return;
					}
				}
			}

			// if that worked (or we skipped) now add to local config to actually show it
			$widgetKeys = array();
			try {
				$widgetKeys = json_decode(get_option('fanplayr_config_widget_keys'));
			}catch(Exception $e) {
			}

			if (!is_array($widgetKeys))
				$widgetKeys = array();

			if ($remove){
				array_remove_all($widgetKeys, $campKey);
			}else {
				array_push($widgetKeys, $campKey);
			}

			update_option('fanplayr_config_widget_keys', json_encode($widgetKeys));

			echo fanplayr_wpc_jsonMessage(false, "Widget set.");
			return;
		}
	}

?>