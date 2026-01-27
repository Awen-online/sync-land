# Sync.Land Theme

A WordPress child theme for Sync.Land - a music licensing platform with CC-BY licensing and blockchain (Cardano/NMKR) NFT verification.

## Requirements

- WordPress 6.0+
- PHP 8.0+
- Hello Elementor parent theme
- Pods plugin (for custom post types)
- Gravity Forms (for license generation)
- JWT Authentication for WP REST API (for external API access)
  - Plugin: https://wordpress.org/plugins/jwt-authentication-for-wp-rest-api/

## Installation

1. Install and activate the Hello Elementor parent theme
2. Upload this theme to `wp-content/themes/`
3. Configure API credentials in `wp-config.php` (see Configuration section)
4. Install and configure JWT Authentication plugin for external API access
5. Activate the theme

## Configuration

Add the following constants to your `wp-config.php`:

```php
// AWS/DreamObjects S3 Configuration
define( 'FML_AWS_KEY', 'your-aws-key' );
define( 'FML_AWS_SECRET_KEY', 'your-aws-secret' );
define( 'FML_AWS_HOST', 'https://objects-us-east-1.dream.io' );
define( 'FML_AWS_REGION', 'us-east-1' );

// NMKR (NFT Minting) Configuration
define( 'FML_NMKR_API_KEY', 'your-nmkr-api-key' );
define( 'FML_NMKR_PROJECT_UID', 'your-project-uid' );
define( 'FML_NMKR_POLICY_ID', 'your-policy-id' );
define( 'FML_NMKR_API_URL', 'https://studio-api.nmkr.io' ); // Use preprod URL for testing

// JWT Authentication (required for external API access)
define( 'JWT_AUTH_SECRET_KEY', 'your-secret-key' );
define( 'JWT_AUTH_CORS_ENABLE', true );

// Stripe Payment Configuration
define( 'FML_STRIPE_SECRET_KEY', 'sk_test_...' ); // or sk_live_...
define( 'FML_STRIPE_PUBLISHABLE_KEY', 'pk_test_...' );
define( 'FML_STRIPE_WEBHOOK_SECRET', 'whsec_...' );
```

## API Endpoints

### Custom Endpoints (FML/v1)

Business logic endpoints for licensing, uploads, and NFT minting:

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/wp-json/FML/v1/PDF_license_generator/` | POST | Generate CC-BY license PDF |
| `/wp-json/FML/v1/song-search` | GET | Search songs |
| `/wp-json/FML/v1/song-upload` | POST | Upload song to S3 |
| `/wp-json/FML/v1/playlists/` | GET | Get user playlists |
| `/wp-json/FML/v1/playlists/add` | POST | Create playlist |
| `/wp-json/FML/v1/playlists/edit` | POST | Edit playlist |
| `/wp-json/FML/v1/playlists/delete` | POST | Delete playlist |
| `/wp-json/FML/v1/playlists/addsong` | POST | Add song to playlist |
| `/wp-json/FML/v1/licenses/{id}/mint-nft` | POST | Mint license as NFT |
| `/wp-json/FML/v1/licenses/{id}/nft-status` | GET | Get NFT status for license |
| `/wp-json/FML/v1/licenses/{id}/payment-status` | GET | Get payment status |
| `/wp-json/FML/v1/stripe/create-checkout` | POST | Create Stripe checkout session |
| `/wp-json/FML/v1/stripe/webhook` | POST | Stripe webhook handler |

### Pods REST API (Alternative)

WordPress Pods plugin provides a built-in REST API for standard CRUD operations.
Enable in **Pods Admin > Settings > REST API**.

| Endpoint | Description |
|----------|-------------|
| `/wp-json/pods/v1/song/` | Songs CRUD |
| `/wp-json/pods/v1/artist/` | Artists CRUD |
| `/wp-json/pods/v1/album/` | Albums CRUD |
| `/wp-json/pods/v1/license/` | Licenses CRUD |
| `/wp-json/pods/v1/playlist/` | Playlists CRUD |

**API Strategy:**
- Use **Pods REST API** for standard CRUD operations on content types
- Use **FML/v1 custom endpoints** for business logic (license generation, S3 uploads, NFT minting)

Full API documentation: `docs/api-spec.yaml` (OpenAPI 3.0)

## Directory Structure

```
hello-elementor-child-sync-land/
├── assets/
│   ├── css/          # Stylesheets
│   └── js/           # JavaScript files
├── docs/
│   └── api-spec.yaml # OpenAPI specification
├── functions/
│   ├── api/          # REST API endpoints
│   │   ├── licensing.php
│   │   ├── playlists.php
│   │   └── songs.php
│   ├── gravityforms/ # Gravity Forms integrations
│   ├── shortcodes/   # Custom shortcodes
│   ├── nmkr.php      # NMKR NFT minting
│   └── ...
├── php/
│   └── aws/          # AWS SDK
├── user-registration/
│   └── myaccount/    # User account templates
├── functions.php     # Main theme functions
└── style.css         # Theme stylesheet
```

## Custom Post Types (Pods)

- `song` - Music tracks
- `artist` - Artists/musicians
- `album` - Album collections
- `license` - License records
- `playlist` - User playlists

## Development

### Local Development

This theme is designed to work with Local by Flywheel for local WordPress development.

### Security Notes

- Never commit credentials to version control
- All API keys should be in `wp-config.php`
- Use environment variables in production
- JWT secret key should be unique and secure

## License

Proprietary - Sync.Land / Awen LLC
