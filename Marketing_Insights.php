<?php
/**
 * Plugin Name: 		Marketing Insights
 * Description: 		Displays Marketing insights for behavioral analysis.
 * Version: 			1.0.18
 * Requires at least: 	4.4
 * Requires PHP:      	7.4 or newer
 * Author: 				Dashhound
 * Author URI: 			https://marketinginsights.io/
 * License: 			GPL v2 or later
 * License URI:       	https://www.gnu.org/licenses/gpl-2.0.html
*/

function MKIN_DependentScripts(){
	//wp_enqueue_style('Marketing_Insights_Bootstrap', plugins_url('css/Bootstrap.css', __FILE__), array(), '5.2.3', 'all');

	wp_enqueue_script('Marketing_Insights_Chart_Helper', plugins_url( 'dist/helpers.js', __FILE__ ), array(), '4.0.1', false);
	wp_enqueue_script('Marketing_Insights_Chart_Helper_Segment', plugins_url( 'dist/chunks/helpers.segment.js', __FILE__ ), array(), '4.0.1', false);
	wp_enqueue_script('Marketing_Insights_Chart', plugins_url( 'dist/chart.umd.js', __FILE__ ), array('Marketing_Insights_Chart_Helper','Marketing_Insights_Chart_Helper_Segment'), '4.0.1', false);
	wp_enqueue_style( 'google-fonts',plugins_url('dist/Montserrat-Regular.ttf' ));
	//wp_dequeue_style( 'Marketing_Insights_Bootstrap' );	
}
add_action('admin_enqueue_scripts', 'MKIN_DependentScripts');
add_action( "wp_ajax_insightsTOS", "update_plugin_tos" );
add_action( "wp_ajax_nopriv_insightsTOS", "update_plugin_tos" );


if (get_option('marketingInsights_isValidated')) {
	error_log("Registration is Valid");
}else{
	error_log("Creating New Registration");
	add_option('marketingInsights_isValidated', false);
}

/** Adds Lotame Insights Pixel to Head Tag **/
add_action('wp_head', function() {
	?>
		<link rel="preconnect" href="https://tags.crwdcntrl.net">
		<link rel="preconnect" href="https://bcp.crwdcntrl.net">
		<link rel="dns-prefetch" href="https://tags.crwdcntrl.net">
		<link rel="dns-prefetch" href="https://bcp.crwdcntrl.net">
		<script>
			! function() { 
			var lotameClientId = 15982; 
			var lotameTagInput = { 
				data: {}, 
				config: { 
					clientId: Number(15982) 
				} 
			}; 

			var lotameConfig = lotameTagInput.config || {};
			var namespace = window["lotame_" + lotameConfig.clientId] = {};
				namespace.config = lotameConfig; 
				namespace.data = lotameTagInput.data || {}; 
				namespace.cmd = namespace.cmd || []; 
			} (); 
		</script>
		<script async src="https://tags.crwdcntrl.net/lt/c/15982/lt.min.js"></script>
		<?php
	} , 0);


/**Initializes restful endpoint to authenticate the server **/
add_action( 'rest_api_init', function () {
	register_rest_route( 'LocalAds/v1', 
						'/LocalAds', array(
							'methods' => 'POST',
							'callback' => 'MKIN_ValidatedRegistration',
							'permission_callback' => '__return_true',
						) 
					   );
});

/** adds the Insights page to the admin nav **/
add_action('admin_menu', 'MKIN_PluginSetupMenu');

/** On deactivation, this clears the flag in the options table for marketingInsights_isValidated **/
register_deactivation_hook(__FILE__, function () {
	delete_option('marketingInsights_isValidated');
});

/** Checks if the registration validation has been done **/
function MKIN_marketingInsightsInit(){	
	if (get_option('marketingInsights_isValidated') == false) {
		MKIN_registerPlugin();
	}else{		
		MKIN_GetGraphData();
	}
}

