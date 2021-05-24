<?php

class PMPro_AvaTax {

	// Singleton class.
	private static $instance = null;
	private $AvaTaxClient = null;
	private $transaction_cache = array();

	/*
	 * --------- SETUP ---------
	 */
	/**
	 * Connect to AvaTax.
	 *
	 * @since 0.1
	 */
	public function __construct() {
		// Load libraries...
		require_once PMPROAVA_DIR . '/lib/vendor/autoload.php';
		require_once PMPROAVA_DIR . '/lib/vendor/avalara/avataxclient/src/AvaTaxClient.php';

		// Set up AvaTaxClient instance...
		$pmproava_options   = pmproava_get_options();
		$account_number     = $pmproava_options['account_number'];
		$license_key        = $pmproava_options['license_key'];
		$environment        = $pmproava_options['environment'];
		$guzzle_params      = array( 'http_errors' => false );
		$this->AvaTaxClient = new Avalara\AvaTaxClient( 'PMPro AvaTax', '1.0', get_bloginfo( 'name' ), $environment, $guzzle_params );
		$this->AvaTaxClient->withLicenseKey( $account_number, $license_key );
	}

	public static function get_instance() {
		if ( self::$instance == null ) {
			self::$instance = new PMPro_AvaTax();
		}

		return self::$instance;
	}

	/*
	 * --------- GETTERS ---------
	 */

	public function get_transaction_for_order( $order, $document_type = Avalara\DocumentType::C_SALESINVOICE ) {
		$transaction_code = pmproava_get_transaction_code( $order );

		if ( empty( $this->transaction_cache[ $transaction_code ] ) ) {
			$this->transaction_cache[ $transaction_code ] = array();
		}

		if ( empty( $this->transaction_cache[ $transaction_code ][ $document_type ] ) ) {
			$pmproava_options = pmproava_get_options();
			$response         = $this->AvaTaxClient->getTransactionByCode( $pmproava_options['company_code'], $transaction_code, $document_type );
			if ( isset( $response->error ) ) {
				$this->transaction_cache[ $transaction_code ][ $document_type ] = null;
			} else {
				$this->transaction_cache[ $transaction_code ][ $document_type ] = $response;
			}
		}

		return $this->transaction_cache[ $transaction_code ][ $document_type ];
	}

	/*
	 * --------- ACTIONS ---------
	 */

