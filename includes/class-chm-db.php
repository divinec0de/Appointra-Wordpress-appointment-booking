<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CHM_DB {

    /* ─── Create table on activation ─── */
    public static function activate() {
        global $wpdb;
        $table   = $wpdb->prefix . CHM_APPT_TABLE;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_name   VARCHAR(200) NOT NULL,
            customer_email  VARCHAR(200) NOT NULL,
            customer_phone  VARCHAR(50)  DEFAULT '',
            service         VARCHAR(200) DEFAULT '',
            appt_date       DATE         NOT NULL,
            appt_time       TIME         NOT NULL,
            duration_min    INT          NOT NULL DEFAULT 60,
            amount          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            currency        VARCHAR(10)  NOT NULL DEFAULT 'USD',
            stripe_payment_id VARCHAR(200) DEFAULT '',
            status          VARCHAR(30)  NOT NULL DEFAULT 'pending',
            notes           TEXT         DEFAULT '',
            created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_date (appt_date),
            KEY idx_status (status)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Default options
        $defaults = [
            'chm_appt_stripe_mode'        => 'test',
            'chm_appt_stripe_test_pk'     => '',
            'chm_appt_stripe_test_sk'     => '',
            'chm_appt_stripe_live_pk'     => '',
            'chm_appt_stripe_live_sk'     => '',
            'chm_appt_currency'           => 'USD',
            'chm_appt_slot_duration'      => '60',
            'chm_appt_business_days'      => [ 'mon','tue','wed','thu','fri' ],
            'chm_appt_start_hour'         => '09',
            'chm_appt_end_hour'           => '17',
            'chm_appt_blocked_dates'      => '',
            'chm_appt_admin_email'        => get_option( 'admin_email' ),
            'chm_appt_from_name'          => get_bloginfo( 'name' ),
            'chm_appt_from_email'         => get_option( 'admin_email' ),
            'chm_appt_services_pricing'   => "Keynote Speaking|500\nExecutive Consulting|300\nCorporate Workshop|1000\nIndividual Coaching|300\nVIP 1-on-1|1000\nGroup Coaching Session|150",
            'chm_appt_min_notice_hours'   => '24',
            'chm_appt_max_advance_days'   => '60',
            'chm_appt_success_message'    => 'Your appointment has been booked successfully! You will receive a confirmation email shortly.',
        ];
        foreach ( $defaults as $key => $val ) {
            if ( get_option( $key ) === false ) {
                update_option( $key, $val );
            }
        }

        update_option( 'chm_appt_db_version', CHM_APPT_VERSION );
    }

    /* ─── Insert appointment ─── */
    public static function insert( $data ) {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . CHM_APPT_TABLE, $data );
        return $wpdb->insert_id;
    }

    /* ─── Update appointment ─── */
    public static function update( $id, $data ) {
        global $wpdb;
        return $wpdb->update( $wpdb->prefix . CHM_APPT_TABLE, $data, [ 'id' => $id ] );
    }

    /* ─── Get single appointment ─── */
    public static function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}" . CHM_APPT_TABLE . " WHERE id = %d", $id
        ) );
    }

    /* ─── Get all appointments (with optional filters) ─── */
    public static function get_all( $args = [] ) {
        global $wpdb;
        $table = $wpdb->prefix . CHM_APPT_TABLE;
        $where = [];
        $vals  = [];

        if ( ! empty( $args['status'] ) ) {
            $where[] = 'status = %s';
            $vals[]  = $args['status'];
        }
        if ( ! empty( $args['date'] ) ) {
            $where[] = 'appt_date = %s';
            $vals[]  = $args['date'];
        }
        if ( ! empty( $args['from_date'] ) ) {
            $where[] = 'appt_date >= %s';
            $vals[]  = $args['from_date'];
        }
        if ( ! empty( $args['to_date'] ) ) {
            $where[] = 'appt_date <= %s';
            $vals[]  = $args['to_date'];
        }

        $sql = "SELECT * FROM {$table}";
        if ( $where ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where );
        }
        $sql .= ' ORDER BY appt_date ASC, appt_time ASC';

        if ( ! empty( $args['limit'] ) ) {
            $sql .= ' LIMIT ' . intval( $args['limit'] );
            if ( ! empty( $args['offset'] ) ) {
                $sql .= ' OFFSET ' . intval( $args['offset'] );
            }
        }

        if ( $vals ) {
            $sql = $wpdb->prepare( $sql, $vals );
        }

        return $wpdb->get_results( $sql );
    }

    /* ─── Count appointments ─── */
    public static function count( $args = [] ) {
        global $wpdb;
        $table = $wpdb->prefix . CHM_APPT_TABLE;
        $where = [];
        $vals  = [];

        if ( ! empty( $args['status'] ) ) {
            $where[] = 'status = %s';
            $vals[]  = $args['status'];
        }

        $sql = "SELECT COUNT(*) FROM {$table}";
        if ( $where ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where );
        }

        if ( $vals ) {
            return (int) $wpdb->get_var( $wpdb->prepare( $sql, $vals ) );
        }
        return (int) $wpdb->get_var( $sql );
    }

    /* ─── Get booked slots for a date ─── */
    public static function get_booked_slots( $date ) {
        global $wpdb;
        $table = $wpdb->prefix . CHM_APPT_TABLE;
        return $wpdb->get_col( $wpdb->prepare(
            "SELECT appt_time FROM {$table} WHERE appt_date = %s AND status IN ('confirmed','pending')",
            $date
        ) );
    }

    /* ─── Delete appointment ─── */
    public static function delete( $id ) {
        global $wpdb;
        return $wpdb->delete( $wpdb->prefix . CHM_APPT_TABLE, [ 'id' => $id ] );
    }
}