function MKIN_registerPlugin() {
	error_log("Registering Plugin");
	//echo "<script>console.log('firing plugin registration');</script>";
	try {
	  $secret_key = MKIN_generateSecretKey();
	  $secret_key = base64_encode($secret_key);
	  $domain = MKIN_getDomain();
	  $current_user = wp_get_current_user();
	  $marketingInsightsUserEmail = $current_user->user_email;
	  $public_key = get_rest_url( null, 'LocalAds/v1/LocalAds' );
	  $url = 'https://dashhoundv2api.azurewebsites.net/api/Lotame/RegisterWPPlugin';
	  //$url = 'https://dashhoundv2api-demo.azurewebsites.net/api/Lotame/RegisterWPPlugin';
  
	  $data = array(
		'PublicKey' => $public_key,
		'SecretHash' => $secret_key,
		'DomainName' => $domain,
		'InstallType' => 'Wordpress Store',
		'Email' => $marketingInsightsUserEmail
	  );
	  
	  $args = array(
		'body' => json_encode($data, JSON_UNESCAPED_SLASHES),
		'timeout' => 45,
		'headers' => array('Content-Type' => 'application/json'),
		'blocking' => true,
		'data_format' => 'body'
	  );
	  $response = wp_remote_post($url, $args);

	  if (is_wp_error($response)) {
		  	//echo "<script>console.log('registration error');</script>";
		wp_die($response->get_error_message(), 'Error');
	  } else {
		  //echo "<script>console.log('registration successs');</script>";
		$server_output = json_decode($response['body'], true);
  
		if ($response['response']['code'] == 200) {
		  	update_option('marketingInsights_isValidated', true);
		  	MKIN_GetGraphData();
		  	status_header(200);
		} else {
		  add_settings_error(
			'MKIN_register_error',
			'MKIN_register_error',
			'Registration failed. Note: Security Plugins may block the registration process. Please check any security plugins for blocked requests.',
			'error'
		  );
		  status_header(400);
		}
	  }
	} catch (Exception $e) {
		error_log($e->getMessage());
	  wp_die($e->getMessage(), 'Error');
	}
}
  
