# Sync.Land Theme

A WordPress child theme for Sync.Land - a music licensing platform with CC-BY licensing and blockchain (Cardano/NMKR) NFT verification.

## Requirements

- WordPress 6.0+
- PHP 8.0+
- Hello Elementor parent theme
- Pods plugin (for custom post types)
- Gravity Forms (for license generation)
- JWT Authentication for WP REST API (optional, for external API access)
  - Plugin: https://wordpress.org/plugins/jwt-authentication-for-wp-rest-api/

## Installation

1. Install and activate the Hello Elementor parent theme
2. Upload this theme to `wp-content/themes/`
3. Configure API credentials in `wp-config.php` (see Configuration section)
4. Activate the theme
5. Generate API keys at **Settings > API Keys** for external applications

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

// Stripe Payment Configuration
define( 'FML_STRIPE_SECRET_KEY', 'sk_test_...' ); // or sk_live_...
define( 'FML_STRIPE_PUBLISHABLE_KEY', 'pk_test_...' );
define( 'FML_STRIPE_WEBHOOK_SECRET', 'whsec_...' );

// API Rate Limiting (optional)
define( 'FML_API_RATE_LIMIT', 100 );      // Requests per hour
define( 'FML_API_RATE_WINDOW', 3600 );    // Window in seconds

// CORS Configuration (optional)
define( 'FML_CORS_ALLOWED_ORIGINS', 'https://app.sync.land,https://your-app.com' );

// JWT Authentication (optional, for external API access)
define( 'JWT_AUTH_SECRET_KEY', 'your-secret-key' );
define( 'JWT_AUTH_CORS_ENABLE', true );
```

## API Authentication

### API Key (Recommended for External Apps)

Generate API keys at **Settings > API Keys** in WordPress admin.

Include in requests:
```bash
curl -H "X-API-Key: fml_your_api_key_here" \
     https://sync.land/wp-json/FML/v1/songs/123
```

### WordPress Nonce (Internal)

For same-origin requests, use the `_wpnonce` parameter.

### Rate Limiting

- Default: 100 requests per hour
- Headers: `X-RateLimit-Limit`, `X-RateLimit-Remaining`

Full authentication guide: `docs/api-authentication.md`

## API Endpoints

### External API (v1.1)

Hardened endpoints for external applications:

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/wp-json/FML/v1/status` | GET | None | API health check |
| `/wp-json/FML/v1/songs` | GET | Public | Search songs with filters |
| `/wp-json/FML/v1/songs/{id}` | GET | Public | Get song by ID |
| `/wp-json/FML/v1/songs/{id}/licenses` | GET | API Key | Get licenses for song |
| `/wp-json/FML/v1/artists/{id}` | GET | Public | Get artist by ID |
| `/wp-json/FML/v1/artists/{id}/songs` | GET | Public | Get artist's songs |
| `/wp-json/FML/v1/albums/{id}` | GET | Public | Get album by ID |
| `/wp-json/FML/v1/albums/{id}/songs` | GET | Public | Get album's songs |
| `/wp-json/FML/v1/licenses/{id}` | GET | API Key | Get license by ID |
| `/wp-json/FML/v1/licenses/request` | POST | API Key | Request CC-BY license |
| `/wp-json/FML/v1/licenses/my` | GET | User | Get current user's licenses |
| `/wp-json/FML/v1/licenses/{id}/mint-nft` | POST | API Key | Mint license as NFT |
| `/wp-json/FML/v1/licenses/{id}/nft-status` | GET | API Key | Get NFT status |
| `/wp-json/FML/v1/licenses/{id}/payment-status` | GET | API Key | Get payment status |
| `/wp-json/FML/v1/stripe/create-checkout` | POST | User | Create Stripe checkout |
| `/wp-json/FML/v1/stripe/webhook` | POST | Stripe | Stripe webhook handler |
| `/wp-json/FML/v1/api-keys` | GET/POST | Admin | Manage API keys |

### Legacy Endpoints

Internal endpoints (deprecated, use external API instead):

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
- Use **External API** for external applications (with API key auth)
- Use **Pods REST API** for standard CRUD operations
- Use **Legacy endpoints** for internal WordPress frontend only

Full API documentation: `docs/api-spec.yaml` (OpenAPI 3.0)

## Directory Structure

```
hello-elementor-child-sync-land/
├── assets/
│   ├── css/          # Stylesheets
│   └── js/           # JavaScript files
├── docs/
│   ├── api-spec.yaml         # OpenAPI specification
│   ├── api-authentication.md # Auth guide
│   ├── stripe-setup.md       # Stripe integration
│   └── pods-schema-nft-fields.md
├── functions/
│   ├── api/          # REST API endpoints
│   │   ├── security.php   # API auth & rate limiting
│   │   ├── external.php   # External API endpoints
│   │   ├── licensing.php
│   │   ├── playlists.php
│   │   ├── songs.php
│   │   └── stripe.php
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
- `license` - License records (with NFT and payment fields)
- `playlist` - User playlists

## Admin Pages

- **Settings > Sync.Land Licensing** - License pricing configuration
- **Settings > API Keys** - Manage API keys for external apps

## Development

### Local Development

This theme is designed to work with Local by Flywheel for local WordPress development.

### Security Notes

- Never commit credentials to version control
- All API keys should be in `wp-config.php`
- Use environment variables in production
- API rate limiting is enabled by default
- SQL injection vulnerabilities have been fixed

## License

Proprietary - Sync.Land / Awen LLC
