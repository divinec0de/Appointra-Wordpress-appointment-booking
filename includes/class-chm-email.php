<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CHM_Email {

    /* ─── Set HTML content type ─── */
    private static function headers() {
        $from_name  = get_option( 'chm_appt_from_name', get_bloginfo( 'name' ) );
        $from_email = get_option( 'chm_appt_from_email', get_option( 'admin_email' ) );
        return [
            'Content-Type: text/html; charset=UTF-8',
            "From: {$from_name} <{$from_email}>",
        ];
    }

    /* ─── Email wrapper ─── */
    private static function wrap( $content ) {
        $site_name = esc_html( get_option( 'chm_appt_from_name', get_bloginfo( 'name' ) ) );
        return '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="margin:0;padding:0;background:#F5F8FC;font-family:Helvetica,Arial,sans-serif">
            <div style="max-width:600px;margin:40px auto;background:#FFFFFF;border:1px solid #D8E2EE">
                <div style="background:#06101E;padding:30px 40px">
                    <h1 style="margin:0;font-size:20px;font-weight:700;color:#FFFFFF;letter-spacing:0.02em">' . $site_name . '</h1>
                    <p style="margin:4px 0 0;font-size:12px;color:rgba(238,243,255,0.5);letter-spacing:0.1em;text-transform:uppercase">Appointra — Smart Booking</p>
                </div>
                <div style="padding:40px">
                    ' . $content . '
                </div>
                <div style="background:#F5F8FC;padding:20px 40px;border-top:1px solid #D8E2EE;text-align:center">
                    <p style="margin:0;font-size:12px;color:#5A7088">&copy; ' . date('Y') . ' ' . $site_name . '. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
    }

    /* ─── Format appointment details block ─── */
    private static function details_block( $appt ) {
        $date_fmt = date( 'l, F j, Y', strtotime( $appt->appt_date ) );
        $time_fmt = date( 'g:i A', strtotime( $appt->appt_time ) );
        $cur_sym  = '$';
        if ( $appt->currency === 'EUR' ) $cur_sym = '€';
        if ( $appt->currency === 'GBP' ) $cur_sym = '£';

        return '
        <table style="width:100%;border-collapse:collapse;margin:20px 0" cellpadding="0" cellspacing="0">
            <tr>
                <td style="padding:12px 16px;background:#F5F8FC;border:1px solid #D8E2EE;font-size:13px;color:#5A7088;width:120px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em">Date</td>
                <td style="padding:12px 16px;border:1px solid #D8E2EE;font-size:15px;color:#081524;font-weight:600">' . esc_html($date_fmt) . '</td>
            </tr>
            <tr>
                <td style="padding:12px 16px;background:#F5F8FC;border:1px solid #D8E2EE;font-size:13px;color:#5A7088;width:120px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em">Time</td>
                <td style="padding:12px 16px;border:1px solid #D8E2EE;font-size:15px;color:#081524;font-weight:600">' . esc_html($time_fmt) . '</td>
            </tr>
            <tr>
                <td style="padding:12px 16px;background:#F5F8FC;border:1px solid #D8E2EE;font-size:13px;color:#5A7088;width:120px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em">Duration</td>
                <td style="padding:12px 16px;border:1px solid #D8E2EE;font-size:15px;color:#081524">' . esc_html($appt->duration_min) . ' minutes</td>
            </tr>
            <tr>
                <td style="padding:12px 16px;background:#F5F8FC;border:1px solid #D8E2EE;font-size:13px;color:#5A7088;width:120px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em">Service</td>
                <td style="padding:12px 16px;border:1px solid #D8E2EE;font-size:15px;color:#081524">' . esc_html($appt->service) . '</td>
            </tr>
            <tr>
                <td style="padding:12px 16px;background:#F5F8FC;border:1px solid #D8E2EE;font-size:13px;color:#5A7088;width:120px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em">Amount</td>
                <td style="padding:12px 16px;border:1px solid #D8E2EE;font-size:15px;color:#00C8C0;font-weight:700">' . $cur_sym . number_format($appt->amount, 2) . '</td>
            </tr>
        </table>';
    }

    /* ─── Send confirmation to customer ─── */
    public static function send_confirmation_to_customer( $appt ) {
        $content = '
            <h2 style="margin:0 0 8px;font-size:24px;color:#081524">Your Appointment is Confirmed</h2>
            <p style="margin:0 0 24px;font-size:15px;color:#5A7088;line-height:1.6">Hi ' . esc_html($appt->customer_name) . ', your appointment has been booked successfully. Here are your details:</p>
            ' . self::details_block( $appt ) . '
            <div style="background:#06101E;padding:20px 24px;margin-top:24px">
                <p style="margin:0;font-size:14px;color:rgba(238,243,255,0.7);line-height:1.6">If you need to reschedule or cancel, please reply to this email at least 24 hours before your appointment.</p>
            </div>
        ';

        wp_mail(
            $appt->customer_email,
            'Appointment Confirmed — ' . date( 'M j, Y', strtotime( $appt->appt_date ) ),
            self::wrap( $content ),
            self::headers()
        );
    }

    /* ─── Send notification to admin ─── */
    public static function send_notification_to_admin( $appt ) {
        $admin_email = get_option( 'chm_appt_admin_email', get_option( 'admin_email' ) );

        $content = '
            <h2 style="margin:0 0 8px;font-size:24px;color:#081524">New Appointment Booked</h2>
            <p style="margin:0 0 8px;font-size:15px;color:#5A7088;line-height:1.6">A new appointment has been booked on your website.</p>

            <table style="width:100%;border-collapse:collapse;margin:20px 0" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="padding:12px 16px;background:#F5F8FC;border:1px solid #D8E2EE;font-size:13px;color:#5A7088;width:120px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em">Customer</td>
                    <td style="padding:12px 16px;border:1px solid #D8E2EE;font-size:15px;color:#081524;font-weight:600">' . esc_html($appt->customer_name) . '</td>
                </tr>
                <tr>
                    <td style="padding:12px 16px;background:#F5F8FC;border:1px solid #D8E2EE;font-size:13px;color:#5A7088;width:120px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em">Email</td>
                    <td style="padding:12px 16px;border:1px solid #D8E2EE;font-size:15px;color:#081524"><a href="mailto:' . esc_attr($appt->customer_email) . '">' . esc_html($appt->customer_email) . '</a></td>
                </tr>
                <tr>
                    <td style="padding:12px 16px;background:#F5F8FC;border:1px solid #D8E2EE;font-size:13px;color:#5A7088;width:120px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em">Phone</td>
                    <td style="padding:12px 16px;border:1px solid #D8E2EE;font-size:15px;color:#081524">' . esc_html($appt->customer_phone ?: '—') . '</td>
                </tr>
            </table>

            ' . self::details_block( $appt ) . '

            ' . ( $appt->notes ? '<div style="background:#F5F8FC;padding:16px;border:1px solid #D8E2EE;margin-top:16px"><p style="margin:0 0 4px;font-size:13px;font-weight:600;color:#5A7088;text-transform:uppercase;letter-spacing:0.06em">Notes</p><p style="margin:0;font-size:14px;color:#081524;line-height:1.6">' . nl2br(esc_html($appt->notes)) . '</p></div>' : '' ) . '

            <p style="margin:24px 0 0;font-size:14px;color:#5A7088">Stripe Payment ID: <code style="background:#F5F8FC;padding:2px 6px;font-size:12px">' . esc_html($appt->stripe_payment_id) . '</code></p>

            <p style="margin:16px 0 0"><a href="' . admin_url('admin.php?page=chm-appointments') . '" style="display:inline-block;background:#00C8C0;color:#06101E;padding:12px 24px;text-decoration:none;font-weight:700;font-size:14px;letter-spacing:0.04em;text-transform:uppercase">View in Dashboard</a></p>
        ';

        wp_mail(
            $admin_email,
            'New Booking: ' . esc_html($appt->customer_name) . ' — ' . date( 'M j', strtotime( $appt->appt_date ) ),
            self::wrap( $content ),
            self::headers()
        );
    }

    /* ─── Send status update to customer ─── */
    public static function send_status_update( $appt ) {
        $status_labels = [
            'confirmed' => [ 'Appointment Confirmed', 'Your appointment has been confirmed.' ],
            'cancelled' => [ 'Appointment Cancelled', 'Your appointment has been cancelled. If this was unexpected, please contact us.' ],
            'completed' => [ 'Appointment Completed', 'Thank you for your session! We hope it was valuable.' ],
            'pending'   => [ 'Appointment Pending', 'Your appointment is currently under review. We\'ll confirm shortly.' ],
        ];

        $info = $status_labels[ $appt->status ] ?? [ 'Status Update', 'Your appointment status has been updated.' ];

        $content = '
            <h2 style="margin:0 0 8px;font-size:24px;color:#081524">' . $info[0] . '</h2>
            <p style="margin:0 0 24px;font-size:15px;color:#5A7088;line-height:1.6">Hi ' . esc_html($appt->customer_name) . ', ' . $info[1] . '</p>
            ' . self::details_block( $appt ) . '
            <div style="margin-top:24px;padding:16px;background:#F5F8FC;border:1px solid #D8E2EE;text-align:center">
                <p style="margin:0;font-size:13px;color:#5A7088">Status: <strong style="color:#081524;text-transform:uppercase;letter-spacing:0.06em">' . esc_html(ucfirst($appt->status)) . '</strong></p>
            </div>
        ';

        wp_mail(
            $appt->customer_email,
            $info[0] . ' — ' . date( 'M j, Y', strtotime( $appt->appt_date ) ),
            self::wrap( $content ),
            self::headers()
        );
    }
}
