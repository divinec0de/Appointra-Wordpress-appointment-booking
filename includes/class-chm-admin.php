<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CHM_Admin {

    public static function init() {
        add_action( 'admin_menu',            [ __CLASS__, 'add_menus' ] );
        add_action( 'admin_init',            [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
        add_action( 'wp_ajax_chm_appt_update_status', [ __CLASS__, 'ajax_update_status' ] );
        add_action( 'wp_ajax_chm_appt_delete',        [ __CLASS__, 'ajax_delete' ] );
    }

    /* ─── Admin menus ─── */
    public static function add_menus() {
        add_menu_page(
            'Appointra', 'Appointra', 'manage_options',
            'chm-appointments', [ __CLASS__, 'page_appointments' ],
            'dashicons-calendar-alt', 26
        );
        add_submenu_page(
            'chm-appointments', 'All Appointments', 'All Appointments',
            'manage_options', 'chm-appointments', [ __CLASS__, 'page_appointments' ]
        );
        add_submenu_page(
            'chm-appointments', 'Settings', 'Settings',
            'manage_options', 'chm-appt-settings', [ __CLASS__, 'page_settings' ]
        );
    }

    /* ─── Register settings — SEPARATE GROUP PER TAB ─── */
    public static function register_settings() {

        // General tab
        foreach ( [ 'chm_appt_services_pricing', 'chm_appt_currency', 'chm_appt_slot_duration', 'chm_appt_success_message' ] as $f ) {
            register_setting( 'chm_appt_general', $f );
        }

        // Stripe tab
        foreach ( [ 'chm_appt_stripe_mode', 'chm_appt_stripe_test_pk', 'chm_appt_stripe_test_sk', 'chm_appt_stripe_live_pk', 'chm_appt_stripe_live_sk' ] as $f ) {
            register_setting( 'chm_appt_stripe', $f );
        }

        // Schedule tab
        foreach ( [ 'chm_appt_start_hour', 'chm_appt_end_hour', 'chm_appt_min_notice_hours', 'chm_appt_max_advance_days', 'chm_appt_blocked_dates' ] as $f ) {
            register_setting( 'chm_appt_schedule', $f );
        }
        register_setting( 'chm_appt_schedule', 'chm_appt_business_days', [
            'type'              => 'array',
            'sanitize_callback' => function( $v ) { return is_array( $v ) ? $v : []; },
        ]);

        // Email tab
        foreach ( [ 'chm_appt_admin_email', 'chm_appt_from_name', 'chm_appt_from_email' ] as $f ) {
            register_setting( 'chm_appt_email', $f );
        }
    }

    /* ─── Enqueue admin assets ─── */
    public static function enqueue( $hook ) {
        if ( strpos( $hook, 'chm-appt' ) === false && strpos( $hook, 'chm-appointments' ) === false ) return;
        wp_enqueue_style( 'chm-admin', CHM_APPT_URL . 'assets/css/admin.css', [], CHM_APPT_VERSION );
        wp_enqueue_script( 'chm-admin', CHM_APPT_URL . 'assets/js/admin.js', [ 'jquery' ], CHM_APPT_VERSION, true );
        wp_localize_script( 'chm-admin', 'chmAdmin', [
            'ajax'  => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'chm_admin_nonce' ),
        ]);
    }

    /* ─── Appointments list page ─── */
    public static function page_appointments() {
        $status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
        $paged  = max( 1, isset( $_GET['paged'] ) ? intval( $_GET['paged'] ) : 1 );
        $limit  = 20;
        $offset = ( $paged - 1 ) * $limit;

        $args = [ 'limit' => $limit, 'offset' => $offset ];
        if ( $status_filter ) $args['status'] = $status_filter;

        $items = CHM_DB::get_all( $args );
        $total = CHM_DB::count( $status_filter ? [ 'status' => $status_filter ] : [] );
        $pages = ceil( $total / $limit );

        $count_all       = CHM_DB::count();
        $count_confirmed = CHM_DB::count( [ 'status' => 'confirmed' ] );
        $count_pending   = CHM_DB::count( [ 'status' => 'pending' ] );
        $count_cancelled = CHM_DB::count( [ 'status' => 'cancelled' ] );
        $count_completed = CHM_DB::count( [ 'status' => 'completed' ] );

        ?>
        <div class="wrap chm-admin-wrap">
            <h1 class="wp-heading-inline">Appointra — Appointments</h1>
            <p class="chm-shortcode-hint">Use shortcode: <code>[chm_booking]</code> on any page to display the booking form.</p>

            <ul class="subsubsub">
                <li><a href="?page=chm-appointments" class="<?php echo $status_filter===''?'current':''; ?>">All <span class="count">(<?php echo $count_all; ?>)</span></a> |</li>
                <li><a href="?page=chm-appointments&status=confirmed" class="<?php echo $status_filter==='confirmed'?'current':''; ?>">Confirmed <span class="count">(<?php echo $count_confirmed; ?>)</span></a> |</li>
                <li><a href="?page=chm-appointments&status=pending" class="<?php echo $status_filter==='pending'?'current':''; ?>">Pending <span class="count">(<?php echo $count_pending; ?>)</span></a> |</li>
                <li><a href="?page=chm-appointments&status=completed" class="<?php echo $status_filter==='completed'?'current':''; ?>">Completed <span class="count">(<?php echo $count_completed; ?>)</span></a> |</li>
                <li><a href="?page=chm-appointments&status=cancelled" class="<?php echo $status_filter==='cancelled'?'current':''; ?>">Cancelled <span class="count">(<?php echo $count_cancelled; ?>)</span></a></li>
            </ul>

            <table class="wp-list-table widefat fixed striped chm-table">
                <thead>
                    <tr>
                        <th style="width:50px">ID</th>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Service</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Amount</th>
                        <th>Payment ID</th>
                        <th>Status</th>
                        <th style="width:180px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $items ) ) : ?>
                        <tr><td colspan="11" style="text-align:center;padding:2rem;color:#888">No appointments found.</td></tr>
                    <?php else : foreach ( $items as $item ) : ?>
                        <tr data-id="<?php echo $item->id; ?>">
                            <td><strong>#<?php echo $item->id; ?></strong></td>
                            <td><?php echo esc_html( $item->customer_name ); ?></td>
                            <td><a href="mailto:<?php echo esc_attr( $item->customer_email ); ?>"><?php echo esc_html( $item->customer_email ); ?></a></td>
                            <td><?php echo esc_html( $item->customer_phone ); ?></td>
                            <td><?php echo esc_html( $item->service ); ?></td>
                            <td><?php echo date( 'M j, Y', strtotime( $item->appt_date ) ); ?></td>
                            <td><?php echo date( 'g:i A', strtotime( $item->appt_time ) ); ?></td>
                            <td>$<?php echo number_format( $item->amount, 2 ); ?></td>
                            <td><code style="font-size:11px"><?php echo $item->stripe_payment_id ? esc_html( substr( $item->stripe_payment_id, 0, 20 ) . '...' ) : '—'; ?></code></td>
                            <td><span class="chm-badge chm-badge--<?php echo esc_attr( $item->status ); ?>"><?php echo ucfirst( $item->status ); ?></span></td>
                            <td>
                                <div class="chm-actions">
                                    <select class="chm-status-select" data-id="<?php echo $item->id; ?>">
                                        <option value="">Change…</option>
                                        <option value="confirmed" <?php selected( $item->status, 'confirmed' ); ?>>Confirmed</option>
                                        <option value="pending" <?php selected( $item->status, 'pending' ); ?>>Pending</option>
                                        <option value="completed" <?php selected( $item->status, 'completed' ); ?>>Completed</option>
                                        <option value="cancelled" <?php selected( $item->status, 'cancelled' ); ?>>Cancelled</option>
                                    </select>
                                    <button class="button button-small chm-delete-btn" data-id="<?php echo $item->id; ?>">Delete</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>

            <?php if ( $pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php for ( $i = 1; $i <= $pages; $i++ ) : ?>
                        <a class="button <?php echo $i===$paged?'button-primary':''; ?>" href="?page=chm-appointments<?php echo $status_filter?"&status={$status_filter}":''; ?>&paged=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /* ─── Settings page ─── */
    public static function page_settings() {
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';

        // Map tab → settings group
        $groups = [
            'general'  => 'chm_appt_general',
            'stripe'   => 'chm_appt_stripe',
            'schedule' => 'chm_appt_schedule',
            'email'    => 'chm_appt_email',
        ];
        $settings_group = $groups[ $active_tab ] ?? 'chm_appt_general';

        ?>
        <div class="wrap chm-admin-wrap">
            <h1>Appointra — Settings</h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=chm-appt-settings&tab=general"  class="nav-tab <?php echo $active_tab==='general'?'nav-tab-active':''; ?>">General</a>
                <a href="?page=chm-appt-settings&tab=stripe"   class="nav-tab <?php echo $active_tab==='stripe'?'nav-tab-active':''; ?>">Stripe Payment</a>
                <a href="?page=chm-appt-settings&tab=schedule" class="nav-tab <?php echo $active_tab==='schedule'?'nav-tab-active':''; ?>">Schedule</a>
                <a href="?page=chm-appt-settings&tab=email"    class="nav-tab <?php echo $active_tab==='email'?'nav-tab-active':''; ?>">Emails</a>
            </nav>

            <form method="post" action="options.php" class="chm-settings-form">
                <?php settings_fields( $settings_group ); ?>

                <?php if ( $active_tab === 'general' ) : ?>
                <table class="form-table">
                    <tr>
                        <th>Services &amp; Pricing</th>
                        <td><textarea name="chm_appt_services_pricing" rows="10" class="large-text" placeholder="Keynote Speaking|500&#10;Executive Consulting|300&#10;Corporate Workshop|1000"><?php echo esc_textarea( get_option( 'chm_appt_services_pricing' ) ); ?></textarea>
                        <p class="description"><strong>Format: <code>Service Name|Price</code></strong> — one per line. Example:<br>
                        <code>Keynote Speaking|500</code><br>
                        <code>Executive Consulting|300</code><br>
                        <code>VIP 1-on-1|1000</code><br>
                        Each service will appear in the booking dropdown with its own price.</p></td>
                    </tr>
                    <tr>
                        <th>Currency</th>
                        <td><select name="chm_appt_currency">
                            <?php foreach( ['USD'=>'USD ($)','EUR'=>'EUR (€)','GBP'=>'GBP (£)','CAD'=>'CAD ($)','AUD'=>'AUD ($)'] as $code => $label ) : ?>
                                <option value="<?php echo $code; ?>" <?php selected( get_option( 'chm_appt_currency', 'USD' ), $code ); ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select></td>
                    </tr>
                    <tr>
                        <th>Session Duration (minutes)</th>
                        <td><select name="chm_appt_slot_duration">
                            <?php foreach( [30,45,60,90,120] as $d ) : ?>
                                <option value="<?php echo $d; ?>" <?php selected( get_option( 'chm_appt_slot_duration', '60' ), $d ); ?>><?php echo $d; ?> min</option>
                            <?php endforeach; ?>
                        </select></td>
                    </tr>
                    <tr>
                        <th>Success Message</th>
                        <td><textarea name="chm_appt_success_message" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'chm_appt_success_message' ) ); ?></textarea></td>
                    </tr>
                </table>

                <?php elseif ( $active_tab === 'stripe' ) : ?>
                <table class="form-table">
                    <tr>
                        <th>Stripe Mode</th>
                        <td>
                            <label><input type="radio" name="chm_appt_stripe_mode" value="test" <?php checked( get_option( 'chm_appt_stripe_mode' ), 'test' ); ?>> Test Mode</label>&nbsp;&nbsp;
                            <label><input type="radio" name="chm_appt_stripe_mode" value="live" <?php checked( get_option( 'chm_appt_stripe_mode' ), 'live' ); ?>> Live Mode</label>
                            <p class="description">Use Test Mode until you're ready to accept real payments.</p>
                        </td>
                    </tr>
                    <tr><td colspan="2"><h3 style="margin:0">Test Keys</h3></td></tr>
                    <tr>
                        <th>Publishable Key (Test)</th>
                        <td><input type="text" name="chm_appt_stripe_test_pk" value="<?php echo esc_attr( get_option( 'chm_appt_stripe_test_pk' ) ); ?>" class="large-text" placeholder="pk_test_..."></td>
                    </tr>
                    <tr>
                        <th>Secret Key (Test)</th>
                        <td><input type="password" name="chm_appt_stripe_test_sk" value="<?php echo esc_attr( get_option( 'chm_appt_stripe_test_sk' ) ); ?>" class="large-text" placeholder="sk_test_..."></td>
                    </tr>
                    <tr><td colspan="2"><h3 style="margin:0">Live Keys</h3></td></tr>
                    <tr>
                        <th>Publishable Key (Live)</th>
                        <td><input type="text" name="chm_appt_stripe_live_pk" value="<?php echo esc_attr( get_option( 'chm_appt_stripe_live_pk' ) ); ?>" class="large-text" placeholder="pk_live_..."></td>
                    </tr>
                    <tr>
                        <th>Secret Key (Live)</th>
                        <td><input type="password" name="chm_appt_stripe_live_sk" value="<?php echo esc_attr( get_option( 'chm_appt_stripe_live_sk' ) ); ?>" class="large-text" placeholder="sk_live_..."></td>
                    </tr>
                </table>

                <?php elseif ( $active_tab === 'schedule' ) : ?>
                <table class="form-table">
                    <tr>
                        <th>Business Days</th>
                        <td>
                            <?php
                            $days = get_option( 'chm_appt_business_days', [] );
                            if ( ! is_array( $days ) ) $days = [];
                            $all_days = [ 'mon'=>'Monday','tue'=>'Tuesday','wed'=>'Wednesday','thu'=>'Thursday','fri'=>'Friday','sat'=>'Saturday','sun'=>'Sunday' ];
                            foreach ( $all_days as $key => $label ) :
                            ?>
                                <label style="display:inline-block;margin-right:16px;margin-bottom:8px">
                                    <input type="checkbox" name="chm_appt_business_days[]" value="<?php echo $key; ?>" <?php checked( in_array( $key, $days ) ); ?>>
                                    <?php echo $label; ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Start Hour</th>
                        <td><select name="chm_appt_start_hour">
                            <?php for ( $h = 6; $h <= 20; $h++ ) : $hh = str_pad( $h, 2, '0', STR_PAD_LEFT ); ?>
                                <option value="<?php echo $hh; ?>" <?php selected( get_option( 'chm_appt_start_hour', '09' ), $hh ); ?>><?php echo date( 'g A', strtotime( "{$hh}:00" ) ); ?></option>
                            <?php endfor; ?>
                        </select></td>
                    </tr>
                    <tr>
                        <th>End Hour</th>
                        <td><select name="chm_appt_end_hour">
                            <?php for ( $h = 7; $h <= 22; $h++ ) : $hh = str_pad( $h, 2, '0', STR_PAD_LEFT ); ?>
                                <option value="<?php echo $hh; ?>" <?php selected( get_option( 'chm_appt_end_hour', '17' ), $hh ); ?>><?php echo date( 'g A', strtotime( "{$hh}:00" ) ); ?></option>
                            <?php endfor; ?>
                        </select></td>
                    </tr>
                    <tr>
                        <th>Minimum Notice (hours)</th>
                        <td><input type="number" name="chm_appt_min_notice_hours" value="<?php echo esc_attr( get_option( 'chm_appt_min_notice_hours', '24' ) ); ?>" min="0" class="small-text">
                        <p class="description">How many hours in advance someone must book (e.g. 24 = must book at least 24 hours ahead).</p></td>
                    </tr>
                    <tr>
                        <th>Max Advance (days)</th>
                        <td><input type="number" name="chm_appt_max_advance_days" value="<?php echo esc_attr( get_option( 'chm_appt_max_advance_days', '60' ) ); ?>" min="1" class="small-text">
                        <p class="description">How far into the future bookings are allowed.</p></td>
                    </tr>
                    <tr>
                        <th>Blocked Dates</th>
                        <td><textarea name="chm_appt_blocked_dates" rows="4" class="large-text" placeholder="2026-05-01&#10;2026-07-04&#10;2026-12-25"><?php echo esc_textarea( get_option( 'chm_appt_blocked_dates' ) ); ?></textarea>
                        <p class="description">One date per line (YYYY-MM-DD). These dates will not be available for booking.</p></td>
                    </tr>
                </table>

                <?php elseif ( $active_tab === 'email' ) : ?>
                <table class="form-table">
                    <tr>
                        <th>Admin Notification Email</th>
                        <td><input type="email" name="chm_appt_admin_email" value="<?php echo esc_attr( get_option( 'chm_appt_admin_email' ) ); ?>" class="regular-text">
                        <p class="description">New bookings will be sent to this email.</p></td>
                    </tr>
                    <tr>
                        <th>From Name</th>
                        <td><input type="text" name="chm_appt_from_name" value="<?php echo esc_attr( get_option( 'chm_appt_from_name' ) ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>From Email</th>
                        <td><input type="email" name="chm_appt_from_email" value="<?php echo esc_attr( get_option( 'chm_appt_from_email' ) ); ?>" class="regular-text"></td>
                    </tr>
                </table>
                <?php endif; ?>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /* ─── AJAX: Update status ─── */
    public static function ajax_update_status() {
        check_ajax_referer( 'chm_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $id     = intval( $_POST['id'] );
        $status = sanitize_text_field( $_POST['status'] );

        if ( ! in_array( $status, [ 'confirmed','pending','completed','cancelled' ] ) ) {
            wp_send_json_error( 'Invalid status' );
        }

        CHM_DB::update( $id, [ 'status' => $status ] );

        $appt = CHM_DB::get( $id );
        if ( $appt ) {
            CHM_Email::send_status_update( $appt );
        }

        wp_send_json_success( [ 'status' => $status ] );
    }

    /* ─── AJAX: Delete ─── */
    public static function ajax_delete() {
        check_ajax_referer( 'chm_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $id = intval( $_POST['id'] );
        CHM_DB::delete( $id );
        wp_send_json_success();
    }
}
