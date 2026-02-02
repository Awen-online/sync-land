# Sync.Land API Authentication Guide

## Overview

The Sync.Land API supports two authentication methods:
1. **API Key Authentication** - For external applications
2. **WordPress Session** - For logged-in WordPress users

## API Key Authentication

### Obtaining an API Key

1. Go to **WordPress Admin > Settings > API Keys**
2. Enter your application name
3. Click "Generate API Key"
4. **Copy the key immediately** - it will only be shown once

### Using the API Key

Include the API key in the `X-API-Key` header:

```bash
curl -H "X-API-Key: fml_your_api_key_here" \
     https://sync.land/wp-json/FML/v1/songs/123
```

### JavaScript Example

```javascript
const response = await fetch('https://sync.land/wp-json/FML/v1/songs', {
  headers: {
    'X-API-Key': 'fml_your_api_key_here',
    'Content-Type': 'application/json'
  }
});
const data = await response.json();
```

### Python Example

```python
import requests

headers = {
    'X-API-Key': 'fml_your_api_key_here'
}
response = requests.get('https://sync.land/wp-json/FML/v1/songs', headers=headers)
data = response.json()
```

## WordPress Session Authentication

For WordPress users (internal frontend), authentication is handled via cookies and nonces:

```javascript
// The nonce is provided via wp_localize_script
fetch('/wp-json/FML/v1/licenses/my', {
  headers: {
    'X-WP-Nonce': wpApiSettings.nonce
  },
  credentials: 'same-origin'
});
```

## JWT Authentication (Alternative)

For stateless authentication, install the [JWT Authentication for WP REST API](https://wordpress.org/plugins/jwt-authentication-for-wp-rest-api/) plugin.

### Configuration

Add to `wp-config.php`:
```php
define('JWT_AUTH_SECRET_KEY', 'your-secret-key');
define('JWT_AUTH_CORS_ENABLE', true);
```

### Getting a Token

```bash
curl -X POST https://sync.land/wp-json/jwt-auth/v1/token \
     -d "username=your_username" \
     -d "password=your_password"
```

Response:
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "user_email": "user@example.com",
  "user_nicename": "username",
  "user_display_name": "User Name"
}
```

### Using the Token

```bash
curl -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..." \
     https://sync.land/wp-json/FML/v1/licenses/my
```

## Rate Limiting

All API endpoints are rate limited:
- **Default**: 100 requests per hour
- **Configurable**: Set `FML_API_RATE_LIMIT` in wp-config.php

Rate limit headers are included in all responses:
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
```

When rate limited, you'll receive a `429 Too Many Requests` response.

## CORS Configuration

For cross-origin requests from external applications, add allowed origins to `wp-config.php`:

```php
define('FML_CORS_ALLOWED_ORIGINS', 'https://app.sync.land,https://your-app.com');
```

In development mode (`WP_DEBUG = true`), localhost origins are automatically allowed.

## Endpoint Permission Levels

| Endpoint | Authentication Required |
|----------|------------------------|
| `GET /songs` | Public (rate limited) |
| `GET /songs/{id}` | Public (rate limited) |
| `GET /artists/{id}` | Public (rate limited) |
| `GET /albums/{id}` | Public (rate limited) |
| `GET /licenses/{id}` | API Key or User Session |
| `GET /licenses/my` | User Session only |
| `POST /licenses/request` | API Key or User Session |
| `POST /stripe/create-checkout` | User Session only |
| `POST /licenses/{id}/mint-nft` | API Key or User Session |
| `POST /api-keys` | Admin only |

## Error Responses

Authentication errors return standard error format:

```json
{
  "success": false,
  "error": {
    "code": "unauthorized",
    "message": "Invalid or missing API key"
  }
}
```

Common error codes:
- `unauthorized` (401) - Missing or invalid authentication
- `forbidden` (403) - Valid auth but insufficient permissions
- `rate_limit_exceeded` (429) - Too many requests

## Security Best Practices

1. **Never expose API keys in client-side code** - Use a backend proxy
2. **Use HTTPS** - All API requests should use HTTPS
3. **Rotate keys periodically** - Revoke and regenerate keys regularly
4. **Monitor usage** - Check the API Keys admin page for request counts
5. **Use least privilege** - Request only the permissions you need
