# Sync.Land

A WordPress-based music licensing platform with CC-BY licensing, blockchain (Cardano/NMKR) NFT verification, Stripe payments, and a persistent music player.

## Repository Structure

This repo lives at the WordPress site root and tracks two components:

```
public/                          # WordPress site root (git root)
├── .gitignore                   # Whitelists only theme + plugin dirs
├── wp-content/
│   ├── themes/
│   │   └── hello-elementor-child-sync-land/   # Theme
│   └── plugins/
│       └── fml-music-player/                  # Music player plugin
```

Everything else (WordPress core, other plugins, uploads) is gitignored.

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
2. Upload the theme to `wp-content/themes/`
3. Upload the `fml-music-player` plugin to `wp-content/plugins/` and activate it
4. Configure API credentials in `wp-config.php` (see Configuration section)
5. Activate the theme
6. Generate API keys at **Settings > API Keys** for external applications

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

---

## FML Music Player Plugin

**Path:** `wp-content/plugins/fml-music-player/`

A sticky-footer music player powered by [Amplitude.js](https://521dimensions.com/open-source/amplitudejs). Renders via the `[fml_music_player]` shortcode (typically placed in Elementor's sticky footer template).

### Features

- **Amplitude.js playback** - play, pause, next, previous, shuffle, repeat
- **Queue management** - dynamic queue panel with drag-reorder, "Play All" and "Play Now" support from song listings
- **Session persistence** - current song, queue, and playback position survive page navigation via localStorage
- **PJAX navigation** - seamless page transitions without interrupting audio playback
- **Dark mode** - respects CSS custom properties (`--player-bg`, `--player-text`, etc.)
- **Artist/album links** - clickable metadata in the player linking to artist and album pages
- **License button** - direct link to the currently playing song's license page
- **Audio visualizer** - frequency analysis data exposed via `window.FMLAudioData` for integration with visual effects (background particles in the theme)
- **Volume control** - slider with mute/unmute toggle
- **Responsive design** - adapts to mobile and desktop viewports

### Plugin Structure

```
fml-music-player/
├── fml-music-player.php          # Plugin entry point, shortcode, asset loading
├── rest-api.php                  # REST API route registration
├── music-player/
│   ├── css/
│   │   └── music-player.css      # Player styles
│   ├── img/                      # Player control icons (SVG)
│   └── js/
│       ├── amplitudejs/
│       │   └── amplitude.js      # Amplitude.js library
│       ├── music-player.js       # Main player logic (queue, localStorage, UI)
│       └── player-visualizer.js  # Audio analyser bridge
└── wavesurfer-player.js-master/  # WaveSurfer reference (not actively loaded)
```

### Shortcode

```
[fml_music_player]
```

Place this in your Elementor sticky footer widget to render the player controls, progress bar, queue panel, and visualizer toggle.

### How Queue / Playback Works

1. Song listings on the site call `window.playNow(songData)` or `window.playAll(songsArray)` (defined in `music-player.js`).
2. Amplitude.js is initialized (or re-initialized) with the new queue.
3. On `beforeunload`, the full queue and playback position are saved to localStorage.
4. On the next page load, the player restores the queue and seeks to the saved position.
5. With PJAX enabled (in the theme's `pjax-navigation.js`), page transitions swap content without reloading, so audio continues uninterrupted.

---

## Theme

**Path:** `wp-content/themes/hello-elementor-child-sync-land/`

Child theme of Hello Elementor providing the full Sync.Land frontend.

### Theme Directory Structure

```
hello-elementor-child-sync-land/
├── assets/
│   ├── css/          # Stylesheets (main, search, forms, my-account, etc.)
│   └── js/           # JavaScript (PJAX navigation, search, particles, tables, etc.)
├── docs/
│   ├── api-spec.yaml         # OpenAPI 3.0 specification
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

This project is designed to work with Local by Flywheel for local WordPress development.

### Security Notes

- Never commit credentials to version control
- All API keys should be in `wp-config.php`
- Use environment variables in production
- API rate limiting is enabled by default

## License

Proprietary - Sync.Land / Awen LLC
