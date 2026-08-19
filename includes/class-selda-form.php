<?php
/**
 * The [selda_form] shortcode.
 *
 * A form that needs no other plugin, sends nowhere except Selda, and can
 * target one campaign. That last part is the point: a guide download and a
 * quote request are different conversations and should not share a
 * follow-up rhythm.
 *
 * Submission goes over fetch so the page does not reload. Without
 * JavaScript it falls back to a normal POST, because a form that only
 * works with JavaScript is a form that sometimes does not work.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Selda_Form {

	const ACTION = 'selda_form';

	public static function init() {
		add_shortcode( 'selda_form', array( __CLASS__, 'render' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( __CLASS__, 'receive' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'receive' ) );
	}

	private static function labels() {
		return array(
			'name'    => __( 'Name', 'selda' ),
			'email'   => __( 'Email', 'selda' ),
			'phone'   => __( 'Phone', 'selda' ),
			'company' => __( 'Company', 'selda' ),
			'message' => __( 'Message', 'selda' ),
		);
	}

	public static function render( $atts ) {
		$a = shortcode_atts( array(
			'fields'   => 'name,email,message',
			'required' => 'email',
			'campaign' => '',
			'type'     => 'form_submitted',
			'tags'     => '',
			'button'   => __( 'Send', 'selda' ),
			'thanks'   => __( 'Thank you. We will be in touch.', 'selda' ),
			'class'    => '',
		), $atts, 'selda_form' );

		if ( ! Selda_API::is_connected() ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<p><em>' . esc_html__( 'Selda is not connected yet, so this form is hidden. Only administrators see this note.', 'selda' ) . '</em></p>';
			}
			return '';
		}

		$labels   = self::labels();
		$fields   = array_values( array_intersect( array_map( 'trim', explode( ',', $a['fields'] ) ), array_keys( $labels ) ) );
		$required = array_map( 'trim', explode( ',', $a['required'] ) );
		if ( empty( $fields ) ) {
			$fields = array( 'email' );
		}

		$id = 'selda-form-' . wp_rand( 1000, 9999 );

		ob_start();
		?>
<div class="selda-form-wrap <?php echo esc_attr( $a['class'] ); ?>" id="<?php echo esc_attr( $id ); ?>">
	<form class="selda-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
		<?php wp_nonce_field( self::ACTION, 'selda_nonce' ); ?>
		<input type="hidden" name="selda_campaign" value="<?php echo esc_attr( $a['campaign'] ); ?>">
		<input type="hidden" name="selda_type" value="<?php echo esc_attr( $a['type'] ); ?>">
		<input type="hidden" name="selda_tags" value="<?php echo esc_attr( $a['tags'] ); ?>">
		<input type="hidden" name="selda_page" value="<?php echo esc_attr( get_permalink() ); ?>">
		<input type="hidden" name="selda_bg" value="">
		<p class="selda-hp"><label><?php esc_html_e( 'Leave this empty', 'selda' ); ?>
			<input type="text" name="selda_hp" tabindex="-1" autocomplete="off"></label></p>

		<?php foreach ( $fields as $field ) :
			$is_required = in_array( $field, $required, true );
			$type = 'email' === $field ? 'email' : ( 'phone' === $field ? 'tel' : 'text' ); ?>
			<label class="selda-field">
				<span><?php echo esc_html( $labels[ $field ] ); ?><?php echo $is_required ? ' *' : ''; ?></span>
				<?php if ( 'message' === $field ) : ?>
					<textarea name="selda_<?php echo esc_attr( $field ); ?>" rows="4" <?php echo $is_required ? 'required' : ''; ?>></textarea>
				<?php else : ?>
					<input type="<?php echo esc_attr( $type ); ?>" name="selda_<?php echo esc_attr( $field ); ?>"
						<?php echo $is_required ? 'required' : ''; ?>
						autocomplete="<?php echo esc_attr( self::autocomplete( $field ) ); ?>">
				<?php endif; ?>
			</label>
		<?php endforeach; ?>

		<button type="submit" class="selda-submit"><?php echo esc_html( $a['button'] ); ?></button>
	</form>
	<div class="selda-thanks" hidden><?php echo esc_html( $a['thanks'] ); ?></div>
</div>
		<?php
		self::assets_once();
		return ob_get_clean();
	}

	private static function autocomplete( $field ) {
		$map = array( 'name' => 'name', 'email' => 'email', 'phone' => 'tel', 'company' => 'organization' );
		return isset( $map[ $field ] ) ? $map[ $field ] : 'on';
	}

	private static $assets_done = false;

	/**
	 * Deliberately minimal styling: the form should inherit the theme, not
	 * fight it. Only layout and the states the theme cannot know about.
	 */
	private static function assets_once() {
		if ( self::$assets_done ) {
			return;
		}
		self::$assets_done = true;
		?>
<style id="selda-form-css">
.selda-form{display:grid;gap:14px;max-width:520px}
.selda-field{display:grid;gap:6px;font-size:.95em}
.selda-field input,.selda-field textarea{width:100%;padding:11px 12px;font:inherit;
 border:1px solid currentColor;border-radius:4px;background:transparent;color:inherit;opacity:.95}
.selda-field input:focus,.selda-field textarea:focus{outline:2px solid currentColor;outline-offset:1px}
/* Outlined by default. A filled button needs to know the page background
   to stay legible, and a plugin cannot know that: on a dark theme a
   system colour like Canvas turns the label invisible. An outline
   inherits the text colour and is readable on any background. Themes
   and sites can restyle .selda-submit freely. */
.selda-submit{justify-self:start;padding:12px 26px;font:inherit;font-weight:700;cursor:pointer;
 border:2px solid currentColor;border-radius:4px;background:transparent;color:inherit;
 transition:opacity .15s,transform .15s}
.selda-submit:hover{opacity:.75;transform:translateY(-1px)}
.selda-submit:active{transform:translateY(1px)}
.selda-submit[disabled]{opacity:.5;cursor:default;transform:none}
.selda-hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
.selda-thanks[hidden]{display:none}
.selda-thanks{padding:16px 18px;border:1px solid currentColor;border-radius:4px;max-width:520px}
.selda-error{margin-top:10px;font-size:.9em}
</style>
<script id="selda-form-js">
(function(){
	document.addEventListener('submit', function(e){
		var form = e.target;
		if (!form.classList || !form.classList.contains('selda-form')) return;
		if (!window.fetch || !form.checkValidity()) return;
		e.preventDefault();

		var wrap   = form.closest('.selda-form-wrap');
		var thanks = wrap ? wrap.querySelector('.selda-thanks') : null;
		var button = form.querySelector('.selda-submit');
		var bg     = form.querySelector('[name="selda_bg"]');
		if (bg) bg.value = '1';
		if (button) button.disabled = true;

		/* A field named "action" shadows form.action, so read the attribute. */
		fetch(form.getAttribute('action'), {
			method: 'POST', body: new FormData(form),
			credentials: 'same-origin', headers: { 'Accept': 'application/json' }
		})
		.then(function(r){ return r.json(); })
		.then(function(d){
			if (d && d.ok) {
				form.hidden = true;
				form.style.display = 'none';
				if (thanks) { thanks.hidden = false; thanks.scrollIntoView({block:'nearest',behavior:'smooth'}); }
			} else {
				fail(d && d.message);
			}
		})
		.catch(function(){
			/* Network trouble: let the browser do the ordinary submit rather
			   than losing what the visitor typed. */
			if (bg) bg.value = '';
			form.submit();
		});

		function fail(msg){
			if (button) button.disabled = false;
			var p = form.querySelector('.selda-error');
			if (!p) { p = document.createElement('p'); p.className = 'selda-error'; form.appendChild(p); }
			p.textContent = msg || 'Something went wrong. Please try again.';
		}
	});
})();
</script>
		<?php
	}

	public static function receive() {
		$background = ! empty( $_POST['selda_bg'] );
		$back       = isset( $_POST['selda_page'] ) ? esc_url_raw( wp_unslash( $_POST['selda_page'] ) ) : home_url( '/' );

		$finish = function ( $ok, $message ) use ( $background, $back ) {
			if ( $background ) {
				wp_send_json( array( 'ok' => $ok, 'message' => $message ) );
			}
			wp_safe_redirect( add_query_arg( 'selda', $ok ? 'ok' : 'error', $back ) );
			exit;
		};

		/* Bots fill hidden fields. Show them success and send nothing. */
		if ( ! empty( $_POST['selda_hp'] ) ) {
			$finish( true, '' );
		}
		if ( ! isset( $_POST['selda_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['selda_nonce'] ), self::ACTION ) ) {
			$finish( false, __( 'The form expired. Please reload the page and try again.', 'selda' ) );
		}

		$get = function ( $key ) {
			return isset( $_POST[ 'selda_' . $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'selda_' . $key ] ) ) : '';
		};

		$email = isset( $_POST['selda_email'] ) ? sanitize_email( wp_unslash( $_POST['selda_email'] ) ) : '';
		$name  = $get( 'name' );
		$parts = preg_split( '/\s+/', trim( $name ), 2 );

		if ( '' === $email && '' === $name ) {
			$finish( false, __( 'Please add at least an email address.', 'selda' ) );
		}

		$message = isset( $_POST['selda_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['selda_message'] ) ) : '';

		$analysis = sprintf(
			/* translators: %s: page url */
			__( 'Submitted a form on %s.', 'selda' ),
			$back
		) . "\n";
		foreach ( array( 'name', 'email', 'phone', 'company' ) as $field ) {
			$value = 'email' === $field ? $email : $get( $field );
			if ( '' !== $value ) {
				$analysis .= ucfirst( $field ) . ': ' . $value . "\n";
			}
		}
		if ( '' !== $message ) {
			$analysis .= "\n" . $message . "\n";
		}

		$tags = array_filter( array_map( 'trim', explode( ',', $get( 'tags' ) ) ) );

		$result = Selda_API::send_lead( array(
			'email'      => $email,
			'first_name' => isset( $parts[0] ) ? $parts[0] : '',
			'last_name'  => isset( $parts[1] ) ? $parts[1] : '',
			'company'    => $get( 'company' ),
			'phone'      => $get( 'phone' ),
			'type'       => $get( 'type' ) ? $get( 'type' ) : 'form_submitted',
			'run_id'     => $get( 'campaign' ),
			'tags'       => $tags,
			'analysis'   => $analysis,
			'fields'     => array_filter( array(
				'name' => $name, 'email' => $email, 'phone' => $get( 'phone' ),
				'company' => $get( 'company' ), 'message' => $message, 'page' => $back,
			) ),
		), 'form' );

		if ( is_wp_error( $result ) ) {
			$finish( false, __( 'We could not record your message. Please try again or contact us directly.', 'selda' ) );
		}
		$finish( true, '' );
	}
}
