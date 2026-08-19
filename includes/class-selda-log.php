<?php
/**
 * Delivery log.
 *
 * Without this, an integration that quietly stops working looks exactly
 * like a website that stopped getting enquiries. The log is the difference
 * between "nobody is contacting us" and "something is broken".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Selda_Log {

	const OPTION = 'selda_log';
	const LIMIT  = 200;

	public static function init() {}

	public static function install() {}

	/**
	 * @param string $status sent | error | skipped
	 * @param string $who    email or name, for recognising the row
	 * @param string $detail what happened, shown as-is to the admin
	 * @param string $type   event type
	 */
	public static function add( $status, $who, $detail, $type = '' ) {
		$rows = get_option( self::OPTION, array() );
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}
		array_unshift( $rows, array(
			'time'   => time(),
			'status' => $status,
			'who'    => (string) $who,
			'detail' => wp_strip_all_tags( (string) $detail ),
			'type'   => (string) $type,
		) );
		update_option( self::OPTION, array_slice( $rows, 0, self::LIMIT ), false );
	}

	public static function recent( $limit = 25 ) {
		$rows = get_option( self::OPTION, array() );
		return is_array( $rows ) ? array_slice( $rows, 0, $limit ) : array();
	}

	public static function clear() {
		delete_option( self::OPTION );
	}

	/** How many failed since the last success. Drives the admin warning. */
	public static function failing() {
		$count = 0;
		foreach ( self::recent( 20 ) as $row ) {
			if ( 'sent' === $row['status'] ) {
				break;
			}
			if ( 'error' === $row['status'] ) {
				$count++;
			}
		}
		return $count;
	}
}
