<?php
/**
 * Selda API client.
 *
 * Selda speaks JSON-RPC over plain HTTPS. That matters more than it sounds:
 * many hosts disable PHP's mail() or block outbound SMTP, which is exactly
 * why site enquiries go missing. HTTPS is open everywhere, so this path
 * works where email does not.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Selda_API {

	const OPT_KEY      = 'selda_api_key';
	const OPT_ENDPOINT = 'selda_endpoint';
	const OPT_PROJECT  = 'selda_project_id';
	const OPT_RUN      = 'selda_run_id';

	public static function key() {
		return trim( (string) get_option( self::OPT_KEY, '' ) );
	}

	public static function endpoint() {
		$url = trim( (string) get_option( self::OPT_ENDPOINT, '' ) );
		return $url ? $url : SELDA_DEFAULT_ENDPOINT;
	}

	public static function project() {
		return trim( (string) get_option( self::OPT_PROJECT, '' ) );
	}

	public static function run() {
		return trim( (string) get_option( self::OPT_RUN, '' ) );
	}

	public static function is_connected() {
		return '' !== self::key() && '' !== self::project();
	}

	/**
	 * Call a Selda tool.
	 *
	 * @param string $tool      Tool name, e.g. selda_ingest_event.
	 * @param array  $arguments Tool arguments.
	 * @param string $key       Optional key override, used when testing a key
	 *                          before it has been saved.
	 * @return array|WP_Error Decoded result, or WP_Error with a readable message.
	 */
	public static function call( $tool, $arguments = array(), $key = null ) {
		$key = null === $key ? self::key() : trim( $key );
		if ( '' === $key ) {
			return new WP_Error( 'selda_no_key', __( 'No API key has been saved yet.', 'selda' ) );
		}

		$response = wp_remote_post( self::endpoint(), array(
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Bearer ' . $key,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json, text/event-stream',
				'User-Agent'    => 'Selda-WordPress/' . SELDA_VERSION . '; ' . home_url(),
			),
			'body' => wp_json_encode( array(
				'jsonrpc' => '2.0',
				'id'      => 1,
				'method'  => 'tools/call',
				'params'  => array(
					'name'      => $tool,
					'arguments' => empty( $arguments ) ? new stdClass() : $arguments,
				),
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'selda_http', $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$json = json_decode( $body, true );

		if ( 200 !== $code ) {
			return new WP_Error( 'selda_http_' . $code, sprintf(
				/* translators: 1: HTTP status code, 2: response body */
				__( 'Selda answered with %1$d: %2$s', 'selda' ), $code, wp_strip_all_tags( substr( $body, 0, 200 ) )
			) );
		}
		if ( ! empty( $json['error']['message'] ) ) {
			return new WP_Error( 'selda_rpc', $json['error']['message'] );
		}

		$text = isset( $json['result']['content'][0]['text'] ) ? $json['result']['content'][0]['text'] : '';

		if ( ! empty( $json['result']['isError'] ) ) {
			return new WP_Error( 'selda_tool', $text ? $text : __( 'Selda rejected the request.', 'selda' ) );
		}

		$decoded = json_decode( $text, true );

		/* A tool may answer with plain text rather than JSON. Both are valid. */
		return null === $decoded ? array( 'text' => $text ) : $decoded;
	}

	/** Projects the saved key can see. Used to fill the project selector. */
	public static function projects( $key = null ) {
		$result = self::call( 'selda_list_projects', array(), $key );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return is_array( $result ) ? $result : array();
	}

	/** Campaigns in the chosen project, so a form can target one. */
	public static function campaigns() {
		if ( ! self::is_connected() ) {
			return array();
		}
		$result = self::call( 'selda_list_campaigns', array( 'projectId' => self::project() ) );
		if ( is_wp_error( $result ) ) {
			return array();
		}
		if ( isset( $result['campaigns'] ) && is_array( $result['campaigns'] ) ) {
			return $result['campaigns'];
		}
		return is_array( $result ) ? $result : array();
	}

	/**
	 * Send a lead.
	 *
	 * Always ingest_event rather than add_lead: an enquiry ARRIVED, it was
	 * not found by a search. Selda then recognises a returning person
	 * instead of creating a second record for them.
	 *
	 * Nothing here sends a message to anyone. Drafts wait for a human in
	 * the Selda app.
	 */
	public static function send_lead( $args, $context = 'api' ) {
		if ( ! self::is_connected() ) {
			return new WP_Error( 'selda_not_connected', __( 'Selda is not connected yet.', 'selda' ) );
		}

		/**
		 * Adjust or stop a lead before it leaves the site.
		 *
		 * Return an empty value to send nothing, for example to skip
		 * submissions from a particular form or from logged-in staff.
		 *
		 * @param array  $args    Lead fields.
		 * @param string $context Where it came from: form, capture, api, test.
		 */
		$args = apply_filters( 'selda_lead_args', $args, $context );
		if ( empty( $args ) || ! is_array( $args ) ) {
			Selda_Log::add( 'skipped', '', __( 'Stopped by a selda_lead_args filter.', 'selda' ), $context );
			return new WP_Error( 'selda_filtered', __( 'The lead was stopped by a filter.', 'selda' ) );
		}

		$identity = array_filter( array(
			'email'       => isset( $args['email'] ) ? sanitize_email( $args['email'] ) : '',
			'firstName'   => isset( $args['first_name'] ) ? $args['first_name'] : '',
			'lastName'    => isset( $args['last_name'] ) ? $args['last_name'] : '',
			'company'     => isset( $args['company'] ) ? $args['company'] : '',
			'phone'       => isset( $args['phone'] ) ? $args['phone'] : '',
			'jobTitle'    => isset( $args['job_title'] ) ? $args['job_title'] : '',
			'linkedinUrl' => isset( $args['linkedin'] ) ? $args['linkedin'] : '',
		) );

		if ( empty( $identity['email'] ) && empty( $identity['linkedinUrl'] ) && empty( $identity['firstName'] ) ) {
			return new WP_Error( 'selda_no_identity', __( 'A lead needs at least an email address or a name.', 'selda' ) );
		}

		$payload = array(
			'projectId'      => self::project(),
			'type'           => ! empty( $args['type'] ) ? $args['type'] : 'form_submitted',
			'source'         => wp_parse_url( home_url(), PHP_URL_HOST ),
			'identity'       => $identity,
			'idempotencyKey' => ! empty( $args['idempotency_key'] )
				? $args['idempotency_key']
				: substr( md5( wp_json_encode( $identity ) . gmdate( 'Y-m-d-H' ) ), 0, 32 ),
		);

		if ( ! empty( $args['analysis'] ) ) {
			$payload['analysis'] = $args['analysis'];
		}
		if ( ! empty( $args['tags'] ) ) {
			$payload['tags'] = array_values( (array) $args['tags'] );
		}
		if ( ! empty( $args['fields'] ) ) {
			$payload['payload'] = $args['fields'];
		}

		/* A form can target its own campaign; otherwise the site default. */
		$run = ! empty( $args['run_id'] ) ? $args['run_id'] : self::run();
		if ( '' !== $run ) {
			$payload['attachToRunId'] = $run;
		}

		$result = self::call( 'selda_ingest_event', $payload );

		Selda_Log::add(
			is_wp_error( $result ) ? 'error' : 'sent',
			isset( $identity['email'] ) ? $identity['email'] : ( $identity['firstName'] ?? '' ),
			is_wp_error( $result ) ? $result->get_error_message() : ( $result['message'] ?? 'ok' ),
			$payload['type']
		);

		return $result;
	}
}