	/**
	 * Create a Avalara\TransactionMode object.
	 *
	 * @param array $args to build transaction from.
	 *
	 * @return Avalara\TransactionMode|null
	 */
	private function build_transaction( $args ) {
		if ( ! $this->check_credentials() ) {
			global $pmproava_error;
			$pmproava_error = "Could not validate credentials in 'build_transaction()'";

			return null;
		}

		$default_args = array(
			'price'            => 0,
			'product_category' => PMPROAVA_GENERAL_MERCHANDISE,
			'billing_address'  => null,
			'document_type'    => Avalara\DocumentType::C_SALESORDER,
			'customer_code'    => '0',
			'entity_use_code'  => '',
			'transaction_code' => '0',
			'commit'           => false,
			'transaction_date' => date( 'Y-m-d' ),
			'currency'         => 'USD',
			'item_code'        => null,
			'item_description' => null,
			'vat_number'       => null,
			'tax_included'     => false
		);
		$args         = array_merge( $default_args, $args );
		extract( $args );

		// Make sure we have a valid company address.
		$pmproava_options          = pmproava_get_options();
		$validated_company_address = $this->validate_address( $pmproava_options['company_address'] );
		if ( empty( $validated_company_address ) ) {
			// Invalid company address. Error would have been thrown in that function.
			return null;
		}
		// Validate billing address.
		$validated_billing_address = $this->validate_address( $billing_address );

		// Create a transaction in AvaTax.
		$transaction_builder = new Avalara\TransactionBuilder(
			$this->AvaTaxClient,
			$pmproava_options['company_code'],
			$document_type,
			$customer_code,
			$transaction_date
		);

		if ( $document_type === Avalara\DocumentType::C_SALESINVOICE ) {
			$transaction_builder->withTransactionCode( $transaction_code );
		}

		if ( empty( $validated_billing_address ) ) {
			// Invalid address. Error would have been thrown in that function.
			return null;
		}

		$transaction_builder->withAddress(
			'shipTo',
			$validated_billing_address->line1,
			$validated_billing_address->line2,
			$validated_billing_address->line3,
			$validated_billing_address->city,
			$validated_billing_address->region,
			$validated_billing_address->postalCode,
			$validated_billing_address->country
		);
		$transaction_builder->withAddress(
			'shipFrom',
			$validated_company_address->line1,
			$validated_company_address->line2,
			$validated_company_address->line3,
			$validated_company_address->city,
			$validated_company_address->region,
			$validated_company_address->postalCode,
			$validated_company_address->country
		);

		// Add product to transaction.
		$transaction_builder->withLine(
			$price,              // $amount
			1,                   // $quantity
			$item_code,          // $itemCode
			$product_category    // $taxCode
		);

		if ( ! empty( $item_description ) ) {
			$transaction_builder->withLineDescription( $item_description );
		}

		// Set currency.
		$transaction_builder->withCurrencyCode( $currency );

		// Make tax retroactive.
		if ( isset( $tax_included ) && $tax_included ) {
			$transaction_builder->withLineTaxIncluded();
		}

		if ( ! empty( $vat_number ) ) {
			$transaction_builder->withBusinessIdentificationNo( $vat_number );
		}

		if ( ! empty( $entity_use_code ) ) {
			$transaction_builder->withEntityUseCode( $entity_use_code );
		}

		// Commit transaction if needed.
		if ( $commit ) {
			$transaction_builder->withCommit();
		}

		$transaction_mode = $transaction_builder->createOrAdjust();
		if ( ! empty( $transaction_mode->errors ) ) {
			global $pmproava_error;
			$pmproava_error = 'Error while creating transaction_mode: ' . $transaction_mode->errors->{''}[0];

			return null;
		}

		// Break transaction cache.
		unset( $this->transaction_cache[ $transaction_code ] );

		return $transaction_mode;
	}

	public function update_transaction_from_order( $order ) {
		global $pmpro_currency;

		// If document recording is disabled, don't make any changes.
		$pmproava_options = pmproava_get_options();
		if ( ! pmproava_environment_same_as_order_environment( $order ) || $pmproava_options['record_documents'] === 'no' ) {
			return false;
		}

		$billing_address = $this->construct_billing_address( $order );

		$membership_level = pmpro_getLevel( $order->membership_id );

		$vat_number = get_pmpro_membership_order_meta( $order->id, 'pmproava_vat_number', true );

		$exemption_reason = get_user_meta( $order->user_id, 'pmproava_user_exemption_reason', true );
		$entity_use_code  = empty( $exemption_reason ) ? '' : $exemption_reason;

		// Get args to send.
		$args = array(
			'price'            => $order->total,
			'product_category' => pmproava_get_product_category( $membership_level->id ),
			'billing_address'  => $billing_address,
			'document_type'    => Avalara\DocumentType::C_SALESINVOICE,
			'customer_code'    => pmproava_get_customer_code( $order->user_id ),
			'entity_use_code'  => $entity_use_code,
			'transaction_code' => pmproava_get_transaction_code( $order ),
			'commit'           => in_array( $order->status, array(
				'success',
				'cancelled'
			) ) ? true : false,
			'transaction_date' => ! empty( $order->timestamp ) ? date( 'Y-m-d', $order->getTimestamp( true ) ) : null,
			'currency'         => $pmpro_currency,
			'item_code'        => $membership_level->id,
			'item_description' => $membership_level->name,
			'vat_number'       => ! empty( $vat_number ) ? $vat_number : null,
			'tax_included'     => true
		);

		// Update transaction.
		if ( empty( $this->build_transaction( $args ) ) ) {
			pmproava_save_order_error( $order );

			return false;
		}
		update_pmpro_membership_order_meta( $order->id, 'pmproava_last_sync', date( 'Y-m-d H:i:s' ) );

		return true;
	}

