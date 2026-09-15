<?php
/**
 * Admin screen.
 *
 * The whole setup is three steps and no jargon: paste a key, pick a
 * project, send a test lead. Everything else is optional.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Selda_Settings {

	const SLUG = 'selda';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SELDA_FILE ), array( __CLASS__, 'action_link' ) );
	}

	public static function action_link( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">'
			. esc_html__( 'Settings', 'selda' ) . '</a>' );
		return $links;
	}

	public static function menu() {
		add_menu_page(
			__( 'Selda', 'selda' ),
			__( 'Selda', 'selda' ),
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'page' ),
			'dashicons-email-alt',
			58
		);
	}

	/** A quiet nudge only when something is actually wrong. */
	public static function notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && false !== strpos( $screen->id, self::SLUG ) ) {
			return;
		}
		if ( Selda_API::is_connected() && Selda_Log::failing() >= 3 ) {
			printf(
				'<div class="notice notice-error"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
				esc_html__( 'Selda:', 'selda' ),
				esc_html__( 'the last few enquiries did not reach Selda.', 'selda' ),
				esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ),
				esc_html__( 'See the log', 'selda' )
			);
		}
	}

	public static function handle() {
		if ( ! isset( $_POST['selda_action'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'selda_settings' );

		$action = sanitize_key( wp_unslash( $_POST['selda_action'] ) );
		$back   = admin_url( 'admin.php?page=' . self::SLUG );

		if ( 'connect' === $action ) {
			$key = isset( $_POST['selda_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['selda_key'] ) ) ) : '';
			$endpoint = isset( $_POST['selda_endpoint'] ) ? esc_url_raw( wp_unslash( $_POST['selda_endpoint'] ) ) : '';

			update_option( Selda_API::OPT_ENDPOINT, $endpoint );

			$projects = Selda_API::projects( $key );
			if ( is_wp_error( $projects ) ) {
				set_transient( 'selda_notice', array( 'error', $projects->get_error_message() ), 60 );
				wp_safe_redirect( $back ); exit;
			}
			if ( empty( $projects ) ) {
				set_transient( 'selda_notice', array( 'error', __( 'The key works, but it cannot see any projects.', 'selda' ) ), 60 );
				wp_safe_redirect( $back ); exit;
			}

			update_option( Selda_API::OPT_KEY, $key );
			/* One project is the common case, so choose it and skip a step. */
			if ( 1 === count( $projects ) && ! empty( $projects[0]['id'] ) ) {
				update_option( Selda_API::OPT_PROJECT, $projects[0]['id'] );
			}
			set_transient( 'selda_notice', array( 'success', __( 'Connected to Selda.', 'selda' ) ), 60 );
		}

		if ( 'save' === $action ) {
			update_option( Selda_API::OPT_PROJECT, isset( $_POST['selda_project'] ) ? sanitize_text_field( wp_unslash( $_POST['selda_project'] ) ) : '' );
			update_option( Selda_API::OPT_RUN, isset( $_POST['selda_run'] ) ? sanitize_text_field( wp_unslash( $_POST['selda_run'] ) ) : '' );
			update_option( 'selda_capture_all', ! empty( $_POST['selda_capture_all'] ) ? 1 : 0 );
			update_option( Selda_API::OPT_AUTO, ! empty( $_POST['selda_auto'] ) ? 1 : 0 );

			$slack = isset( $_POST['selda_slack'] ) ? esc_url_raw( wp_unslash( $_POST['selda_slack'] ) ) : '';
			$was   = Selda_Notify::slack();
			update_option( Selda_Notify::OPT_SLACK, $slack );

			/* Only bother Selda when the answer actually changed. */
			if ( '' !== $slack && $was !== $slack ) {
				$hook = Selda_Notify::register();
				if ( is_wp_error( $hook ) ) {
					set_transient( 'selda_notice', array( 'error', sprintf(
						/* translators: %s: error message */
						__( 'Saved, but Selda would not accept the notification address: %s', 'selda' ),
						$hook->get_error_message()
					) ), 60 );
					wp_safe_redirect( $back ); exit;
				}
			}
			if ( '' === $slack && '' !== $was ) {
				Selda_Notify::unregister();
			}
			set_transient( 'selda_notice', array( 'success', __( 'Saved.', 'selda' ) ), 60 );
		}

		if ( 'test' === $action ) {
			$user   = wp_get_current_user();
			$result = Selda_API::send_lead( array(
				'email'      => $user->user_email,
				'first_name' => $user->first_name ? $user->first_name : $user->display_name,
				'last_name'  => $user->last_name,
				'type'       => 'test_from_wordpress',
				'tags'       => array( 'test' ),
				'analysis'   => sprintf(
					/* translators: %s: site url */
					__( 'Test lead sent from the Selda plugin on %s. Nothing to follow up.', 'selda' ),
					home_url()
				),
				'idempotency_key' => 'selda-test-' . wp_generate_uuid4(),
			) );
			set_transient( 'selda_notice', is_wp_error( $result )
				? array( 'error', $result->get_error_message() )
				: array( 'success', __( 'Test lead sent. It should be in your Selda inbox now.', 'selda' ) ), 60 );
		}

		if ( 'disconnect' === $action ) {
			delete_option( Selda_API::OPT_KEY );
			delete_option( Selda_API::OPT_PROJECT );
			delete_option( Selda_API::OPT_RUN );
			set_transient( 'selda_notice', array( 'success', __( 'Disconnected. Nothing is sent to Selda any more.', 'selda' ) ), 60 );
		}

		if ( 'clear_log' === $action ) {
			Selda_Log::clear();
		}

		wp_safe_redirect( $back );
		exit;
	}

	public static function page() {
		$connected = Selda_API::is_connected();
		$projects  = '' !== Selda_API::key() ? Selda_API::projects() : array();
		$campaigns = $connected ? Selda_API::campaigns() : array();
		$notice    = get_transient( 'selda_notice' );
		delete_transient( 'selda_notice' );
		?>
<div class="wrap selda-wrap">
	<h1><?php esc_html_e( 'Selda', 'selda' ); ?></h1>
	<p class="selda-lede"><?php esc_html_e( 'Others sell you a tool. Selda builds your sales machine. Every enquiry from your site lands in Selda, where the reply is drafted for you and you press send.', 'selda' ); ?></p>

	<?php if ( $notice ) : ?>
		<div class="notice notice-<?php echo 'error' === $notice[0] ? 'error' : 'success'; ?>">
			<p><?php echo esc_html( $notice[1] ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! $connected ) : ?>

		<div class="selda-card">
			<h2><?php esc_html_e( 'Step 1 · Connect', 'selda' ); ?></h2>
			<p><?php
				printf(
					/* translators: %s: link to Selda */
					esc_html__( 'Create an API key in Selda under Settings, Connections, MCP server, then paste it here. It is on every plan, the free one included. %s', 'selda' ),
					'<a href="https://app.selda.ai" target="_blank" rel="noopener">' . esc_html__( 'Open Selda', 'selda' ) . '</a>'
				);
			?></p>
			<form method="post">
				<?php wp_nonce_field( 'selda_settings' ); ?>
				<input type="hidden" name="selda_action" value="connect">
				<p>
					<label for="selda_key"><strong><?php esc_html_e( 'API key', 'selda' ); ?></strong></label><br>
					<input type="password" id="selda_key" name="selda_key" class="regular-text code"
						placeholder="sk_..." autocomplete="off" required style="width:min(520px,100%)">
				</p>
				<details>
					<summary><?php esc_html_e( 'Advanced', 'selda' ); ?></summary>
					<p>
						<label for="selda_endpoint"><?php esc_html_e( 'Endpoint', 'selda' ); ?></label><br>
						<input type="url" id="selda_endpoint" name="selda_endpoint" class="regular-text code"
							value="<?php echo esc_attr( get_option( Selda_API::OPT_ENDPOINT, '' ) ); ?>"
							placeholder="<?php echo esc_attr( SELDA_DEFAULT_ENDPOINT ); ?>" style="width:min(520px,100%)">
						<span class="description"><?php esc_html_e( 'Leave empty unless you were told otherwise.', 'selda' ); ?></span>
					</p>
				</details>
				<p><button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Connect', 'selda' ); ?></button></p>
			</form>
		</div>

	<?php else : ?>

		<?php $mode = Selda_API::mode(); ?>
		<?php if ( 'test' === $mode ) : ?>
			<div class="notice notice-warning" style="margin:16px 0">
				<p>
					<strong><?php esc_html_e( 'Test key.', 'selda' ); ?></strong>
					<?php esc_html_e( 'Enquiries do arrive, but this key runs with limited access: the paid features, including having replies drafted for you, stay off. Swap in a production key to use them.', 'selda' ); ?>
				</p>
			</div>
		<?php endif; ?>

		<div class="selda-card selda-ok">
			<h2>
				<?php esc_html_e( 'Connected', 'selda' ); ?>
				<?php if ( 'test' === $mode ) : ?>
					<span style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;padding:3px 9px;border-radius:99px;border:1px solid #b8860b;color:#8a6100;vertical-align:middle;margin-left:6px"><?php esc_html_e( 'Sandbox', 'selda' ); ?></span>
				<?php elseif ( 'live' === $mode ) : ?>
					<span style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;padding:3px 9px;border-radius:99px;border:1px solid #1f7a3f;color:#1f7a3f;vertical-align:middle;margin-left:6px"><?php esc_html_e( 'Production', 'selda' ); ?></span>
				<?php endif; ?>
			</h2>
			<form method="post">
				<?php wp_nonce_field( 'selda_settings' ); ?>
				<input type="hidden" name="selda_action" value="save">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="selda_project"><?php esc_html_e( 'Project', 'selda' ); ?></label></th>
						<td>
							<select id="selda_project" name="selda_project">
								<?php foreach ( (array) $projects as $p ) :
									if ( empty( $p['id'] ) ) { continue; } ?>
									<option value="<?php echo esc_attr( $p['id'] ); ?>"
										<?php selected( Selda_API::project(), $p['id'] ); ?>>
										<?php echo esc_html( $p['name'] ?? $p['id'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Where leads from this site are stored.', 'selda' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="selda_run"><?php esc_html_e( 'Campaign', 'selda' ); ?></label></th>
						<td>
							<select id="selda_run" name="selda_run">
								<option value=""><?php esc_html_e( 'No campaign, just add the lead', 'selda' ); ?></option>
								<?php foreach ( (array) $campaigns as $c ) :
									if ( empty( $c['id'] ) ) { continue; } ?>
									<option value="<?php echo esc_attr( $c['id'] ); ?>"
										<?php selected( Selda_API::run(), $c['id'] ); ?>>
										<?php echo esc_html( $c['name'] ?? $c['id'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'A campaign gives the lead a tone of voice and a follow-up rhythm. The first message still waits for your approval.', 'selda' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Draft the reply', 'selda' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="selda_auto" value="1" <?php checked( Selda_API::auto_advance() ); ?>>
								<?php esc_html_e( 'Have Selda write a reply as soon as an enquiry arrives', 'selda' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Selda reads what you have told it about your business and leaves a draft in the Sales Inbox. Nothing is sent: a person still presses send.', 'selda' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Tell me in Slack', 'selda' ); ?></th>
						<td>
							<input type="url" name="selda_slack" class="regular-text code" style="width:min(520px,100%)"
								value="<?php echo esc_attr( Selda_Notify::slack() ); ?>"
								placeholder="https://hooks.slack.com/services/...">
							<p class="description"><?php esc_html_e( 'Paste a Slack incoming webhook and Selda will say when a reply is waiting, when someone answers, and when a meeting is booked. Leave empty for no notifications.', 'selda' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Existing forms', 'selda' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="selda_capture_all" value="1" <?php checked( get_option( 'selda_capture_all', 1 ) ); ?>>
								<?php esc_html_e( 'Also capture Contact Form 7, WPForms, Gravity Forms and Elementor submissions', 'selda' ); ?>
							</label>
							<p class="description"><?php echo esc_html( Selda_Capture::detected_text() ); ?></p>
						</td>
					</tr>
				</table>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'selda' ); ?></button></p>
			</form>
		</div>

		<div class="selda-card">
			<h2><?php esc_html_e( 'Send a test lead', 'selda' ); ?></h2>
			<p><?php esc_html_e( 'Sends one lead using your own details, so you can see it arrive. Nothing is emailed to anyone.', 'selda' ); ?></p>
			<form method="post">
				<?php wp_nonce_field( 'selda_settings' ); ?>
				<input type="hidden" name="selda_action" value="test">
				<button type="submit" class="button"><?php esc_html_e( 'Send test lead', 'selda' ); ?></button>
			</form>
		</div>

		<div class="selda-card">
			<h2><?php esc_html_e( 'Build a form', 'selda' ); ?></h2>
			<p><?php esc_html_e( 'Put this shortcode on any page. Every submission goes to Selda.', 'selda' ); ?></p>
			<p><code>[selda_form]</code></p>
			<p><?php esc_html_e( 'A lead magnet form that feeds one campaign and reveals a download:', 'selda' ); ?></p>
			<p><code>[selda_form fields="email" button="Get the guide" campaign="&lt;campaign id&gt;" thanks="Here it is." type="guide_downloaded"]</code></p>
			<table class="widefat striped" style="max-width:760px">
				<thead><tr><th><?php esc_html_e( 'Attribute', 'selda' ); ?></th><th><?php esc_html_e( 'What it does', 'selda' ); ?></th></tr></thead>
				<tbody>
					<tr><td><code>fields</code></td><td><?php esc_html_e( 'Comma separated: name, email, phone, company, message. Default: name, email, message.', 'selda' ); ?></td></tr>
					<tr><td><code>required</code></td><td><?php esc_html_e( 'Which of those must be filled. Default: email.', 'selda' ); ?></td></tr>
					<tr><td><code>campaign</code></td><td><?php esc_html_e( 'Campaign id for this form only. Overrides the site default.', 'selda' ); ?></td></tr>
					<tr><td><code>type</code></td><td><?php esc_html_e( 'Event name in Selda, e.g. quote_requested or guide_downloaded.', 'selda' ); ?></td></tr>
					<tr><td><code>tags</code></td><td><?php esc_html_e( 'Comma separated tags added to the lead.', 'selda' ); ?></td></tr>
					<tr><td><code>button</code></td><td><?php esc_html_e( 'Button label.', 'selda' ); ?></td></tr>
					<tr><td><code>thanks</code></td><td><?php esc_html_e( 'Message shown in place of the form afterwards.', 'selda' ); ?></td></tr>
				</tbody>
			</table>
		</div>

		<div class="selda-card">
			<h2><?php esc_html_e( 'Recent activity', 'selda' ); ?></h2>
			<?php $rows = Selda_Log::recent( 20 ); ?>
			<?php if ( empty( $rows ) ) : ?>
				<p><?php esc_html_e( 'Nothing yet. Send a test lead to see this fill up.', 'selda' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr>
						<th style="width:150px"><?php esc_html_e( 'When', 'selda' ); ?></th>
						<th style="width:90px"><?php esc_html_e( 'Status', 'selda' ); ?></th>
						<th><?php esc_html_e( 'Lead', 'selda' ); ?></th>
						<th><?php esc_html_e( 'Detail', 'selda' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( wp_date( 'j.n.Y H:i', $row['time'] ) ); ?></td>
							<td><span class="selda-pill selda-<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( $row['status'] ); ?></span></td>
							<td><?php echo esc_html( $row['who'] ); ?></td>
							<td><?php echo esc_html( $row['detail'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<form method="post" style="margin-top:12px">
					<?php wp_nonce_field( 'selda_settings' ); ?>
					<input type="hidden" name="selda_action" value="clear_log">
					<button type="submit" class="button button-small"><?php esc_html_e( 'Clear log', 'selda' ); ?></button>
				</form>
			<?php endif; ?>
		</div>

		<div class="selda-card">
			<form method="post">
				<?php wp_nonce_field( 'selda_settings' ); ?>
				<input type="hidden" name="selda_action" value="disconnect">
				<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Disconnect', 'selda' ); ?></button>
				<span class="description"><?php esc_html_e( 'Stops sending immediately. Your forms keep working.', 'selda' ); ?></span>
			</form>
		</div>

	<?php endif; ?>
</div>

<style>
.selda-wrap .selda-lede{font-size:14px;color:#50575e;max-width:640px}
.selda-card{background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:20px 22px;margin:18px 0;max-width:900px}
.selda-card h2{margin-top:0;font-size:16px}
.selda-card.selda-ok{border-left:4px solid #2f7a68}
.selda-pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em}
.selda-pill.selda-sent{background:#e3f3ee;color:#1c6b58}
.selda-pill.selda-error{background:#fbeaea;color:#a02b2b}
.selda-pill.selda-skipped{background:#f0f0f1;color:#646970}
.selda-card details{margin:10px 0}
.selda-card summary{cursor:pointer;color:#2271b1}
</style>
		<?php
	}
}
