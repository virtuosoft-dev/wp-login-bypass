<?php
/*
Plugin Name: Login Bypass
Plugin URI: https://github.com/virtuosoft-dev/wp-login-bypass
Description: Allows login without a password via any of the first 100 usernames in a combobox on the login form; used by developers on the .dev.cc or .dev.pw "developer" domains.
Version: 1.0.0
Author: Stephen J. Carnam
Author URI: http://virtuosoft.com
Text-Domain: login-bypass
*/

class LoginBypass
{
	function __construct()
	{
        // Only allow .dev.cc or .dev.pw TLD sites
        $domain = $_SERVER['SERVER_NAME'];
        if (substr($domain, -7) === ".dev.cc" || substr($domain, -7) === ".dev.pw") {
            $this->injectLoginBypass();
        }
	}

    /**
     * Inject Login Bypass into login form
     */
    public function injectLoginBypass() {
        add_action( 'wp_ajax_nopriv_login_bypass', array( $this, 'wp_ajax_nopriv_login_bypass' ) );
        add_action( 'wp_ajax_login_bypass', array( $this, 'wp_ajax_nopriv_login_bypass' ) );
        add_action( 'login_enqueue_scripts', array( $this, 'login_enqueue_scripts' ) );
        add_action( 'login_form', array( $this, 'login_form' ) );
    }

	/**
	 * Enqueues scripts to be loaded on the login page
	 */
	public function login_enqueue_scripts()
	{
		wp_enqueue_script( 'jquery' );
	}

	/**
	 * Outputs <code><option></code> elements for dropdown based on user role
	 * @param array $atts Attributes. Overrides for the get_users() function call.
	 */
	public function echo_user_option_elements( $atts )
	{
		$atts = shortcode_atts( array(
			'number' => 100,
			'orderby' => 'display_name',
			'role' => '',
			'exclude_roles' => array(),
		), $atts );
		$users = get_users( $atts );

		foreach ( $users as $user ) {
			$skip = FALSE;
			$wp_roles = new WP_Roles();
			$roles = array_keys( $user->{$user->cap_key} );
			$cap = $user->{$user->cap_key};
			$roles = ' (';
			$sep = '';
			foreach ( $wp_roles->role_names as $role => $name ) {
				if ( array_key_exists( $role, $cap ) ) {
					if ( in_array( $role, $atts['exclude_roles'] ) ) {
						$skip = TRUE;
					}
					$roles .= $sep . $role;
					$sep = ', ';
				}
			}
			$roles .= ')';
			if ( $skip ) {
				continue;
			}
			echo '<option value="', $user->ID, '">';
			echo $user->user_login;
			echo $roles, '</option>';
		}
	}

	/**
	 * Outputs the dropdown for the username in the login form
	 */
	public function login_form()
	{
		?>
		<p>
			<label for="login_bypass"><?php _e('Login Bypass', 'login-bypass' ); ?><br>
				<select id="login_bypass" style="width:100%;margin:2px 0 15px;">
					<option value="-1" selected="selected"><?php _e( 'Choose Username...', 'login-bypass' ); ?></option>
					<?php
					$this->echo_user_option_elements( array(
						'role' => 'administrator',
					) );
					$this->echo_user_option_elements( array(
						'role' => 'webmaster',
					) );
					$this->echo_user_option_elements( array(
						'role' => 'editor',
					) );
					$this->echo_user_option_elements( array(
						'exclude_roles' => array(
							'administrator',
							'webmaster',
							'editor',
						),
					) );

					// Remember redirect URL or default to admin
					$url = get_admin_url();
					if ( isset( $_REQUEST['redirect_to'] ) ) {
						$url = $_REQUEST['redirect_to'];
					}
					?>
				</select>
		</p>
		<script type="text/javascript">
			(function($) {
				$(function() {
					// Send bypass request via ajax
					$( "#login_bypass" ).change( function() {
						var user_id = $(this).val();
						if ('-1' !== user_id ) {
							var login = {
								action: 'login_bypass',
								user_id: user_id
							};
							$.post('<?php echo admin_url( 'admin-ajax.php' ); ?>', login, function(r){
								if (r < 1) {
									alert('<?php _e( 'Login error: ', 'login-bypass' ); ?>' + r);
								} else {
									window.location.href = '<?php echo $url; ?>';
									$( '#wp-submit' ).attr( 'disabled', 'disabled' ).val( '<?php _e( 'Logging in...', 'login-bypass' ); ?>');
								}
							} );
						}
					} );
				} );
			})(jQuery);
		</script>
	<?php
	}

	/**
	 * AJAX callback method for handling login actions
	 */
	public function wp_ajax_nopriv_login_bypass()
	{
		// Login as the user and return success
		$user_id = intval( $_POST['user_id'] );
		wp_set_auth_cookie( $user_id, TRUE );
		echo 1;
		die();
	}
}

global $loginBypass;
$loginBypass = new LoginBypass();
