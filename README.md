# Appointra — Smart Booking System

A lightweight WordPress plugin for appointment scheduling with built-in Stripe payments, per-service pricing, and email notifications. No bloat, no dependencies beyond Stripe.js.

![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue) ![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple) ![License](https://img.shields.io/badge/License-GPLv2-green)

---

## Why I Built This

Every booking plugin I tried was either way too heavy for what I needed, locked basic features behind a paywall, or didn't support per-service pricing out of the box. I needed something simple: let clients pick a service (each with its own price), choose a date/time, pay via Stripe, and get a confirmation email. That's it.

---

## Features

- **Per-service pricing** — each service gets its own rate. No flat-fee nonsense.
- **Stripe integration** — PaymentIntent API (SCA/3DS2 compliant). Supports test + live mode toggle.
- **3-step booking flow** — date/time → details → payment → done. Clean, fast, mobile-friendly.
- **Custom calendar** — no jQuery UI datepicker. Built from scratch, respects business days and blocked dates.
- **Email notifications** — branded HTML emails to both the customer (confirmation) and admin (new booking alert). Status change emails too.
- **Admin dashboard** — filterable appointment list with status management (confirmed/pending/completed/cancelled), inline status updates, and deletion.
- **Shortcode-based** — drop `[chm_booking]` on any page. Works with any theme, any page builder.
- **Configurable schedule** — business days, start/end hours, minimum notice period, max advance booking window, blocked dates.
- **Zero external dependencies** — no Composer, no npm, no build step. Upload and activate.

---

## Requirements

- WordPress 6.0+
- PHP 7.4+
- SSL certificate (required by Stripe)
- Stripe account ([stripe.com](https://stripe.com))

---

## Installation

1. Download or clone this repo
2. Zip the `chm-appointments` folder (or grab the release zip)
3. WordPress → Plugins → Add New → Upload Plugin → select the zip
4. Activate
5. Go to **Appointra → Settings** and configure your services, Stripe keys, and schedule

Or via WP-CLI:

```bash
wp plugin install /path/to/appointra.zip --activate
```

---

## Configuration

### General Tab

Add your services with pricing, one per line:

```
Keynote Speaking|500
Executive Consulting|300
Corporate Workshop|1000
Individual Coaching|250
VIP 1-on-1|1000
```

Format: `Service Name|Price`

Set your currency (USD/EUR/GBP/CAD/AUD) and session duration (30/45/60/90/120 min).

### Stripe Tab

1. Grab your API keys from [dashboard.stripe.com/apikeys](https://dashboard.stripe.com/apikeys)
2. Paste test keys first (`pk_test_...` / `sk_test_...`)
3. Test a booking end-to-end
4. Switch to Live Mode and paste live keys when ready

### Schedule Tab

- **Business days** — check the days you're available
- **Hours** — set start/end (e.g., 9 AM – 5 PM)
- **Minimum notice** — hours in advance required (default: 24)
- **Max advance** — how far out clients can book (default: 60 days)
- **Blocked dates** — one per line, `YYYY-MM-DD` format (holidays, vacations, etc.)

### Email Tab

Set the admin notification email and the From name/address for outgoing emails.

---

## Usage

Add the shortcode to any page or post:

```
[chm_booking]
```

That's it. The booking form renders with your configured services, calendar, and Stripe checkout.

In WPBakery, use a **Raw HTML** or **Text Block** element and paste the shortcode.

---

## How It Works

### Booking Flow (Frontend)

1. **Step 1** — Client picks a date from the calendar, then selects an available time slot
2. **Step 2** — Client enters their name, email, phone, selects a service (price updates dynamically), and optionally adds notes
3. **Step 3** — Order summary shown with service-specific total. Client enters card details via Stripe Elements. Payment is processed via PaymentIntent API.
4. **Step 4** — Confirmation screen. Customer receives a branded HTML email. Admin receives a notification email with all details + Stripe payment ID.

### Admin Dashboard

- View all appointments with filtering by status
- Change status inline (triggers an email to the customer)
- Delete appointments
- See Stripe payment IDs for reconciliation

### Emails Sent

| Trigger | Recipient | Content |
|---|---|---|
| Booking created | Customer | Confirmation with date, time, service, amount |
| Booking created | Admin | Full details + Stripe ID + link to dashboard |
| Status changed | Customer | Updated status notification |

---

## Database

The plugin creates one table: `{prefix}_chm_appointments`

| Column | Type | Description |
|---|---|---|
| `id` | BIGINT | Auto-increment PK |
| `customer_name` | VARCHAR(200) | Client name |
| `customer_email` | VARCHAR(200) | Client email |
| `customer_phone` | VARCHAR(50) | Client phone |
| `service` | VARCHAR(200) | Selected service name |
| `appt_date` | DATE | Appointment date |
| `appt_time` | TIME | Appointment time |
| `duration_min` | INT | Session duration in minutes |
| `amount` | DECIMAL(10,2) | Amount charged |
| `currency` | VARCHAR(10) | Currency code |
| `stripe_payment_id` | VARCHAR(200) | Stripe PaymentIntent ID |
| `status` | VARCHAR(30) | confirmed/pending/completed/cancelled |
| `notes` | TEXT | Client notes |
| `created_at` | DATETIME | Record creation timestamp |

Table is created on activation via `dbDelta()`. Uninstalling the plugin does **not** drop the table — your data is safe.

---

## File Structure

```
chm-appointments/
├── chm-appointments.php          # Plugin bootstrap
├── includes/
│   ├── class-chm-db.php          # Database operations (CRUD, table creation)
│   ├── class-chm-admin.php       # Admin pages, settings, AJAX handlers
│   ├── class-chm-frontend.php    # Shortcode, booking form, AJAX endpoints
│   ├── class-chm-stripe.php      # Stripe PaymentIntent integration
│   └── class-chm-email.php       # HTML email templates & sending
├── assets/
│   ├── css/
│   │   ├── admin.css             # Admin dashboard styles
│   │   └── frontend.css          # Booking form styles
│   └── js/
│       ├── admin.js              # Admin status updates & deletion
│       └── frontend.js           # Calendar, slots, Stripe, multi-step form
└── README.md
```

---

## Customization

### Styling

The booking form uses namespaced CSS classes (`.chm-*`), so it won't conflict with your theme. Override styles in your theme's stylesheet or via Customizer → Additional CSS. Key classes:

- `.chm-booking` — outer wrapper
- `.chm-step` — each step panel
- `.chm-cal-day--selected` — selected calendar date
- `.chm-slot--selected` — selected time slot
- `.chm-btn` — primary button
- `.chm-price-preview` — service price display bar
- `.chm-summary-card` — payment summary

### Colors

The default palette is navy/cyan/orange. To match your brand, override these in CSS:

```css
.chm-step-num { background: #your-color; }
.chm-step-header { border-bottom-color: #your-color; }
.chm-btn { background: #your-color; }
.chm-cal-day--selected { background: #your-color; }
.chm-slot--selected { background: #your-color; }
```

---

## Stripe Test Cards

For testing, use Stripe's test card numbers:

| Card | Number |
|---|---|
| Visa (success) | `4242 4242 4242 4242` |
| Visa (decline) | `4000 0000 0000 0002` |
| 3D Secure | `4000 0027 6000 3184` |

Any future expiry date and any 3-digit CVC will work in test mode.

---

## Roadmap

- [ ] Google Calendar sync
- [ ] iCal export (.ics file in confirmation email)
- [ ] Recurring appointment support
- [ ] Coupon/discount codes
- [ ] Multiple staff members with individual availability
- [ ] Webhook-based payment verification (belt + suspenders)
- [ ] REST API endpoints for headless setups

---

## Known Limitations

- Single-provider only (one calendar). Multi-staff is on the roadmap.
- No refund handling from within the plugin — use Stripe Dashboard for refunds.
- Email delivery depends on your WordPress mail setup. Use an SMTP plugin (WP Mail SMTP, FluentSMTP, etc.) for production.
- The table is not dropped on uninstall. This is intentional — booking data is business-critical. Drop manually if needed.

---

## Contributing

PRs welcome. Keep it simple — the whole point of this plugin is that it's lightweight. If your feature needs Composer or a build step, it probably belongs in a different plugin.

---

## License

GPLv2 or later. See [LICENSE](LICENSE) for details.

---

**Built by [Nadir](https://github.com/divinec0de)** — because booking plugins shouldn't need 47 add-ons to do the basics.
