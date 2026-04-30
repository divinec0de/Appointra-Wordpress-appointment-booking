<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CHM_Stripe {

    /* ─── Get secret key based on mode ─── */
    private static function get_secret_key() {
        $mode = get_option( 'chm_appt_stripe_mode', 'test' );
        return $mode === 'live'
            ? get_option( 'chm_appt_stripe_live_sk' )
            : get_option( 'chm_appt_stripe_test_sk' );
    }

    /* ─── Create a PaymentIntent ─── */
    public static function create_payment_intent( $amount, $currency, $metadata = [] ) {
        $sk = self::get_secret_key();
        if ( ! $sk ) {
            return new WP_Error( 'stripe', 'Stripe secret key not configured. Please contact the administrator.' );
        }

        $body = [
            'amount'               => $amount,
            'currency'             => $currency,
            'payment_method_types' => [ 'card' ],
            'description'          => sprintf(
                'Appointment: %s on %s at %s',
                $metadata['service'] ?? 'Consultation',
                $metadata['date'] ?? '',
                $metadata['time'] ?? ''
            ),
        ];

        // Add metadata
        if ( ! empty( $metadata ) ) {
            foreach ( $metadata as $key => $val ) {
                $body["metadata[{$key}]"] = $val;
            }
        }

        // Add receipt email
        if ( ! empty( $metadata['customer_email'] ) ) {
            $body['receipt_email'] = $metadata['customer_email'];
        }

        $response = wp_remote_post( 'https://api.stripe.com/v1/payment_intents', [
            'headers' => [
                'Authorization' => 'Bearer ' . $sk,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body'    => http_build_query( $body ),
            'timeout' => 30,
        ]);

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'stripe', 'Payment service unavailable: ' . $response->get_error_message() );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $data['error'] ) ) {
            return new WP_Error( 'stripe', $data['error']['message'] ?? 'Payment error occurred.' );
        }

        if ( empty( $data['client_secret'] ) ) {
            return new WP_Error( 'stripe', 'Failed to initialize payment.' );
        }

        return $data['client_secret'];
    }
}