/** Checks if there is a vendasta account for this domain and determines if the 'View All Reporting' button is shown or not **/
function MKIN_ValidateVendastaUser(){

	$url = 'https://dashhoundv2api.azurewebsites.net/api/Lotame/ValidateWPVendastaUser';
	$marketingInsightsDomainName = MKIN_getDomain();
	$marketingInsights_secretKey = get_option('marketingInsights_secretKey');
	$marketingInsights_secretKey = base64_encode($marketingInsights_secretKey);
	$current_user = wp_get_current_user();
	$marketingInsightsUserEmail = $current_user->user_email;

	$data = array(
		'Message' => $marketingInsightsUserEmail,
		'SecretKey' => $marketingInsights_secretKey,
		'DomainName' => $marketingInsightsDomainName
	);
	
	$args = array(
		'body' => json_encode($data, JSON_UNESCAPED_SLASHES),
		'timeout' => 45,
		'headers' => array('Content-Type' => 'application/json'),
		'blocking' => true,
		'data_format' => 'body'
	);

	$response = wp_remote_post($url, $args);
	//logs the wp_error if there is one
	if ( is_wp_error( $response ) ) {
		$error_message = $response->get_error_message();
		//console log the error
		echo "<script>console.log('Error: " . esc_js($error_message) . "');</script>";
	}
	$server_output = json_decode($response['body'], true);

	echo "<script>var existingVendastaAccount = \"" . esc_js($server_output) . "\";</script>";
	echo "<script>var marketingInsightsDomainName = \"" . esc_js($marketingInsightsDomainName) . "\";</script>";
	echo "<script>var secretKey = \"" . esc_js($marketingInsights_secretKey) . "\";</script>";
}
// gets demographic data from lotame via the server
function MKIN_GetGraphData(){
	//echo "<script>console.log('load graph data');</script>";
	wp_enqueue_style('Marketing_Insights_Bootstrap', plugins_url('css/Bootstrap.css', __FILE__), array(), '5.2.3', 'text/css');
	MKIN_GetUniquesData();
	$url = 'https://dashhoundv2api.azurewebsites.net/api/Lotame/WPPluginDemographicsData';
	$marketingInsights_secretKey = get_option('marketingInsights_secretKey');
	$marketingInsights_secretKey = base64_encode($marketingInsights_secretKey);	
	$thisDomain = MKIN_getDomain();
	$data = array(
		'Message' => 'default',
		'SecretKey' => $marketingInsights_secretKey, 
		'DomainName' => $thisDomain
	);
	
	$args = array(
		'body' => json_encode($data, JSON_UNESCAPED_SLASHES),
		'timeout' => 45,
		'headers' => array('Content-Type' => 'application/json'),
		'blocking' => true,
		'data_format' => 'body'
	);
	$response = wp_remote_post($url, $args);

	if ( is_wp_error( $response ) ) {
		echo "<script>console.log('graph data error');</script>";
		$error_message = $response->get_error_message();
	}

	if (!$response['response']['code'] == 200) {
		
		add_settings_error(
			'MKIN_data_error',
			'MKIN_data_error',
			'Thank you for installing Marketing Insights. "Please allow website data to be gathered. In the meantime we have please visit (marketinginsights.io) for any information.',
			'error'
		  );
	}
	//echo "<script>console.log('graph load success');</script>";
	$server_output = json_decode($response['body'], true);

	MKIN_ValidateVendastaUser();

	//sets the js datasource for the insights chart
	$marketingInsightsdata = json_encode($server_output,JSON_UNESCAPED_SLASHES);

	if(!empty($marketingInsightsdata)){
		foreach($marketingInsightsdata as $key => $value){
			if(is_string($value)){
				$marketingInsightsdata[$key] = sanitize_text_field($value);
			}
		}
	}
	
	//This variable is created to be used in the Insights.html file that is loaded in the admin page
	echo "<script>var marketingInsightsdata = \"" . esc_js($marketingInsightsdata) . "\";</script>";
	echo "<script>var marketingInsightsImage = \"" . plugins_url('img/Marketing-Insights-Loading-Screen.png', __FILE__) . "\";</script>";
	echo "<script>var errorAlertImage = \"" . plugins_url('img/Error-Loading-Modal.png', __FILE__) . "\";</script>";
	echo "<script>var ajaxURL = \"" . admin_url( 'admin-ajax.php' ) . "\";</script>";
	echo "<script>var tosAccepted = \"" . get_option('marketingInsights_acceptTOS'). "\";</script>";
	$page = plugins_url('Insights.html', __FILE__);
	echo "<script>var pluginUrl = \"" . esc_js($page) . "\";</script>";

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $page);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$html = curl_exec($ch);
	curl_close($ch);
	echo $html;

	return $server_output;
}

// gets uniques data from lotame via the server
function MKIN_GetUniquesData(){	
	$url = 'https://dashhoundv2api.azurewebsites.net/api/Lotame/WPPluginUniquesData';
	$marketingInsights_secretKey = get_option('marketingInsights_secretKey');
	$marketingInsights_secretKey = base64_encode($marketingInsights_secretKey);	
	$thisDomain = MKIN_getDomain();
	$data = array(
		'Message' => 'default',
		'SecretKey' => $marketingInsights_secretKey, 
		'DomainName' => $thisDomain
	);
	
	$args = array(
		'body' => json_encode($data, JSON_UNESCAPED_SLASHES),
		'timeout' => 45,
		'headers' => array('Content-Type' => 'application/json'),
		'blocking' => true,
		'data_format' => 'body'
	);
	$response = wp_remote_post($url, $args);

	if ( is_wp_error( $response ) ) {
		$error_message = $response->get_error_message();
	}

	if (!$response['response']['code'] == 200) {
		add_settings_error(
			'MKIN_data_error',
			'MKIN_data_error',
			'Thank you for installing Marketing Insights. "Please allow website data to be gathered. In the meantime we have please visit (marketinginsights.io) for any information.',
			'error'
		  );
	}
	
	$server_output = json_decode($response['body'], true);


	//sets the js datasource for the insights chart
	$marketingInsightsdata = json_encode($server_output,JSON_UNESCAPED_SLASHES);
	
	//This variable is created to be used in the Insights.html file that is loaded in the admin page
	echo "<script>var marketingUniquesData = \"" . esc_js($marketingInsightsdata) . "\";</script>";	

	return $server_output;
}
  function accept() {
       
        add_option('marketingInsights_acceptTOS', 'true');
        exit;
    }   
