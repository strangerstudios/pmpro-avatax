<?php

/**
 * Add settings page.
 */
function pmproava_admin_add_page() {
	add_submenu_page( 'pmpro-dashboard', __( 'PMPro AvaTax', 'pmpro-avatax' ), __( 'PMPro AvaTax', 'pmpro-avatax' ), 'manage_options', 'pmpro-avatax', 'pmproava_admin_page' );
}
add_action( 'admin_menu', 'pmproava_admin_add_page' );

/**
 * Display settings page.
 */
function pmproava_admin_page() {
	?>
	<div class="wrap">
		<div id="icon-options-general" class="icon32"><br></div>
		<h2><?php _e( 'AvaTax Integration Options and Settings', 'pmpro-avatax' );?></h2>

		<?php if (!empty($msg)) { ?>
			<div class="message <?php echo $msgt; ?>"><p><?php echo $msg; ?></p></div>
		<?php } ?>

		<form action="options.php" method="post">
			<?php settings_fields('pmproava_options'); ?>
			<?php do_settings_sections('pmproava_options'); ?>
			<p><br/></p>
			<div class="bottom-buttons">
				<input type="hidden" name="pmproava_options[set]" value="1"/>
				<input type="submit" name="submit" class="button-primary" value="<?php esc_attr_e(__('Save Settings', 'pmpro-avatax')); ?>">
			</div>
		</form>
	</div>
	<?php
}

/**
 * Registers Avatax settings.
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
	add_settings_field('pmproava_option_company_address_line1', __('Line 1', 'pmpro-avatax'), 'pmproava_option_company_address_line1', 'pmproava_options', 'pmproava_section_company');
	add_settings_field('pmproava_option_company_address_line2', __('Line 2', 'pmpro-avatax'), 'pmproava_option_company_address_line2', 'pmproava_options', 'pmproava_section_company');
	add_settings_field('pmproava_option_company_address_line3', __('Line 3', 'pmpro-avatax'), 'pmproava_option_company_address_line3', 'pmproava_options', 'pmproava_section_company');
	add_settings_field('pmproava_option_company_address_city', __('City', 'pmpro-avatax'), 'pmproava_option_company_address_city', 'pmproava_options', 'pmproava_section_company');
	add_settings_field('pmproava_option_company_address_region', __('Region', 'pmpro-avatax'), 'pmproava_option_company_address_region', 'pmproava_options', 'pmproava_section_company');
	add_settings_field('pmproava_option_company_address_postalCode', __('Postal Code', 'pmpro-avatax'), 'pmproava_option_company_address_postalCode', 'pmproava_options', 'pmproava_section_company');
	add_settings_field('pmproava_option_company_address_country', __('Country', 'pmpro-avatax'), 'pmproava_option_company_address_country', 'pmproava_options', 'pmproava_section_company');

	add_settings_section('pmproava_section_settings', __('Settings', 'pmpro-avatax'), 'pmproava_section_settings', 'pmproava_options');
	add_settings_field('pmproava_option_record_documents', __('Record Documents in AvaTax', 'pmpro-avatax'), 'pmproava_option_record_documents', 'pmproava_options', 'pmproava_section_settings');
	add_settings_field('pmproava_option_vat_field', __('Show VAT Field at Checkout', 'pmpro-avatax'), 'pmproava_option_vat_field', 'pmproava_options', 'pmproava_section_settings');
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

	// Check if avatax credentials are valid. If not, show warning.
	$pmpro_avatax = PMPro_Avatax::get_instance();
	if ( ! $pmpro_avatax->check_credentials() ) {
		?>
		<div class="notice notice-error">
			<p><strong><?php esc_html_e( 'Invalid avatax credentials.', 'pmpro-avatax' ); ?></strong></p>
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
	echo "<input id='pmproava_account_number' name='pmproava_options[account_number]' size='80' type='text' value='" . esc_attr( $account_number ) . "' />";
}

/**
 * Show license key field.
 */
