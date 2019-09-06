<?php
/*

Plugin Name: Ghost Login

Plugin URI: http://brightthought.co/

Description: Helps with debugging a specific users experience.

Author: Bright Thought, LLC

Author URI: http://brightthought.co

Version: 1.0.0

Text Domain: ghost-login

Domain Path: /languages

License: GPLv3

License URI: https://www.gnu.org/licenses/gpl.html

*/

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

/**
 * Set the default table name
 */
define('GHOST_TABLE', 'ghost_login');

/**
 * Adds the ghost user link
 */
add_filter('user_row_actions', 'ghostUserOptions', 10, 2);
function ghostUserOptions($actions, $user_object){
	if($user_object->ID != get_current_user_id()) {
		$actions['ghost'] = '<a href="javascript:void()" class="ghost-user-login" data-user="' . $user_object->ID . '">Ghost As User</a>';
	}
	return $actions;
}

/**
 * Creates the database table for ghosting
 */
function createDB(){
	global $wpdb;

	$locations_table = $wpdb->prefix . GHOST_TABLE;
	if($wpdb->get_var("SHOW TABLES LIKE '$locations_table'") !== $locations_table){

		//Get the charset of the database
		$charset_collate = $wpdb->get_charset_collate();

		//Create query to insert new events table

		$sql = "CREATE TABLE $locations_table (
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20),
			admin_id BIGINT(20),
			token VARCHAR(255),
			expires DATETIME,
			PRIMARY KEY  (id)
	) $charset_collate";
		//Creates Table
		dbDelta($sql);
	}
}
createDB();


/**
 * Enqueues the ghosting script only on the user page
 */
add_action('admin_enqueue_scripts', 'ghostScript');
function ghostScript($hook){
	if($hook === 'users.php'){
		wp_enqueue_script('ghost_handle', plugin_dir_url(__FILE__) . 'ghost.js', null, '1.0.0', true);
	}
}

/**
 * Checks if ghosting was enabled from url parameter
 */
add_action('init', 'ghostURLCheck');
function ghostURLCheck(){
	if(isset($_GET['ghost'])){
		global $wpdb;

		$token = $_GET['ghost'];
		$data = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}" . GHOST_TABLE . " WHERE token='{$token}' AND expires > NOW()");
		if($data) {
			setcookie('ghosting', $data->token, time() + (2*60*60));
			wp_set_auth_cookie( $data->user_id, false );
			add_filter('show_admin_bar', '__return_false');
			$user = get_user_by('ID', $data->user_id);
			add_action('wp_footer', function() use ($user, $token){
				ghostDisplayGhosting($user->user_email, $token);
			});
		}
	}
}

/**
 * Checks on following pages that ghosting is still enabled
 */
add_action('init', 'ghostCookieCheck');
function ghostCookieCheck(){
	if(isset($_COOKIE['ghosting']) && !isset($_GET['ghost'])){
		global $wpdb;

		$token = $_COOKIE['ghosting'];
		$data = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}" . GHOST_TABLE . " WHERE token='{$token}' AND expires > NOW()");

		if($data){
			wp_set_auth_cookie($data->user_id, false);

			$user = get_user_by('ID', $data->user_id);
			add_filter('show_admin_bar', '__return_false');
			add_action('wp_footer', function() use ($user, $token){
				ghostDisplayGhosting($user->user_email, $token);
			});
		}else{
			setcookie('ghosting', '', time() - 3600);
		}
	}
}


/**
 * Generates the ghosting token
 *
 * @return string
 * @throws Exception
 */
function random_str(){
	$length = 64;
	$keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

	$pieces = [];
	$max = mb_strlen($keyspace, '8bit') - 1;
	for ($i = 0; $i < $length; ++$i) {
		$pieces []= $keyspace[random_int(0, $max)];
	}
	return implode('', $pieces);
}

/**
 * Creates the ajax handler for ghosting
 */
add_action('wp_ajax_ghost_handler', 'ghostAjax');
function ghostAjax(){
	global $wpdb;

	$user = $_POST['user'];
	$admin = get_current_user_id();
	$token = random_str();

	$row = $wpdb->insert($wpdb->prefix . GHOST_TABLE, [
		'user_id' => $user,
		'admin_id' => $admin,
		'token' => $token,
		'expires' => date('Y-n-d H:i:s', (time() + (2*60*60)))
	]);

	if($row){
		echo home_url('?ghost=' . $token);
	}else {
		echo 'failed';
	}

	exit();
}


/**
 * Clears ghost and returns user back to dashboard
 */
add_action('wp_ajax_ghost_clear', 'ghostClear');
add_action('wp_ajax_nopriv_ghost_clear', 'ghostClear');
function ghostClear(){
	global $wpdb;
	$token = $_POST['token'];

	$data = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}" . GHOST_TABLE . " WHERE token='{$token}'");
	if($data){
		wp_set_auth_cookie($data->admin_id, true);
		unset($_COOKIE['ghosting']);
		setcookie('ghosting', '', time() - 3600, '/');

		echo admin_url();
	}

	exit();
}

/**
 * Displays the ghosting message
 *
 * @param $user
 * @param $token
 */
function ghostDisplayGhosting($user, $token){
	?>
    <div class="ghosting">
        You are currently ghosting as user <?= $user; ?>. Return to admin account by clicking <a href="#" class="ghost-clear">here</a>.
    </div>
    <style>
        body{
            padding-top:30px;
        }
        .ghosting{
            position:fixed;
            z-index:9999999999999999;
            background-color:#000;
            padding:10px 25px;
            color:#fff;
            top:0;
            left:0;
            width:100%;
            font-size:14px;
        }
        .ghosting a{
            color:#fff;
        }
    </style>
    <script>
        var ghost = document.querySelector('.ghost-clear');
        ghost.addEventListener('click', function(e){
            e.preventDefault();

            fetch('<?= admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: 'action=ghost_clear&token=<?= $token; ?>',
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                }
            }).then(function(response){
                return response.text();
            }).then(function(text){
                window.location.href = text;
            })
        });
    </script>
	<?php
}

/**
 * Delete ghost_login table on deactivation
 */
register_deactivation_hook(__FILE__, 'ghostDeactivate');
function ghostDeactivate(){
	global $wpdb;

	$wpdb->query("DROP TABLE {$wpdb->prefix}" . GHOST_TABLE);
}