	public function void_transaction_for_order( $order, $document_type = Avalara\DocumentType::C_SALESINVOICE ) {
		$pmproava_options = pmproava_get_options();

		// If document recording is disabled, don't make any changes.
		if ( ! pmproava_environment_same_as_order_environment( $order ) || $pmproava_options['record_documents'] === 'no' ) {
			return false;
		}

		$transaction_code             = pmproava_get_transaction_code( $order );
		$void_transaction_model       = new Avalara\VoidTransactionModel();
		$void_transaction_model->code = Avalara\VoidReasonCode::C_DOCVOIDED;
		$transaction_model            = $this->AvaTaxClient->voidTransaction( $pmproava_options['company_code'], $transaction_code, $document_type, null, $void_transaction_model );
		unset( $this->transaction_cache[ $transaction_code ] );
		update_pmpro_membership_order_meta( $order->id, 'pmproava_last_sync', date( 'Y-m-d H:i:s' ) );

		return true;
	}

	public function lock_transaction( $transaction_code, $document_type = Avalara\DocumentType::C_SALESINVOICE ) {
		$pmproava_options = pmproava_get_options();

		if ( ! pmproava_environment_same_as_order_environment( $order ) || $pmproava_options['record_documents'] === 'no' || $pmproava_options['environment'] !== 'sandbox' ) {
			return false;
		}

		$lock_transaction_model           = new Avalara\LockTransactionModel();
		$lock_transaction_model->isLocked = true;
		$transaction_model                = $this->AvaTaxClient->lockTransaction( $pmproava_options['company_code'], $transaction_code, $document_type, null, $lock_transaction_model );

		return true;
	}

	/*
	 * --------- HELPERS ---------
	 */

	/**
	 * Check whether the user's AvaTax credentials are valid.
	 *
	 * @return bool
	 */
	public function check_credentials() {
		static $credentials_valid = null;
		if ( $credentials_valid === null ) {
			$credentials_valid = $this->AvaTaxClient->ping()->authenticated;
		}

		return $credentials_valid;
	}

	/**
	 * Validate an address.
	 *
	 * @param object $address of buyer
	 * @param bool $force validation of the address, regarless of if disabled.
	 *
	 * @return object
	 */
	public function validate_address( $address, $force = false ) {
		if ( empty( $address ) ) {
			global $pmproava_error;
			$pmproava_error = 'Error while validating address: No address was passed';

			return null;
		}

		// Make sure that $address has a full address.
		$address->line1      = isset( $address->line1 ) ? $address->line1 : '';
		$address->line2      = isset( $address->line2 ) ? $address->line2 : '';
		$address->line3      = isset( $address->line3 ) ? $address->line3 : '';
		$address->city       = isset( $address->city ) ? $address->city : '';
		$address->region     = isset( $address->region ) ? $address->region : '';
		$address->postalCode = isset( $address->postalCode ) ? $address->postalCode : '';
		$address->country    = isset( $address->country ) ? $address->country : '';


		$pmproava_options      = pmproava_get_options();
		$countries_to_validate = array( 'us', 'united states', 'ca', 'canada', '' );
		if ( ( 'no' === $pmproava_options['validate_address'] && ! $force ) || // We don't need to validate
		     ! in_array( strtolower( $address->country ), $countries_to_validate ) // Address is not US or CA
		) {
			return $address;
		}

		$address_cache_hash = wp_hash( serialize( $address ) );
		$address_cache_key  = 'pmproava_address_cache_' . $address_cache_hash;
		$response           = get_transient( $address_cache_key );

		if ( empty( $response ) ) {
			// We do not have a cached value. Validate via API.
			$response = $this->AvaTaxClient->resolveAddress(
				$address->line1,
				$address->line2,
				$address->line3,
				$address->city,
				$address->region,
				$address->postalCode,
				$address->country,
				'Mixed' // Text case.
			);
			set_transient( $address_cache_key, $response, 60 * 60 * 24 );
		}

		if ( ! empty( $response->messages ) ) {
			// Invalid address.
			global $pmproava_error;
			$pmproava_error = 'Error while validating address: ' . $response->messages[0]->summary;

			return null;
		} elseif ( ! empty( $response->error ) ) {
			global $pmproava_error;
			$pmproava_error = 'Error while validating address: ' . $response->error->message;

			return null;
		}

		return $response->validatedAddresses[0];
	}

