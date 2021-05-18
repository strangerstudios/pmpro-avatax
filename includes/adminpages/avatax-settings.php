<?php

/**
 * Add settings page.
 */
function pmproava_admin_add_page() {
	add_submenu_page( 'pmpro-dashboard', __( 'AvaTax Integration Settings', 'pmpro-avatax' ), __( 'AvaTax', 'pmpro-avatax' ), 'manage_options', 'pmpro-avatax', 'pmproava_admin_page' );
}
add_action( 'admin_menu', 'pmproava_admin_add_page' );

/**
 * Display settings page.
 */
function pmproava_admin_page() {
	/**
	 * Load the Paid Memberships Pro admin page header.
	 *
	 */
	require_once( PMPRO_DIR . '/adminpages/admin_header.php' );
	?>
	<h1><?php esc_html_e( 'AvaTax Integration Options and Settings', 'pmpro-avatax' );?></h1>
	<hr />
	<form action="options.php" method="post">
		<?php settings_fields('pmproava_options'); ?>
		<?php do_settings_sections('pmproava_options'); ?>
		<p class="submit">
			<input type="hidden" name="pmproava_options[set]" value="1"/>
			<input type="submit" name="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'pmpro-avatax' ); ?>">
		</p>
	</form>
	<?php
}

/**
 * Registers AvaTax settings.
 */
function pmproava_admin_init() {
	//setup settings
	register_setting('pmproava_options', 'pmproava_options', 'pmproava_options_validate');
	add_settings_section('pmproava_section_credentials', __('Credentials', 'pmpro-avatax'), 'pmproava_section_credentials', 'pmproava_options');
	add_settings_field('pmproava_option_account_number', __('Account Number', 'pmpro-avatax'), 'pmproava_option_account_number', 'pmproava_options', 'pmproava_section_credentials');
	add_settings_field('pmproava_option_license_key', __('Licence Key', 'pmpro-avatax'), 'pmproava_option_license_key', 'pmproava_options', 'pmproava_section_credentials');
	add_settings_field('pmproava_option_environment', __('Environment', 'pmpro-avatax'), 'pmproava_option_environment', 'pmproava_options', 'pmproava_section_credentials');

	add_settings_section('pmproava_section_company', __('Company', 'pmpro-avatax'), 'pmproava_section_company', 'pmproava_options');
	add_settings_field('pmproava_option_company_code', __('Code', 'pmpro-avatax'), 'pmproava_option_company_code', 'pmproava_options', 'pmproava_section_company');
	add_settings_field('pmproava_option_company_address_line1', __('Address Line 1', 'pmpro-avatax'), 'pmproava_option_company_address_line1', 'pmproava_options', 'pmproava_section_company');
	add_settings_field('pmproava_option_company_address_line2', __('Address Line 2', 'pmpro-avatax'), 'pmproava_option_company_address_line2', 'pmproava_options', 'pmproava_section_company');
	add_settings_field('pmproava_option_company_address_line3', __('Address Line 3', 'pmpro-avatax'), 'pmproava_option_company_address_line3', 'pmproava_options', 'pmproava_section_company');
	add_settings_field('pmproava_option_company_address_city', __('City', 'pmpro-avatax'), 'pmproava_option_company_address_city', 'pmproava_options', 'pmproava_section_company');
	add_settings_field('pmproava_option_company_address_region', __('Region', 'pmpro-avatax'), 'pmproava_option_company_address_region', 'pmproava_options', 'pmproava_section_company');
	add_settings_field('pmproava_option_company_address_postalCode', __('Postal Code', 'pmpro-avatax'), 'pmproava_option_company_address_postalCode', 'pmproava_options', 'pmproava_section_company');
	add_settings_field('pmproava_option_company_address_country', __('Country', 'pmpro-avatax'), 'pmproava_option_company_address_country', 'pmproava_options', 'pmproava_section_company');

	add_settings_section('pmproava_section_settings', __('Settings', 'pmpro-avatax'), 'pmproava_section_settings', 'pmproava_options');
	add_settings_field('pmproava_option_record_documents', __('Record Documents in AvaTax', 'pmpro-avatax'), 'pmproava_option_record_documents', 'pmproava_options', 'pmproava_section_settings');
	add_settings_field('pmproava_option_vat_field', __('Collect VAT Number at Checkout', 'pmpro-avatax'), 'pmproava_option_vat_field', 'pmproava_options', 'pmproava_section_settings');
	add_settings_field('pmproava_option_validate_address', __('Validate Address at Checkout', 'pmpro-avatax'), 'pmproava_option_validate_address', 'pmproava_options', 'pmproava_section_settings');
	add_settings_field('pmproava_option_site_prefix', __('Site Prefix', 'pmpro-avatax'), 'pmproava_option_site_prefix', 'pmproava_options', 'pmproava_section_settings');
}
add_action("admin_init", "pmproava_admin_init");

