<?php
/**
 * Notifications out of Selda.
 *
 * Everything else in this plugin pushes work into Selda. This is the one
 * piece that comes back: Selda calls the site when something happens to a
 * lead, and the site passes it on to Slack.
 *
 * Without this the work still gets done, but nobody knows when to look. A
 * draft waiting in an inbox that nobody opens is the same as no draft.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Selda_Notify {

	const OPT_SLACK  = 'selda_slack_url';
	const OPT_TOKEN  = 'selda_hook_token';
	const OPT_HOOKID = 'selda_hook_id';
	const OPT_SECRET = 'selda_hook_secret';

	/** Events worth interrupting someone for. */
	const EVENTS = array(
		'draft.ready',
		'draft.failed',
		'reply.received',
		'meeting.booked',
		'lead.added',
	);

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'route' ) );
	}

	public static function route() {
		register_rest_route( 'selda/v1', '/event', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'receive' ),
			'permission_callback' => '__return_true', /* Guarded by the token in the URL. */
		) );
	}

	/** The address Selda posts to. The token is what makes it ours. */
	public static function endpoint() {
		return add_query_arg( 'token', self::token(), rest_url( 'selda/v1/event' ) );
	}

	public static function token() {
		$token = get_option( self::OPT_TOKEN, '' );
		if ( '' === $token ) {
			$token = wp_generate_password( 32, false );
			update_option( self::OPT_TOKEN, $token );
		}
		return $token;
	}

	public static function slack() {
		return trim( (string) get_option( self::OPT_SLACK, '' ) );
	}

	public static function receive( WP_REST_Request $request ) {
		if ( ! hash_equals( self::token(), (string) $request->get_param( 'token' ) ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 403 );
		}

		$raw = $request->get_body();
		if ( ! self::signed( $request, $raw ) ) {
			Selda_Log::add( 'error', 'Selda', __( 'A notification arrived with a signature that did not match. Ignored.', 'selda' ), 'webhook' );
			return new WP_REST_Response( array( 'ok' => false ), 401 );
		}

		$body  = (array) $request->get_json_params();
		$event = isset( $body['event'] ) ? (string) $body['event'] : ( $body['type'] ?? 'event' );
		$data  = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : $body;

		Selda_Log::add( 'received', self::who( $data ), $event, 'webhook' );

		/* Anyone can hang their own handling off this. */
		do_action( 'selda_event_received', $event, $data, $body );

		$url = self::slack();
		if ( '' !== $url ) {
			wp_remote_post( $url, array(
				'timeout'  => 8,
				'blocking' => false,
				'headers'  => array( 'Content-Type' => 'application/json' ),
				'body'     => wp_json_encode( array( 'text' => self::wording( $event, $data ) ) ),
			) );
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Check that Selda really sent this.
	 *
	 * The token in the address keeps out anyone who is guessing, but it
	 * travels in a URL and URLs end up in logs. The signature is what
	 * proves the body was not written by someone else. When Selda gave us
	 * no secret there is nothing to check, and the token stands alone.
	 */
	private static function signed( WP_REST_Request $request, $raw ) {
		$secret = trim( (string) get_option( self::OPT_SECRET, '' ) );
		if ( '' === $secret ) {
			return true;
		}

		$sent = '';
		foreach ( array( 'x-selda-signature', 'x-webhook-signature', 'x-signature' ) as $header ) {
			$value = $request->get_header( $header );
			if ( $value ) {
				$sent = trim( $value );
				break;
			}
		}
		if ( '' === $sent ) {
			return false;
		}

		/* Some senders prefix the algorithm, as in sha256=abc123. */
		if ( false !== strpos( $sent, '=' ) ) {
			$parts = explode( '=', $sent, 2 );
			$sent  = $parts[1];
		}

		return hash_equals( hash_hmac( 'sha256', $raw, $secret ), $sent );
	}

	/**
	 * Turn an event into a sentence a person can act on.
	 *
	 * The name matters more than the payload: whoever reads this on a phone
	 * needs to know in one line whether to open Selda now or after lunch.
	 */
	private static function who( $data ) {
		$who = trim( ( $data['firstName'] ?? '' ) . ' ' . ( $data['lastName'] ?? '' ) );
		if ( '' === $who ) {
			$who = $data['email'] ?? __( 'a contact', 'selda' );
		}
		return $who;
	}

	private static function wording( $event, $data ) {
		$who  = self::who( $data );
		$firm = ! empty( $data['company'] ) ? ' (' . $data['company'] . ')' : '';
		$link = ! empty( $data['url'] ) ? "\n" . $data['url'] : "\n" . 'https://app.selda.ai';

		switch ( $event ) {
			case 'draft.ready':
				$line = sprintf( __( 'A reply to %s is written and waiting for your approval.', 'selda' ), $who . $firm );
				break;
			case 'draft.failed':
				$line = sprintf( __( 'Selda could not write a reply to %1$s. %2$s', 'selda' ), $who . $firm, $data['reason'] ?? '' );
				break;
			case 'reply.received':
				$line = sprintf( __( '%s replied.', 'selda' ), $who . $firm );
				break;
			case 'meeting.booked':
				$line = sprintf( __( '%s booked a meeting.', 'selda' ), $who . $firm );
				break;
			case 'lead.added':
				$line = sprintf( __( 'New enquiry from %s.', 'selda' ), $who . $firm );
				break;
			default:
				$line = sprintf( __( 'Selda: %s', 'selda' ), $event );
		}

		return $line . $link;
	}

	/**
	 * Tell Selda where to call. Replaces any endpoint we registered before,
	 * so pressing the button twice does not double every notification.
	 */
	public static function register() {
		$old = get_option( self::OPT_HOOKID, '' );
		if ( '' !== $old ) {
			Selda_API::mutate( 'webhooks.delete', array( 'webhookId' => $old ) );
			delete_option( self::OPT_HOOKID );
		}

		/* Webhooks belong to the organisation, not to one project. */
		$result = Selda_API::mutate( 'webhooks.create', array(
			'url'         => self::endpoint(),
			'events'      => self::EVENTS,
			'description' => sprintf( 'WordPress: %s', wp_parse_url( home_url(), PHP_URL_HOST ) ),
		) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! empty( $result['webhookId'] ) ) {
			update_option( self::OPT_HOOKID, $result['webhookId'] );
		} elseif ( ! empty( $result['id'] ) ) {
			update_option( self::OPT_HOOKID, $result['id'] );
		}
		if ( ! empty( $result['secret'] ) ) {
			update_option( self::OPT_SECRET, $result['secret'] );
		}
		return $result;
	}

	public static function unregister() {
		$old = get_option( self::OPT_HOOKID, '' );
		if ( '' !== $old ) {
			Selda_API::mutate( 'webhooks.delete', array( 'webhookId' => $old ) );
			delete_option( self::OPT_HOOKID );
			delete_option( self::OPT_SECRET );
		}
	}
}
