<?php

if ( class_exists( 'WP_REST_Controller' ) ) {
	class PMProAVA_REST_API_Routes extends WP_REST_Controller {
		
		public function pmproava_rest_api_register_routes() {

			$pmproava_namespace = 'pmpro-avatax/v1';

			/**
			 * Get user access for a specific post.
			 * @since 2.3
			 * Example: https://example.com/wp-json/pmpro-avatax/v1/calculate_tax_at_checkout
			 */
			register_rest_route( $pmproava_namespace, '/calculate_tax_at_checkout',
			array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'pmproava_rest_api_calculate_tax_at_checkout'),
					'permission_callback' => array( $this, 'pmproava_rest_api_get_permissions_check' ),
				)));
		}
		
		/**
		 * Calculate tax at checkout.
		 * @since 2.3
		 * Example: https://example.com/wp-json/wp/v2/posts/58/user_id/2/pmpro_has_membership_access
		 * Example: https://example.com/wp-json/pmpro/v1/has_membership_access?post_id=58&user_id=2
		 */
		function pmproava_rest_api_calculate_tax_at_checkout( $request ) {
			$params = $request->get_params();

			$level_id = isset( $params['level_id'] ) ? intval( $params['level_id'] ) : null;
			if ( null === $level_id ) {
				return new WP_REST_Response( 'level_id not passed through.', 400 );
			}
			$price = isset( $params['price'] ) ? floatval( $params['price'] ) : null;
			if ( null === $price ) {
				return new WP_REST_Response( 'price not passed through.', 400 );
			}
			$line1 = isset( $params['line1'] ) ? sanitize_text_field( $params['line1'] ) : null;
			if ( null === $line1 ) {
				return new WP_REST_Response( 'line1 not passed through.', 400 );
			}
			$city = isset( $params['city'] ) ? sanitize_text_field( $params['city'] ) : null;
			if ( null === $city ) {
				return new WP_REST_Response( 'city not passed through.', 400 );
			}
			$region = isset( $params['region'] ) ? sanitize_text_field( $params['region'] ) : null;
			if ( null === $region ) {
				return new WP_REST_Response( 'region not passed through.', 400 );
			}
			$postalCode = isset( $params['postalCode'] ) ? sanitize_text_field( $params['postalCode'] ) : null;
			if ( null === $postalCode ) {
				return new WP_REST_Response( 'postalCode not passed through.', 400 );
			}
			$country = isset( $params['country'] ) ? sanitize_text_field( $params['country'] ) : null;
			if ( null === $country ) {
				return new WP_REST_Response( 'country not passed through.', 400 );
			}

			$order = new MemberOrder();
			$order->membership_id    = $level_id;
			$order->subtotal         = $price;
			$order->billing          = new stdClass();
			$order->billing->street  = $line1;
			$order->billing->city    = $city;
			$order->billing->city    = $city;
			$order->billing->state   = $region;
			$order->billing->zip     = $postalCode;
			$order->billing->country = $country;
			$tax = $order->getTax();

			return new WP_REST_Response( $tax, 200 );
		}

		/**
		 * Default permissions check for endpoints/routes.
		 * Defaults to 'subscriber' for all GET requests and 
		 * 'administrator' for any other type of request.
		 *
		 * @since 2.3
		 */
		 function pmproava_rest_api_get_permissions_check( $request ) {
			$method = $request->get_method();
			$route = $request->get_route();

			// Default to requiring pmpro_edit_memberships capability.
			$permission = current_user_can( 'pmpro_edit_memberships' );

			// Check other caps for some routes.
			$route_caps = array(
				'/pmpro-avatax/v1/calculate_tax_at_checkout' => true,				
			);
			$route_caps = apply_filters( 'pmproava_rest_api_route_capabilities', $route_caps, $request );			

			if ( isset( $route_caps[$route] ) ) {
				if ( $route_caps[$route] === true ) {
					// public
					$permission = true;
				} else {
					$permission = current_user_can( $route_caps[$route] );				
				}				
			}	

			// Is the request method allowed? We disable DELETE by default.
			if ( ! in_array( $method, pmpro_get_rest_api_methods( $route ) ) ) {
				$permission = false;
			}

			$permission = apply_filters( 'pmproava_rest_api_permissions', $permission, $request );

			return $permission;
		}

	} // End of class

	/**
	 * Register the routes for Paid Memberships Pro.
	 * @since 2.3
	 */
	function pmproava_rest_api_register_custom_routes() {
		$pmproava_rest_api_routes = new PMProAVA_REST_API_Routes;
		$pmproava_rest_api_routes->pmproava_rest_api_register_routes();
	}

	add_action( 'rest_api_init', 'pmproava_rest_api_register_custom_routes', 5 );
}

/**
 * Get the allowed methods for PMPro REST API endpoints.
 * To enable DELETE, hook into this filter.
 * @since 2.3
 */
function pmproava_get_rest_api_methods( $route = NULL ) {
	$methods = array( 'GET', 'POST', 'PUT', 'PATCH' );
	$methods = apply_filters( 'pmproava_rest_api_methods', $methods, $route );
	return $methods;
}