# Stripe Payment Integration Setup

## Overview

Sync.Land uses Stripe for processing payments for **paid Non-Exclusive Sync Licenses**.

## License Types

| License Type | Price | NFT Available | Description |
|--------------|-------|---------------|-------------|
| **CC-BY 4.0** | Free | Yes (free mint) | Creative Commons Attribution license |
| **Non-Exclusive Sync** | Paid (default $49) | No | Commercial sync license for media projects |

The NFT is a blockchain verification of the CC-BY license - it's free, users just need to mint it.

## Stripe Account Setup

1. Create a Stripe account at https://stripe.com
2. Get your API keys from https://dashboard.stripe.com/apikeys
3. For testing, use test mode keys (sk_test_... and pk_test_...)

## WordPress Configuration

Add these constants to `wp-config.php`:

```php
// Stripe Payment Configuration
define( 'FML_STRIPE_SECRET_KEY', 'sk_test_...' );
define( 'FML_STRIPE_PUBLISHABLE_KEY', 'pk_test_...' );
define( 'FML_STRIPE_WEBHOOK_SECRET', 'whsec_...' );
```

## Webhook Configuration

1. Go to Stripe Dashboard > Developers > Webhooks
2. Add endpoint: `https://your-domain.com/wp-json/FML/v1/stripe/webhook`
3. Select events to listen for:
   - `checkout.session.completed`
   - `payment_intent.succeeded`
   - `payment_intent.payment_failed`
4. Copy the signing secret (whsec_...) to your wp-config.php

## Admin Settings

Go to **Settings > Sync.Land Licensing** in WordPress admin to:

1. Set the non-exclusive license price (in cents USD)
2. View configuration status

## API Endpoints

### Create Checkout Session (for Non-Exclusive License)

```
POST /wp-json/FML/v1/stripe/create-checkout
```

Parameters:
- `song_id` (required): The song to license
- `licensee_name` (optional): Name of licensee
- `project_name` (optional): Name of project
- `usage_description` (optional): How the music will be used

Returns:
```json
{
  "success": true,
  "checkout_url": "https://checkout.stripe.com/...",
  "session_id": "cs_..."
}
```

### Webhook Endpoint

```
POST /wp-json/FML/v1/stripe/webhook
```

Automatically processes:
- Creates non-exclusive license PDF
- Stores license record in database
- Sends confirmation email to user

## Payment Flow

1. User browses song and clicks "Buy Non-Exclusive License"
2. Frontend calls `/stripe/create-checkout` with song_id and license details
3. User is redirected to Stripe Checkout
4. User completes payment
5. Stripe sends webhook to `/stripe/webhook`
6. Webhook handler:
   - Generates non-exclusive license PDF
   - Uploads to S3
   - Creates license record
   - Emails user with download link
7. User is redirected to success URL

## Testing

Use Stripe test cards:
- **Success:** 4242 4242 4242 4242
- **Decline:** 4000 0000 0000 0002
- **Requires auth:** 4000 0025 0000 3155

Test webhook locally using Stripe CLI:
```bash
stripe listen --forward-to localhost:10018/wp-json/FML/v1/stripe/webhook
```

## Going Live

1. Switch to live API keys in wp-config.php
2. Update webhook endpoint in Stripe Dashboard
3. Test with a real (small amount) transaction
4. Monitor Stripe Dashboard for webhook delivery status

## License Pod Schema Extension

Add these fields to the `license` Pod for paid licenses:

- `license_type` - Text: "cc_by" or "non_exclusive"
- `stripe_payment_id` - Text: Stripe payment intent ID
- `stripe_payment_status` - Text: "pending", "completed", "failed"
- `payment_amount` - Number: Amount in cents
- `payment_currency` - Text: Currency code (USD)
