<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CHM_Frontend {

    public static function init() {
        add_shortcode( 'chm_booking', [ __CLASS__, 'render_shortcode' ] );
        add_action( 'wp_ajax_chm_get_slots',        [ __CLASS__, 'ajax_get_slots' ] );
        add_action( 'wp_ajax_nopriv_chm_get_slots',  [ __CLASS__, 'ajax_get_slots' ] );
        add_action( 'wp_ajax_chm_create_booking',        [ __CLASS__, 'ajax_create_booking' ] );
        add_action( 'wp_ajax_nopriv_chm_create_booking',  [ __CLASS__, 'ajax_create_booking' ] );
        add_action( 'wp_ajax_chm_create_payment_intent',        [ __CLASS__, 'ajax_create_payment_intent' ] );
        add_action( 'wp_ajax_nopriv_chm_create_payment_intent',  [ __CLASS__, 'ajax_create_payment_intent' ] );
    }

    /* ── Parse services: "Name|Price" per line → [ {name,price}, … ] ── */
    public static function get_services() {
        $raw = get_option( 'chm_appt_services_pricing', '' );
        if ( ! $raw ) return [];
        $lines = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
        $services = [];
        foreach ( $lines as $line ) {
            $parts = explode( '|', $line, 2 );
            $name  = trim( $parts[0] );
            $price = isset( $parts[1] ) ? floatval( trim( $parts[1] ) ) : 0;
            if ( $name ) $services[] = [ 'name' => $name, 'price' => $price ];
        }
        return $services;
    }

    /* ── Lookup price by service name ── */
    public static function get_service_price( $service_name ) {
        foreach ( self::get_services() as $s ) {
            if ( $s['name'] === $service_name ) return $s['price'];
        }
        return 0;
    }

    /* ── Currency symbol ── */
    private static function cur_sym() {
        $map = [ 'USD'=>'$','EUR'=>'€','GBP'=>'£','CAD'=>'CA$','AUD'=>'A$' ];
        return $map[ get_option( 'chm_appt_currency', 'USD' ) ] ?? '$';
    }

    /* ── Enqueue assets ── */
    private static function enqueue_assets() {
        $mode = get_option( 'chm_appt_stripe_mode', 'test' );
        $pk   = $mode === 'live'
            ? get_option( 'chm_appt_stripe_live_pk' )
            : get_option( 'chm_appt_stripe_test_pk' );

        $svc_js = [];
        foreach ( self::get_services() as $s ) $svc_js[ $s['name'] ] = $s['price'];

        wp_enqueue_style( 'chm-frontend', CHM_APPT_URL . 'assets/css/frontend.css', [], CHM_APPT_VERSION );
        wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/', [], null, true );
        wp_enqueue_script( 'chm-frontend', CHM_APPT_URL . 'assets/js/frontend.js', [ 'jquery', 'stripe-js' ], CHM_APPT_VERSION, true );
        wp_localize_script( 'chm-frontend', 'chmBooking', [
            'ajax'         => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'chm_booking_nonce' ),
            'stripeKey'    => $pk,
            'services'     => $svc_js,
            'currencySym'  => self::cur_sym(),
            'currency'     => strtolower( get_option( 'chm_appt_currency', 'USD' ) ),
            'duration'     => get_option( 'chm_appt_slot_duration', '60' ),
            'successMsg'   => get_option( 'chm_appt_success_message', 'Booking confirmed!' ),
            'businessDays' => get_option( 'chm_appt_business_days', [] ),
            'blockedDates' => self::get_blocked_dates(),
            'minNotice'    => intval( get_option( 'chm_appt_min_notice_hours', '24' ) ),
            'maxAdvance'   => intval( get_option( 'chm_appt_max_advance_days', '60' ) ),
        ]);
    }

    private static function get_blocked_dates() {
        $raw = get_option( 'chm_appt_blocked_dates', '' );
        if ( ! $raw ) return [];
        return array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
    }

    /* ══════════════════════════════════════
       SHORTCODE
       ══════════════════════════════════════ */
    public static function render_shortcode( $atts ) {
        self::enqueue_assets();
        $services = self::get_services();
        $duration = get_option( 'chm_appt_slot_duration', '60' );
        $sym      = self::cur_sym();

        ob_start();
        ?>
        <div id="chm-booking-app" class="chm-booking">

            <!-- STEP 1: Date & Time -->
            <div class="chm-step chm-step--active" data-step="1">
                <div class="chm-step-header">
                    <span class="chm-step-num">1</span>
                    <div><h3>Select Date &amp; Time</h3><p>Choose your preferred appointment slot</p></div>
                </div>
                <div class="chm-datetime-grid">
                    <div class="chm-calendar-wrap">
                        <div class="chm-cal-nav">
                            <button type="button" class="chm-cal-prev">&larr;</button>
                            <span class="chm-cal-month-label"></span>
                            <button type="button" class="chm-cal-next">&rarr;</button>
                        </div>
                        <div class="chm-cal-weekdays"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>
                        <div class="chm-cal-days"></div>
                    </div>
                    <div class="chm-slots-wrap">
                        <div class="chm-slots-placeholder">← Select a date to view available times</div>
                        <div class="chm-slots-loading" style="display:none">Loading available slots…</div>
                        <div class="chm-slots-list" style="display:none"></div>
                        <div class="chm-slots-empty" style="display:none">No available slots for this date.</div>
                    </div>
                </div>
                <div class="chm-step-footer">
                    <button type="button" class="chm-btn chm-btn--next" data-next="2" disabled>Continue</button>
                </div>
            </div>

            <!-- STEP 2: Details + Service selection -->
            <div class="chm-step" data-step="2">
                <div class="chm-step-header">
                    <span class="chm-step-num">2</span>
                    <div><h3>Your Details &amp; Service</h3><p>Tell us who you are and choose a service</p></div>
                </div>
                <div class="chm-form-fields">
                    <div class="chm-form-row">
                        <div class="chm-field"><label>Full Name <span>*</span></label><input type="text" id="chm-name" required placeholder="Your full name"></div>
                        <div class="chm-field"><label>Email <span>*</span></label><input type="email" id="chm-email" required placeholder="you@email.com"></div>
                    </div>
                    <div class="chm-form-row">
                        <div class="chm-field"><label>Phone</label><input type="tel" id="chm-phone" placeholder="+1 (000) 000-0000"></div>
                        <div class="chm-field">
                            <label>Service <span>*</span></label>
                            <select id="chm-service" required>
                                <option value="">Select a service…</option>
                                <?php foreach ( $services as $s ) : ?>
                                <option value="<?php echo esc_attr( $s['name'] ); ?>" data-price="<?php echo esc_attr( $s['price'] ); ?>">
                                    <?php echo esc_html( $s['name'] ); ?> — <?php echo $sym . number_format( $s['price'], 2 ); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div id="chm-price-preview" class="chm-price-preview" style="display:none">
                        <span>Session price:</span>
                        <strong id="chm-price-value"></strong>
                    </div>
                    <div class="chm-field"><label>Notes (optional)</label><textarea id="chm-notes" rows="3" placeholder="Anything you'd like us to know ahead of the session?"></textarea></div>
                </div>
                <div class="chm-step-footer">
                    <button type="button" class="chm-btn chm-btn--back" data-back="1">Back</button>
                    <button type="button" class="chm-btn chm-btn--next" data-next="3">Continue to Payment</button>
                </div>
            </div>

            <!-- STEP 3: Payment -->
            <div class="chm-step" data-step="3">
                <div class="chm-step-header">
                    <span class="chm-step-num">3</span>
                    <div><h3>Payment</h3><p>Complete your booking with a secure payment</p></div>
                </div>
                <div class="chm-summary-card">
                    <div class="chm-summary-row"><span>Date</span><strong id="chm-sum-date">—</strong></div>
                    <div class="chm-summary-row"><span>Time</span><strong id="chm-sum-time">—</strong></div>
                    <div class="chm-summary-row"><span>Duration</span><strong><?php echo $duration; ?> min</strong></div>
                    <div class="chm-summary-row"><span>Service</span><strong id="chm-sum-service">—</strong></div>
                    <div class="chm-summary-row chm-summary-total"><span>Total</span><strong id="chm-sum-total">—</strong></div>
                </div>
                <div class="chm-stripe-wrap">
                    <label>Card Details</label>
                    <div id="chm-card-element"></div>
                    <div id="chm-card-errors" class="chm-error" role="alert"></div>
                </div>
                <div class="chm-step-footer">
                    <button type="button" class="chm-btn chm-btn--back" data-back="2">Back</button>
                    <button type="button" class="chm-btn chm-btn--pay" id="chm-pay-btn">
                        <span class="chm-btn-text">Pay &amp; Book</span>
                        <span class="chm-btn-spinner" style="display:none"></span>
                    </button>
                </div>
            </div>

            <!-- STEP 4: Confirmation -->
            <div class="chm-step" data-step="4">
                <div class="chm-confirmation">
                    <div class="chm-confirm-icon">✓</div>
                    <h3>Booking Confirmed!</h3>
                    <p id="chm-confirm-msg"></p>
                    <div class="chm-confirm-details" id="chm-confirm-details"></div>
                    <a href="<?php echo esc_url( home_url() ); ?>" class="chm-btn">Back to Home</a>
                </div>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }

    /* ── AJAX: Available slots ── */
    public static function ajax_get_slots() {
        check_ajax_referer( 'chm_booking_nonce', 'nonce' );
        $date = sanitize_text_field( $_POST['date'] );
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) wp_send_json_error( 'Invalid date' );

        $start    = intval( get_option( 'chm_appt_start_hour', '9' ) );
        $end      = intval( get_option( 'chm_appt_end_hour', '17' ) );
        $duration = intval( get_option( 'chm_appt_slot_duration', '60' ) );
        $booked   = CHM_DB::get_booked_slots( $date );
        $slots    = [];

        for ( $h = $start; $h < $end; ) {
            if ( $duration < 60 ) {
                for ( $m = 0; $m < 60; $m += $duration ) {
                    $ts = sprintf( '%02d:%02d:00', $h, $m );
                    if ( ($h*60+$m) + $duration > $end*60 ) break;
                    $slots[] = [ 'time'=>$ts, 'label'=>date('g:i A',strtotime($ts)), 'available'=>!in_array($ts,$booked) ];
                }
                $h++;
            } else {
                $ts = sprintf( '%02d:00:00', $h );
                $slots[] = [ 'time'=>$ts, 'label'=>date('g:i A',strtotime($ts)), 'available'=>!in_array($ts,$booked) ];
                $h += intval( $duration / 60 );
            }
        }
        wp_send_json_success( $slots );
    }

    /* ── AJAX: Create PaymentIntent (price from selected service) ── */
    public static function ajax_create_payment_intent() {
        check_ajax_referer( 'chm_booking_nonce', 'nonce' );

        $service_name = sanitize_text_field( $_POST['service'] ?? '' );
        $rate         = self::get_service_price( $service_name );
        $currency     = strtolower( get_option( 'chm_appt_currency', 'USD' ) );

        if ( $rate <= 0 ) wp_send_json_error( 'Invalid service or price is zero.' );

        $amount = intval( $rate * 100 ); // cents

        $intent = CHM_Stripe::create_payment_intent( $amount, $currency, [
            'customer_name'  => sanitize_text_field( $_POST['name'] ?? '' ),
            'customer_email' => sanitize_email( $_POST['email'] ?? '' ),
            'service'        => $service_name,
            'amount_display' => self::cur_sym() . number_format( $rate, 2 ),
            'date'           => sanitize_text_field( $_POST['date'] ?? '' ),
            'time'           => sanitize_text_field( $_POST['time'] ?? '' ),
        ]);

        if ( is_wp_error( $intent ) ) wp_send_json_error( $intent->get_error_message() );
        wp_send_json_success( [ 'clientSecret' => $intent ] );
    }

    /* ── AJAX: Create booking record ── */
    public static function ajax_create_booking() {
        check_ajax_referer( 'chm_booking_nonce', 'nonce' );

        $name       = sanitize_text_field( $_POST['name'] ?? '' );
        $email      = sanitize_email( $_POST['email'] ?? '' );
        $phone      = sanitize_text_field( $_POST['phone'] ?? '' );
        $service    = sanitize_text_field( $_POST['service'] ?? '' );
        $date       = sanitize_text_field( $_POST['date'] ?? '' );
        $time       = sanitize_text_field( $_POST['time'] ?? '' );
        $notes      = sanitize_textarea_field( $_POST['notes'] ?? '' );
        $payment_id = sanitize_text_field( $_POST['payment_id'] ?? '' );

        if ( ! $name || ! $email || ! $date || ! $time || ! $service )
            wp_send_json_error( 'Missing required fields.' );

        $rate     = self::get_service_price( $service );
        $duration = intval( get_option( 'chm_appt_slot_duration', '60' ) );
        $currency = get_option( 'chm_appt_currency', 'USD' );

        $appt_id = CHM_DB::insert([
            'customer_name'     => $name,
            'customer_email'    => $email,
            'customer_phone'    => $phone,
            'service'           => $service,
            'appt_date'         => $date,
            'appt_time'         => $time,
            'duration_min'      => $duration,
            'amount'            => $rate,
            'currency'          => $currency,
            'stripe_payment_id' => $payment_id,
            'status'            => 'confirmed',
            'notes'             => $notes,
        ]);

        if ( ! $appt_id ) wp_send_json_error( 'Failed to create booking.' );

        $appt = CHM_DB::get( $appt_id );
        CHM_Email::send_confirmation_to_customer( $appt );
        CHM_Email::send_notification_to_admin( $appt );

        wp_send_json_success([
            'id'      => $appt_id,
            'date'    => date( 'l, F j, Y', strtotime( $date ) ),
            'time'    => date( 'g:i A', strtotime( $time ) ),
            'service' => $service,
            'amount'  => self::cur_sym() . number_format( $rate, 2 ),
        ]);
    }
}