function MKIN_ValidatedRegistration($request) { 
    $marketingInsights_secretKey = get_option('marketingInsights_secretKey');
	$marketingInsights_secretKey = base64_encode($marketingInsights_secretKey);	

    return new WP_REST_Response(array('secret' => $marketingInsights_secretKey), 200);//returns the secret as a response
}

function MKIN_generateSecretKey() {
    $marketingInsights_secretKey = '';
    $charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    
    // Generates a random 128-byte key using the character set
    for ($i = 0; $i < 128; $i++) {
        // Append a random character from the character set to the key string
        $marketingInsights_secretKey .= $charset[mt_rand(0, strlen($charset) - 1)];
    }
    
    // Checks for and deletes any previously stored keys
    if (get_option('marketingInsights_secretKey') !== false) {
        delete_option('marketingInsights_secretKey');
    }
    add_option('marketingInsights_secretKey', $marketingInsights_secretKey);

	return $marketingInsights_secretKey;
}

function MKIN_getDomain() {
    $domain = sanitize_url($_SERVER['HTTP_HOST']);
	$domain = str_replace('www.', '', $domain);
	$domain = preg_replace('#^https?://#', '', $domain);
    $domain = str_replace('/', '', $domain);
    $domain = str_replace(' ', '', $domain);
    $domain = strtolower($domain);  
    return $domain;
}

