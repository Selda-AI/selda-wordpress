<?php
/**
 * Capture from form plugins that are already installed.
 *
 * Most sites already have a contact form and nobody wants to rebuild it.
 * These hooks read submissions as they happen and pass them on, without
 * touching the form or how it behaves.
 *
 * Field names vary by site, so the mapping is done by guessing from the
 * field label and the value: an address that looks like an email is the
 * email, and so on. It is not clever, but it is right often enough and it
 * never blocks the original form.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Selda_Capture {

	public static function init() {
		if ( ! get_option( 'selda_capture_all', 1 ) ) {
			return;
		}

		add_action( 'wpcf7_mail_sent', array( __CLASS__, 'cf7' ) );
		add_action( 'wpforms_process_complete', array( __CLASS__, 'wpforms' ), 10, 4 );
		add_action( 'gform_after_submission', array( __CLASS__, 'gravity' ), 10, 2 );
		add_action( 'elementor_pro/forms/new_record', array( __CLASS__, 'elementor' ), 10, 2 );
	}

	/** Which supported plugins are active, for the settings screen. */
	public static function detected_text() {
		$found = array();
		if ( class_exists( 'WPCF7' ) ) { $found[] = 'Contact Form 7'; }
		if ( class_exists( 'WPForms' ) || function_exists( 'wpforms' ) ) { $found[] = 'WPForms'; }
		if ( class_exists( 'GFForms' ) ) { $found[] = 'Gravity Forms'; }
		if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) { $found[] = 'Elementor Pro'; }

		if ( empty( $found ) ) {
			return __( 'No supported form plugin found. You can still use the [selda_form] shortcode.', 'selda' );
		}
		return sprintf(
			/* translators: %s: comma separated plugin names */
			__( 'Found: %s.', 'selda' ),
			implode( ', ', $found )
		);
	}

	/**
	 * Turn an arbitrary label/value list into a lead.
	 *
	 * @param array  $pairs  label => value
	 * @param string $source what produced it, for the log and the note
	 */
	private static function send( $pairs, $source ) {
		if ( ! Selda_API::is_connected() ) {
			return;
		}

		$lead = array( 'email' => '', 'first_name' => '', 'last_name' => '', 'phone' => '', 'company' => '' );
		$note = '';

		foreach ( $pairs as $label => $value ) {
			if ( is_array( $value ) ) {
				$value = implode( ', ', array_map( 'strval', $value ) );
			}
			$value = trim( wp_strip_all_tags( (string) $value ) );
			if ( '' === $value ) {
				continue;
			}

			$key = strtolower( (string) $label );
			$note .= $label . ': ' . $value . "\n";

			if ( '' === $lead['email'] && is_email( $value ) ) {
				$lead['email'] = sanitize_email( $value );
				continue;
			}
			if ( '' === $lead['phone'] && preg_match( '/^[\+\d][\d\s\-\(\)]{5,}$/', $value ) ) {
				$lead['phone'] = $value;
				continue;
			}
			if ( '' === $lead['first_name'] && preg_match( '/name|nimi|namn|nombre|nom/i', $key ) ) {
				$parts = preg_split( '/\s+/', $value, 2 );
				$lead['first_name'] = $parts[0];
				$lead['last_name']  = isset( $parts[1] ) ? $parts[1] : '';
				continue;
			}
			if ( '' === $lead['company'] && preg_match( '/company|yritys|firma|organisation|organization/i', $key ) ) {
				$lead['company'] = $value;
			}
		}

		if ( '' === $lead['email'] && '' === $lead['first_name'] ) {
			Selda_Log::add( 'skipped', $source, __( 'No email address or name in the submission.', 'selda' ), 'form_submitted' );
			return;
		}

		$lead['type']     = 'form_submitted';
		$lead['tags']     = array( $source );
		$lead['analysis'] = sprintf(
			/* translators: 1: form plugin name, 2: page url */
			__( 'Submitted a form (%1$s) on %2$s.', 'selda' ),
			$source,
			isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : home_url()
		) . "\n\n" . $note;
		$lead['fields']   = $pairs;

		Selda_API::send_lead( $lead, 'capture' );
	}

	public static function cf7( $contact_form ) {
		$submission = class_exists( 'WPCF7_Submission' ) ? WPCF7_Submission::get_instance() : null;
		if ( ! $submission ) {
			return;
		}
		self::send( (array) $submission->get_posted_data(), 'Contact Form 7' );
	}

	public static function wpforms( $fields, $entry, $form_data, $entry_id ) {
		$pairs = array();
		foreach ( (array) $fields as $field ) {
			if ( isset( $field['name'] ) ) {
				$pairs[ $field['name'] ] = isset( $field['value'] ) ? $field['value'] : '';
			}
		}
		self::send( $pairs, 'WPForms' );
	}

	public static function gravity( $entry, $form ) {
		$pairs = array();
		foreach ( (array) $form['fields'] as $field ) {
			$id    = $field->id;
			$label = $field->label;
			if ( isset( $entry[ $id ] ) ) {
				$pairs[ $label ] = $entry[ $id ];
			}
		}
		self::send( $pairs, 'Gravity Forms' );
	}

	public static function elementor( $record, $handler ) {
		$pairs = array();
		foreach ( (array) $record->get( 'fields' ) as $field ) {
			$label = ! empty( $field['title'] ) ? $field['title'] : ( $field['id'] ?? '' );
			$pairs[ $label ] = isset( $field['value'] ) ? $field['value'] : '';
		}
		self::send( $pairs, 'Elementor' );
	}
}