function pmproava_option_license_key() {
	$options = pmproava_get_options();
	$license_key = $options['license_key'];
	echo "<input id='pmproava_license_key' name='pmproava_options[license_key]' size='80' type='text' value='" . esc_attr( $license_key ) . "' />";
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
			<?php _e( 'Sandbox', 'pmpro-avatax' ); ?>
		</option>
		<option value="production" <?php selected( $environment, 'production' ); ?>>
			<?php _e( 'Production', 'pmpro-avatax' ); ?>
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
	if ( null === $pmpro_avatax->validate_address( $options['company_address'] ) ) {
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
	echo "<input id='pmproava_company_code' name='pmproava_options[company_code]' size='80' type='text' value='" . esc_attr( $company_code ) . "' />";
}

/**
 * Show company address line1 field.
 */
function pmproava_option_company_address_line1() {
	$options = pmproava_get_options();
	$company_address_line1 = $options['company_address']->line1;
	echo "<input id='pmproava_company_address_line1' name='pmproava_options[company_address][line1]' size='80' type='text' value='" . esc_attr( $company_address_line1 ) . "' />";
}

/**
 * Show company address line2 field.
 */
function pmproava_option_company_address_line2() {
	$options = pmproava_get_options();
	$company_address_line2 = $options['company_address']->line2;
	echo "<input id='pmproava_company_address_line2' name='pmproava_options[company_address][line2]' size='80' type='text' value='" . esc_attr( $company_address_line2 ) . "' />";
}

/**
 * Show company address line1 field.
 */
function pmproava_option_company_address_line3() {
	$options = pmproava_get_options();
	$company_address_line3 = $options['company_address']->line3;
	echo "<input id='pmproava_company_address_line3' name='pmproava_options[company_address][line3]' size='80' type='text' value='" . esc_attr( $company_address_line3 ) . "' />";
}

/**
 * Show company address city field.
 */
function pmproava_option_company_address_city() {
	$options = pmproava_get_options();
	$company_address_city= $options['company_address']->city;
	echo "<input id='pmproava_company_address_city' name='pmproava_options[company_address][city]' size='80' type='text' value='" . esc_attr( $company_address_city ) . "' />";
}

/**
 * Show company address region field.
 */
function pmproava_option_company_address_region() {
	$options = pmproava_get_options();
	$company_address_region = $options['company_address']->region;
	echo "<input id='pmproava_company_address_region' name='pmproava_options[company_address][region]' size='80' type='text' value='" . esc_attr( $company_address_region ) . "' />";
}

/**
 * Show company address line1 field.
 */
function pmproava_option_company_address_postalCode() {
	$options = pmproava_get_options();
	$company_address_postalCode = $options['company_address']->postalCode;
	echo "<input id='pmproava_company_address_postalCode' name='pmproava_options[company_address][postalCode]' size='80' type='text' value='" . esc_attr( $company_address_postalCode ) . "' />";
}

/**
 * Show company address line1 field.
 */
function pmproava_option_company_address_country() {
	$options = pmproava_get_options();
	$company_address_country = $options['company_address']->country;
	echo "<input id='pmproava_company_address_country' name='pmproava_options[company_address][country]' size='80' type='text' value='" . esc_attr( $company_address_country ) . "' />";
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
			<p><strong><?php esc_html_e( 'Setting the "Site Prefix" field is highly reccomended.', 'pmpro-avatax' ); ?></strong></p>
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
			<?php _e( 'Yes, send transactions to AvaTax', 'pmpro-avatax' ); ?>
		</option>
		<option value="no" <?php selected( $record_documents, 'no' ); ?>>
			<?php _e( 'No, do not send transactions to AvaTax', 'pmpro-avatax' ); ?>
		</option>
	</select>
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
			<?php _e( 'Yes', 'pmpro-avatax' ); ?>
		</option>
		<option value="no" <?php selected( $vat_field, 'no' ); ?>>
			<?php _e( 'No', 'pmpro-avatax' ); ?>
		</option>
	</select>
	<p class="description"><?php _e( 'If your country has a VAT tax, enable this feature to collect VAT numbers at checkout when using the "Variable Location" address model.', 'pmpro-avatax' );?></p>
	<?php
}

/**
 * Show "Site Prefix" field.
 */
function pmproava_option_site_prefix() {
	$options = pmproava_get_options();
	$site_prefix = $options['site_prefix'];
	echo "<input id='pmproava_site_prefix' name='pmproava_options[site_prefix]' size='80' type='text' value='" . esc_attr( $site_prefix ) . "' />";
	echo '<p class="description">' . esc_html__( 'Prefix for customer codes and invoice codes in Avatax.', 'pmpro-avatax' ) . '</p>';
}