function MKIN_PluginSetupMenu(){
	$base64MenuIcon = 'PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+CjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMjAwMTA5MDQvL0VOIgogICAgICAgICAgICAgICJodHRwOi8vd3d3LnczLm9yZy9UUi8yMDAxL1JFQy1TVkctMjAwMTA5MDQvRFREL3N2ZzEwLmR0ZCI+Cgo8c3ZnIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIKICAgICB3aWR0aD0iMTAuMTExMWluIiBoZWlnaHQ9IjEzLjg4ODlpbiIKICAgICB2aWV3Qm94PSIwIDAgNzI4IDEwMDAiPgogIDxwYXRoIGlkPSJTZWxlY3Rpb24gIzgiCiAgICAgICAgZmlsbD0iYmxhY2siIHN0cm9rZT0iYmxhY2siIHN0cm9rZS13aWR0aD0iMSIKICAgICAgICBkPSJNIDU4LjAwLDU4MC4wMAogICAgICAgICAgIEMgNTguMDAsNTgwLjAwIDM1LjAxLDUyOC4wMCAzNS4wMSw1MjguMDAKICAgICAgICAgICAgIDE4LjM1LDQ4Ny42MyA1LjMyLDQ0Mi41NSAxLjA0LDM5OS4wMAogICAgICAgICAgICAgMS4wNCwzOTkuMDAgMS4wNCwzOTAuMDAgMS4wNCwzOTAuMDAKICAgICAgICAgICAgIDEuMDQsMzkwLjAwIDAuMDAsMzgwLjAwIDAuMDAsMzgwLjAwCiAgICAgICAgICAgICAwLjAwLDM4MC4wMCAxLjAwLDM1MC4wMCAxLjAwLDM1MC4wMAogICAgICAgICAgICAgMS4wMCwzNTAuMDAgMS45MSwzNDAuMDAgMS45MSwzNDAuMDAKICAgICAgICAgICAgIDMuODQsMzEzLjU4IDkuMTksMjg3LjMwIDE2Ljk4LDI2Mi4wMAogICAgICAgICAgICAgNDkuOTQsMTU0LjgzIDEyNy4zNiw2Ny4zMCAyMzIuMDAsMjUuNDUKICAgICAgICAgICAgIDI3NS41NSw4LjAzIDMyMi4yNCwtMC4wNyAzNjkuMDAsMC4wMAogICAgICAgICAgICAgMzY5LjAwLDAuMDAgMzg4LjAwLDEuMDAgMzg4LjAwLDEuMDAKICAgICAgICAgICAgIDM4OC4wMCwxLjAwIDQwNy4wMCwyLjg1IDQwNy4wMCwyLjg1CiAgICAgICAgICAgICA0NDMuMzQsNy4xOSA0NzQuNDEsMTYuMjEgNTA4LjAwLDMwLjU4CiAgICAgICAgICAgICA1NDUuNzQsNDYuNzMgNTg1Ljg4LDc0LjIyIDYxNS4wMCwxMDMuMDAKICAgICAgICAgICAgIDY0OS4wNSwxMzYuNjYgNjc3LjU3LDE3Ny45NCA2OTYuNDIsMjIyLjAwCiAgICAgICAgICAgICA2OTYuNzgsMjIyLjg1IDY5Ny4xNCwyMjMuNzAgNjk3LjUwLDIyNC41NAogICAgICAgICAgICAgNzEyLjE1LDI1OS40MCA3MjAuNjUsMjkyLjI5IDcyNS4xNSwzMzAuMDAKICAgICAgICAgICAgIDcyNS4xNSwzMzAuMDAgNzI3LjAwLDM1MC4wMCA3MjcuMDAsMzUwLjAwCiAgICAgICAgICAgICA3MjcuMDAsMzUwLjAwIDcyOC4wMCwzNjkuMDAgNzI4LjAwLDM2OS4wMAogICAgICAgICAgICAgNzI4LjAwLDM2OS4wMCA3MjguMDAsMzgwLjAwIDcyOC4wMCwzODAuMDAKICAgICAgICAgICAgIDcyOC4wMCwzODAuMDAgNzI3LjA5LDM5MC4wMCA3MjcuMDksMzkwLjAwCiAgICAgICAgICAgICA3MjIuNzksNDUyLjg1IDcwMi4zNSw1MDcuMjcgNjc2LjMxLDU2NC4wMAogICAgICAgICAgICAgNjMwLjA4LDY2NC42OSA1NDguOTUsNzc5LjM0IDQ3OS42MCw4NjYuMDAKICAgICAgICAgICAgIDQ1Mi42OCw4OTkuNjQgNDI1LjAzLDkzMy4wOSAzOTYuMDcsOTY1LjAwCiAgICAgICAgICAgICAzOTYuMDcsOTY1LjAwIDM3NS4yOCw5ODguMDAgMzc1LjI4LDk4OC4wMAogICAgICAgICAgICAgMzczLjEyLDk5MC40NSAzNjguMDMsOTk2LjkxIDM2NS4wMCw5OTcuNjYKICAgICAgICAgICAgIDM2Mi4xOSw5OTguMzcgMzU5LjgyLDk5NS43MyAzNTguMDAsOTkzLjk4CiAgICAgICAgICAgICAzNTguMDAsOTkzLjk4IDM0Ni4wMSw5ODEuMDAgMzQ2LjAxLDk4MS4wMAogICAgICAgICAgICAgMzQ2LjAxLDk4MS4wMCAzMjkuODMsOTYzLjAwIDMyOS44Myw5NjMuMDAKICAgICAgICAgICAgIDMyOS44Myw5NjMuMDAgMjk0Ljc1LDkyMy4wMCAyOTQuNzUsOTIzLjAwCiAgICAgICAgICAgICAyOTQuNzUsOTIzLjAwIDI0My40NSw4NjEuMDAgMjQzLjQ1LDg2MS4wMAogICAgICAgICAgICAgMjA2LjAwLDgxNC4xOCAxNzAuNTksNzY1Ljg4IDEzNy4zMyw3MTYuMDAKICAgICAgICAgICAgIDEzNy4zMyw3MTYuMDAgMTAyLjYwLDY2MS4wMCAxMDIuNjAsNjYxLjAwCiAgICAgICAgICAgICAxMDIuNjAsNjYxLjAwIDk0LjQwLDY0Ny4wMCA5NC40MCw2NDcuMDAKICAgICAgICAgICAgIDkzLjM0LDY0NS4yMSA5MS4yMSw2NDIuMDkgOTEuMjksNjQwLjAwCiAgICAgICAgICAgICA5MS40MSw2MzcuMzEgOTQuMjQsNjM0LjgxIDk2LjAxLDYzMy4wMAogICAgICAgICAgICAgOTYuMDEsNjMzLjAwIDEwOS4wMCw2MjAuMDAgMTA5LjAwLDYyMC4wMAogICAgICAgICAgICAgMTA5LjAwLDYyMC4wMCAxNjMuMDAsNTY2LjAwIDE2My4wMCw1NjYuMDAKICAgICAgICAgICAgIDE2My4wMCw1NjYuMDAgMTc4LjAwLDU1MS4wMCAxNzguMDAsNTUxLjAwCiAgICAgICAgICAgICAxNzkuNjcsNTQ5LjM3IDE4Mi41MSw1NDYuMjAgMTg1LjAwLDU0Ni4yMAogICAgICAgICAgICAgMTg3LjY5LDU0Ni4yMCAxOTEuMTgsNTUwLjIwIDE5My4wMCw1NTIuMDAKICAgICAgICAgICAgIDE5My4wMCw1NTIuMDAgMjEwLjAwLDU2OS4wMCAyMTAuMDAsNTY5LjAwCiAgICAgICAgICAgICAyMTAuMDAsNTY5LjAwIDI3OC4wMCw2MzcuMDAgMjc4LjAwLDYzNy4wMAogICAgICAgICAgICAgMjc4LjAwLDYzNy4wMCAyOTYuMDAsNjU1LjAwIDI5Ni4wMCw2NTUuMDAKICAgICAgICAgICAgIDI5Ny43Nyw2NTYuNzYgMzAxLjQwLDY2MC45NiAzMDQuMDAsNjYwLjk2CiAgICAgICAgICAgICAzMDcuMjEsNjYwLjk2IDMxNy4zMCw2NDkuNzAgMzIwLjAwLDY0Ny4wMAogICAgICAgICAgICAgMzIwLjAwLDY0Ny4wMCAzNjIuMDAsNjA1LjAwIDM2Mi4wMCw2MDUuMDAKICAgICAgICAgICAgIDM2Mi4wMCw2MDUuMDAgNTAwLjAwLDQ2Ny4wMCA1MDAuMDAsNDY3LjAwCiAgICAgICAgICAgICA1MDAuMDAsNDY3LjAwIDUzNS4wMCw0MzIuMDAgNTM1LjAwLDQzMi4wMAogICAgICAgICAgICAgNTM3LjQ1LDQyOS41NSA1NDQuNzgsNDIxLjIwIDU0OC4wMCw0MjEuMjAKICAgICAgICAgICAgIDU1MC45Niw0MjEuMjAgNTU1LjkxLDQyNi45MSA1NTguMDAsNDI5LjAwCiAgICAgICAgICAgICA1NjQuMDYsNDM1LjA2IDU3NS43Myw0NDcuNTEgNTgyLjAwLDQ1Mi4wMAogICAgICAgICAgICAgNTgyLjAwLDQ1Mi4wMCA1OTMuNTgsNDA3LjAwIDU5My41OCw0MDcuMDAKICAgICAgICAgICAgIDU5My41OCw0MDcuMDAgNjExLjE1LDM0MS4wMCA2MTEuMTUsMzQxLjAwCiAgICAgICAgICAgICA2MTEuMTUsMzQxLjAwIDYyMi4wMCwzMDAuMDAgNjIyLjAwLDMwMC4wMAogICAgICAgICAgICAgNjIyLjAwLDMwMC4wMCA1ODguMDAsMzA5LjQyIDU4OC4wMCwzMDkuNDIKICAgICAgICAgICAgIDU4OC4wMCwzMDkuNDIgNTE1LjAwLDMyOS4xMiA1MTUuMDAsMzI5LjEyCiAgICAgICAgICAgICA1MTUuMDAsMzI5LjEyIDQ3MS4wMCwzNDEuMDAgNDcxLjAwLDM0MS4wMAogICAgICAgICAgICAgNDcxLjAwLDM0MS4wMCA1MDMuMDAsMzc1LjAwIDUwMy4wMCwzNzUuMDAKICAgICAgICAgICAgIDUwMy4wMCwzNzUuMDAgMzUzLjAwLDUyNS4wMCAzNTMuMDAsNTI1LjAwCiAgICAgICAgICAgICAzNTMuMDAsNTI1LjAwIDMxNy4wMCw1NjEuMDAgMzE3LjAwLDU2MS4wMAogICAgICAgICAgICAgMzE0LjI5LDU2My43MSAzMDYuOTUsNTcyLjY1IDMwMy4wMCw1NzEuNjYKICAgICAgICAgICAgIDMwMC43Myw1NzEuMTAgMjk2Ljc0LDU2Ni43NCAyOTUuMDAsNTY1LjAwCiAgICAgICAgICAgICAyOTUuMDAsNTY1LjAwIDI3OC4wMCw1NDguMDAgMjc4LjAwLDU0OC4wMAogICAgICAgICAgICAgMjc4LjAwLDU0OC4wMCAyMTQuMDAsNDg0LjAwIDIxNC4wMCw0ODQuMDAKICAgICAgICAgICAgIDIxNC4wMCw0ODQuMDAgMTkzLjAwLDQ2My4wMCAxOTMuMDAsNDYzLjAwCiAgICAgICAgICAgICAxOTAuOTMsNDYwLjk0IDE4Ny4yNiw0NTYuMzQgMTg0LjAwLDQ1Ny4yMAogICAgICAgICAgICAgMTgxLjY5LDQ1Ny44MSAxNzUuOTQsNDY0LjA2IDE3NC4wMCw0NjYuMDAKICAgICAgICAgICAgIDE3NC4wMCw0NjYuMDAgMTUyLjAwLDQ4OC4wMCAxNTIuMDAsNDg4LjAwCiAgICAgICAgICAgICAxNTIuMDAsNDg4LjAwIDg5LjAwLDU1MS4wMCA4OS4wMCw1NTEuMDAKICAgICAgICAgICAgIDg5LjAwLDU1MS4wMCA3MS4wMCw1NjkuMDAgNzEuMDAsNTY5LjAwCiAgICAgICAgICAgICA2Ni44OCw1NzMuMTIgNjMuMzIsNTc3LjQ4IDU4LjAwLDU4MC4wMCBaIiAvPgo8L3N2Zz4K';
    add_menu_page( 
	'Insights', 
	'Marketing Insights', 
	'manage_options', 
	'Insights-Page', 
	'MKIN_marketingInsightsInit', 
	'data:image/svg+xml;base64,'.$base64MenuIcon
	);
}

function update_plugin_tos(){
	add_option('marketingInsights_acceptTOS', 'true');
}
?>