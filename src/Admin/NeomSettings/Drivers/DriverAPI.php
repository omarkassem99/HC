<?php
    namespace Bidfood\Admin\NeomSettings\Drivers;
    use WP_REST_Response;
    use WP_Error;
    use Bidfood\Admin\NeomSettings\Drivers\DriverAuth;

    class DriverAPI{
        public function __construct(){
            add_action('rest_api_init',array($this, 'driver_rest_routes'));
        }

        public static function init()
        {
            return new self();
        }

        public function driver_rest_routes(){
            register_rest_route('bidfoodme/v1', '/driver/auth/login', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_driver_login'),
            'permission_callback' => '__return_true', // Adjust permissions as needed
            ));

            register_rest_route('bidfoodme/v1', '/driver/auth/logout', [
                'methods' => 'POST',
                'callback' => array($this,'logout_user'),
                'permission_callback' => 'is_driver_logged_in',
            ]);
        }

        public function handle_driver_login($request){
            $email = $request->get_param('email');
            $password = $request->get_param('password');

            // Authenticate the user
            $authResult = DriverAuth::authenticate($email, $password);

            if (is_wp_error($authResult)) {
                return new WP_Error('login_failed', $authResult->get_error_message(), ['status' => 401]);
            }

            // Return user data and success message
            return new WP_REST_Response([
                'message' => 'Login successful',
                'user' => $authResult,
            ], 200);
        }

        public function logout_user()
        {
            // Call the logout method from DriverAuth
            $logout_response = DriverAuth::logout();

            if($logout_response){
                return new WP_REST_Response([
                    'message' => 'You have been successfully logged out.'
                ], 200);
            }else{
                return new WP_REST_Response([
                    'message' => 'Try again later'
                ], 200);
            }
        }
    }
?>