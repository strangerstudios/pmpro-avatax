<?php

class PMProava_SDK_Wrapper {

	// Singlton class.
	private static $instance   = null;
	private $AvaTaxClient      = null;
	private $transaction_cache = array();

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
		$this->AvaTaxClient = new Avalara\AvaTaxClient( 'PMPro Avalara', '1.0', get_bloginfo( 'name' ), $environment, $guzzle_params );
		$this->AvaTaxClient->withLicenseKey( $account_number, $license_key );
	}

	public static function get_instance() {
		if ( self::$instance == null ) {
			self::$instance = new PMProava_SDK_Wrapper();
		}
		return self::$instance;
	}

	/**
	 * Check whether the user's Avalara credentials are valid.
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
	 * @return object
	 */
	public function validate_address( $address ) {
		if ( empty( $address ) ) {
			global $pmproava_error;
			$pmproava_error = 'Error while validating address: No address was passed';
			return null;
		}

		$response = $this->AvaTaxClient->resolveAddress(
			isset( $address->line1 ) ? $address->line1 : '',
			isset( $address->line2 ) ? $address->line2 : '',
			isset( $address->line3 ) ? $address->line3 : '',
			isset( $address->city ) ? $address->city : '',
			isset( $address->region ) ? $address->region : '',
			isset( $address->postalCode ) ? $address->postalCode : '',
			isset( $address->country ) ? $address->country : '',
			'Mixed' // Text case.
		);

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

	/**
	 * Create a Avalara\TransactionMode object.
	 *
	 * @param float  $price to calculate tax for
	 * @param string $product_category being purchased
	 * @param string $product_address_model being purchased
	 * @param object $billing_address of buyer
	 * @param string $document_type of transaction
	 * @param string $customer_code of buyer
	 * @param string $transaction_code of invoice
	 * @param bool   $retroactive_tax if tax is included in $price
	 * @param bool   $commit transaction
	 * @param string $transaction_date of transaction, defaults to today
	 * @return Avalara\TransactionMode|null
	 */
	private function get_transaction_mode( $price, $product_category, $product_address_model, $billing_address = null, $document_type = Avalara\DocumentType::C_SALESORDER, $customer_code = '0', $transaction_code = '0', $retroactive_tax = false, $commit = false, $transaction_date = null ) {
		if ( ! $this->check_credentials() ) {
			global $pmproava_error;
			$pmproava_error = "Could not validate credentials in 'get_transaction_mode()'";
			return null;
		}

		// Make sure we have a valid company address.
		$pmproava_options = pmproava_get_options();
		$validated_company_address = $this->validate_address( $pmproava_options['company_address'] );
		if ( empty( $validated_company_address ) ) {
			// Invalid company address. Error would have been thrown in that function.
			return null;
		}

		// Create a transaction in Avalara.
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

		// Set addresses for transaction.
		switch ( $product_address_model ) {
			case 'singleLocation':
				$transaction_builder->withAddress(
					'SingleLocation',
					$validated_company_address->line1,
					$validated_company_address->line2,
					$validated_company_address->line3,
					$validated_company_address->city,
					$validated_company_address->region,
					$validated_company_address->postalCode,
					$validated_company_address->country
				);
				break;
			case 'shipToFrom':
				$validated_billing_address = $this->validate_address( $billing_address );
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
		}

		// Add product to transaction.
		$transaction_builder->withLine(
			$price,             // $amount
			1,                   // $quantity
			null,                // $itemCode
			$product_category    // $taxCode
		);


		// Make tax retroactive if needed.
		if ( $retroactive_tax ) {
			$transaction_builder->withLineTaxIncluded();
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
		unset( $this->transaction_cache[ $transaction_code ] );
		return $transaction_mode;
	}

	/**
	 * Calculate tax amount without creating a transaction in Avalara.
	 *
	 * @param float  $price to calculate tax for
	 * @param string $product_category being purchased
	 * @param string $product_address_model being purchased
	 * @param object $billing_address of buyer
	 * @param bool   $retroactive_tax if tax is included in $price
	 * @param string $transaction_date of transaction, defaults to today
	 * @return float|null
	 */
	public function calculate_tax( $price, $product_category, $product_address_model, $billing_address = null, $retroactive_tax = false, $transaction_date = null ) {
		$transaction_mode = $this->get_transaction_mode( $price, $product_category, $product_address_model, $billing_address, Avalara\DocumentType::C_SALESORDER, '0', '0', $retroactive_tax, $transaction_date );
		if ( empty( $transaction_mode ) ) {
			// Error would have been thrown in get_transaction_mode.
			return null;
		}
		return $transaction_mode->totalTax;
	}

	public function void_transaction( $transaction_code, $document_type = Avalara\DocumentType::C_SALESINVOICE ) {
		$pmproava_options = pmproava_get_options();
		$void_transaction_model = new Avalara\VoidTransactionModel();
		$void_transaction_model->code = Avalara\VoidReasonCode::C_DOCVOIDED;
		$transaction_model = $this->AvaTaxClient->voidTransaction( $pmproava_options['company_code'], $transaction_code, $document_type, null, $void_transaction_model );
	}

	public function lock_transaction( $transaction_code, $document_type = Avalara\DocumentType::C_SALESINVOICE ) {
		$pmproava_options = pmproava_get_options();

		if ( $pmproava_options['environment'] !== 'sandbox' ) {
			return;
		}

		$lock_transaction_model = new Avalara\LockTransactionModel();
		$lock_transaction_model->isLocked = true;
		$transaction_model = $this->AvaTaxClient->lockTransaction( $pmproava_options['company_code'], $transaction_code, $document_type, null, $lock_transaction_model );
	}

	/**
	 * Calculate tax amount without creating a transaction in Avalara.
	 *
	 * @param float  $price paid
	 * @param string $product_category purchased
	 * @param string $product_address_model purchased
	 * @param object $billing_address of buyer
	 * @param string $transaction_date of transaction, defaults to today
	 * @param bool   successful
	 */
	public function create_transaction( $price, $product_category, $product_address_model, $billing_address = null, $customer_code, $transaction_code, $commit = false, $transaction_date = null ) {
		$transaction_mode = $this->get_transaction_mode( $price, $product_category, $product_address_model, $billing_address, Avalara\DocumentType::C_SALESINVOICE, $customer_code, $transaction_code, true, $commit, $transaction_date );
		if ( empty( $transaction_mode ) ) {
			// Error would have been thrown in get_transaction_mode.
			return false;
		}
		return true;
	}

	public function get_transaction_by_code( $transaction_code, $document_type = Avalara\DocumentType::C_SALESINVOICE ) {
		if ( empty( $this->transaction_cache[$transaction_code] ) ) {
			$this->transaction_cache[$transaction_code] = array();
		}

		if ( empty( $this->transaction_cache[$transaction_code][$document_type] ) ) {
			$pmproava_options = pmproava_get_options();
			$response = $this->AvaTaxClient->getTransactionByCode( $pmproava_options['company_code'], $transaction_code, $document_type );
			if ( isset( $response->error ) ) {
				$this->transaction_cache[$transaction_code][$document_type] = null;
			}
			$this->transaction_cache[$transaction_code][$document_type] = $response;
		}
		return $this->transaction_cache[$transaction_code][$document_type];
	}
}