/**
 * Show warnings on PMProava settings page if credentials are not valid.
 */
function pmproava_section_credentials() {
	$options = pmproava_get_options();
	if ( empty( $options['account_number'] ) || empty( $options['license_key'] ) ) {
		// User has not yet filled out credentials.
		return;
	}

	// Check if AvaTax credentials are valid. If not, show warning.
	$pmpro_avatax = PMPro_Avatax::get_instance();
	if ( ! $pmpro_avatax->check_credentials() ) {
		?>
		<div class="notice notice-error">
			<p><strong><?php esc_html_e( 'Invalid AvaTax credentials.', 'pmpro-avatax' ); ?></strong></p>
		</div>
		<?php
	}
}

/**
 * Show account number field.
 */
function pmproava_option_account_number() {
	$options = pmproava_get_options();
	$account_number = $options['account_number'] ;
	echo "<input id='pmproava_account_number' name='pmproava_options[account_number]' class='regular-text' type='text' value='" . esc_attr( $account_number ) . "' />";
}

/**
 * Show license key field.
 */
function pmproava_option_license_key() {
	$options = pmproava_get_options();
	$license_key = $options['license_key'];
	echo "<input id='pmproava_license_key' name='pmproava_options[license_key]' class='regular-text' type='text' value='" . esc_attr( $license_key ) . "' />";
}

/**
 * Show environment field.
 */
function pmproava_option_environment() {
	$options = pmproava_get_options();
	$environment = $options['environment'];
	?>
	<select id="pmproava_environment" name="pmproava_options[environment]">
		<option value="sandbox" <?php selected( $environment, 'sandbox' ); ?>>
			<?php esc_html_e( 'Sandbox', 'pmpro-avatax' ); ?>
		</option>
		<option value="production" <?php selected( $environment, 'production' ); ?>>
			<?php esc_html_e( 'Production', 'pmpro-avatax' ); ?>
		</option>
	</select>
	<?php
}

/**
 * Show warnings on PMProava settings page if company fields are not valid.
 */
function pmproava_section_company() {
	$options = pmproava_get_options();
	if ( empty( $options['account_number'] ) || empty( $options['license_key'] ) ) {
		// User has not yet filled out credentials.
		return;
	}

	// Check if company address is valid. If not, show warning.
	$pmpro_avatax = PMPro_Avatax::get_instance();
	if ( null === $pmpro_avatax->validate_address( $options['company_address'], true ) ) {
		?>
		<div class="notice notice-error">
			<p><strong><?php esc_html_e( 'Invalid Company Address.', 'pmpro-avatax' ); ?></strong></p>
		</div>
		<?php
	}
}

/**
 * Show company code field.
 */
function pmproava_option_company_code() {
	$options = pmproava_get_options();
	$company_code = $options['company_code'];
	echo "<input id='pmproava_company_code' name='pmproava_options[company_code]' class='regular-text' type='text' value='" . esc_attr( $company_code ) . "' />";
	echo '<p class="description">' . esc_html( 'Create a unique code to identify the company you are connecting to AvaTax.', 'pmpro-avatax' ) . '</p>';
}

/**
 * Show company address line1 field.
 */
function pmproava_option_company_address_line1() {
	$options = pmproava_get_options();
	$company_address_line1 = $options['company_address']->line1;
	echo "<input id='pmproava_company_address_line1' name='pmproava_options[company_address][line1]' class='regular-text' type='text' value='" . esc_attr( $company_address_line1 ) . "' />";
}

/**
 * Show company address line2 field.
 */
function pmproava_option_company_address_line2() {
	$options = pmproava_get_options();
	$company_address_line2 = $options['company_address']->line2;
	echo "<input id='pmproava_company_address_line2' name='pmproava_options[company_address][line2]' class='regular-text' type='text' value='" . esc_attr( $company_address_line2 ) . "' />";
}

/**
 * Show company address line1 field.
 */
function pmproava_option_company_address_line3() {
	$options = pmproava_get_options();
	$company_address_line3 = $options['company_address']->line3;
	echo "<input id='pmproava_company_address_line3' name='pmproava_options[company_address][line3]' class='regular-text' type='text' value='" . esc_attr( $company_address_line3 ) . "' />";
}

/**
 * Show company address city field.
 */
function pmproava_option_company_address_city() {
	$options = pmproava_get_options();
	$company_address_city= $options['company_address']->city;
	echo "<input id='pmproava_company_address_city' name='pmproava_options[company_address][city]' class='regular-text' type='text' value='" . esc_attr( $company_address_city ) . "' />";
}