	public function get_entity_use_codes() {
		$entity_use_codes = $this->AvaTaxClient->listEntityUseCodes();

		return property_exists( $entity_use_codes, 'value' ) ? $entity_use_codes->value : array();
	}

	public function construct_billing_address( $order ) {
		// Construct billing address.
		$billing_address             = new stdClass();
		$billing_address->line1      = $order->billing->street;
		$billing_address->city       = $order->billing->city;
		$billing_address->region     = $order->billing->state;
		$billing_address->postalCode = $order->billing->zip;
		$billing_address->country    = $order->billing->country;

		return $billing_address;
	}

	public function calculate_pmpro_tax( $order, $price ) {
		global $pmpro_currency, $pmpro_level;

		$pmpro_level                = pmpro_getLevelAtCheckout();
		$order->gateway_environment = $order->Gateway->gateway_environment ?? 'sandbox';
		// If document recording is disabled, don't make any changes.
		$pmproava_options = pmproava_get_options();

		if ( ! pmproava_environment_same_as_order_environment( $order ) || $pmproava_options['record_documents'] === 'no' ) {
			return null;
		}

		$tax_cache_hash = wp_hash( serialize( $order ) . $price );
		$tax_cache_key  = 'pmproava_tax_cache_' . $tax_cache_hash;
		$response       = get_transient( $tax_cache_key );

		if ( empty( $response ) ) {
			/**
			 * Filter the billing address at checkout.
			 */
			$billing_address = $this->construct_billing_address( $order );

			$membership_level = pmpro_getLevel( $order->membership_id );
			$vat_number       = ! empty( $_REQUEST['pmproava_vat_number'] ) ? sanitize_text_field( $_REQUEST['pmproava_vat_number'] ) : null;   // set the VAT if it exists

			$exemption_reason = get_user_meta( get_current_user_id(), 'pmproava_user_exemption_reason', true );
			$entity_use_code  = empty( $exemption_reason ) ? '' : $exemption_reason;

			// Get args to send.
			$args = array(
				'price'            => max( (float) $price, 0.00 ),
				'product_category' => pmproava_get_product_category( $membership_level->id ),
				'billing_address'  => $billing_address,
				'document_type'    => null,
				'customer_code'    => pmproava_get_customer_code( get_current_user_id() ),
				'entity_use_code'  => $entity_use_code,
				'commit'           => false,
				'transaction_date' => ! empty( $order->timestamp ) ? date( 'Y-m-d', $order->getTimestamp( true ) ) : null,
				'currency'         => $pmpro_currency,
				'item_code'        => $membership_level->id,
				'item_description' => $membership_level->name,
				'vat_number'       => ! empty( $vat_number ) ? $vat_number : null,
			);

			// Call transaction.
			$response = $this->build_transaction( $args );

			set_transient( $tax_cache_key, $response, 60 * 60 * 24 );
		}

		if ( ! empty( $response->messages ) ) {
			// Invalid address.
			global $pmproava_error;
			$pmproava_error = 'Error while validating address: ' . $response->messages[0]->summary;

			return null;
		} elseif ( ! empty( $response->error ) ) {
			global $pmproava_error;
			$pmproava_error = 'Error while validating address: ' . $response->error->message;

			return null;
		}

		return $response;
	}
}

/**
 * Returns the One True Instance of PMPro_AvaTax.
 *
 * @return PMPro_AvaTax
 */
function pmpro_avatax() {
	return PMPro_AvaTax::get_instance();
}