/**
 * Show company address region field.
 */
function pmproava_option_company_address_region() {
	$options = pmproava_get_options();
	$company_address_region = $options['company_address']->region;
	echo "<input id='pmproava_company_address_region' name='pmproava_options[company_address][region]' class='regular-text' type='text' value='" . esc_attr( $company_address_region ) . "' />";
}

/**
 * Show company address line1 field.
 */
function pmproava_option_company_address_postalCode() {
	$options = pmproava_get_options();
	$company_address_postalCode = $options['company_address']->postalCode;
	echo "<input id='pmproava_company_address_postalCode' name='pmproava_options[company_address][postalCode]' class='regular-text' type='text' value='" . esc_attr( $company_address_postalCode ) . "' />";
}

/**
 * Show company address line1 field.
 */
function pmproava_option_company_address_country() {
	$options = pmproava_get_options();
	$company_address_country = $options['company_address']->country;
	echo "<input id='pmproava_company_address_country' name='pmproava_options[company_address][country]' class='regular-text' type='text' value='" . esc_attr( $company_address_country ) . "' />";
}

/**
 * Show warnings on PMProava settings page if settings are not valid.
 */
function pmproava_section_settings() {
	$options = pmproava_get_options();
	if ( empty( $options['account_number'] ) || empty( $options['license_key'] ) ) {
		// User has not yet filled out credentials.
		return;
	}

	if ( empty( $options['site_prefix'] ) ) {
		?>
		<div class="notice notice-warning">
			<p><strong><?php esc_html_e( 'Setting the "Site Prefix" field is highly recommended.', 'pmpro-avatax' ); ?></strong></p>
		</div>
		<?php
	}
}

/**
 * Show "Record Documnets in AvaTax" field.
 */
function pmproava_option_record_documents() {
	$options = pmproava_get_options();
	$record_documents = $options['record_documents'];
	?>
	<select id="pmproava_record_documents" name="pmproava_options[record_documents]">
		<option value="yes" <?php selected( $record_documents, 'yes' ); ?>>
			<?php esc_html_e( 'Yes', 'pmpro-avatax' ); ?>
		</option>
		<option value="no" <?php selected( $record_documents, 'no' ); ?>>
			<?php esc_html_e( 'No', 'pmpro-avatax' ); ?>
		</option>
	</select>
	<p class="description"><?php esc_html_e( 'When set to "No", PMPro will not send new orders to AvaTax or update existing transactions.', 'pmpro-avatax' );?></p>
	<?php
}

/**
 * Show "Show VAT Field at Checkout" field.
 */
function pmproava_option_vat_field() {
	$options = pmproava_get_options();	
	$vat_field = $options['vat_field'];
	?>
	<select id="pmproava_vat_field" name="pmproava_options[vat_field]">
		<option value="yes" <?php selected( $vat_field, 'yes' ); ?>>
			<?php esc_html_e( 'Yes', 'pmpro-avatax' ); ?>
		</option>
		<option value="no" <?php selected( $vat_field, 'no' ); ?>>
			<?php esc_html_e( 'No', 'pmpro-avatax' ); ?>
		</option>
	</select>
	<p class="description"><?php esc_html_e( 'Set to "Yes" if you need to collect and calculate VAT.', 'pmpro-avatax' );?></p>
	<?php
}

/**
 * Show "Show VAT Field at Checkout" field.
 */
function pmproava_option_validate_address() {
	$options = pmproava_get_options();	
	$validate_address = $options['validate_address'];
	?>
	<select id="pmproava_validate_address" name="pmproava_options[validate_address]">
		<option value="yes" <?php selected( $validate_address, 'yes' ); ?>>
			<?php esc_html_e( 'Yes', 'pmpro-avatax' ); ?>
		</option>
		<option value="no" <?php selected( $validate_address, 'no' ); ?>>
			<?php esc_html_e( 'No', 'pmpro-avatax' ); ?>
		</option>
	</select>
	<p class="description"><?php esc_html_e( 'Only addresses in the United States and Canada will be validated at checkout.', 'pmpro-avatax' );?></p>
	<?php
}

/**
 * Show "Site Prefix" field.
 */
function pmproava_option_site_prefix() {
	$options = pmproava_get_options();
	$site_prefix = $options['site_prefix'];
	echo "<input id='pmproava_site_prefix' name='pmproava_options[site_prefix]' class='regular-text' type='text' value='" . esc_attr( $site_prefix ) . "' />";
	echo '<p class="description">' . esc_html__( 'Prefix for customer codes and invoice codes in AvaTax.', 'pmpro-avatax' ) . '</p>';
}
