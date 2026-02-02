# License Pod Schema Extension for NFT Support

## Overview

To support NFT minting for licenses, the following fields need to be added to the `license` Pod in WordPress Admin.

## New Fields to Add

Navigate to: **Pods Admin > Edit Pods > license > Add Field**

### 1. NFT Asset ID
- **Field Name:** `nft_asset_id`
- **Field Type:** Text
- **Description:** The Cardano asset ID of the minted NFT
- **Required:** No

### 2. NFT Transaction Hash
- **Field Name:** `nft_transaction_hash`
- **Field Type:** Text
- **Description:** The blockchain transaction hash of the NFT mint
- **Required:** No

### 3. NFT Status
- **Field Name:** `nft_status`
- **Field Type:** Text (or Select dropdown)
- **Options (if select):**
  - `none` - No NFT requested
  - `pending` - NFT mint requested, awaiting processing
  - `minting` - NFT mint in progress
  - `minted` - NFT successfully minted
  - `failed` - NFT mint failed
- **Default:** `none`
- **Required:** No

### 4. NFT Minted At
- **Field Name:** `nft_minted_at`
- **Field Type:** Date/Time
- **Description:** Timestamp when the NFT was successfully minted
- **Required:** No

### 5. Wallet Address
- **Field Name:** `wallet_address`
- **Field Type:** Text
- **Description:** The Cardano wallet address to receive the NFT
- **Required:** No (required only if minting NFT)

### 6. Mint as NFT Flag
- **Field Name:** `mint_as_nft`
- **Field Type:** Boolean (Yes/No)
- **Description:** Whether the licensee requested an NFT version
- **Default:** No
- **Required:** No

## Existing License Fields (Reference)

The license Pod already has these fields:
- `user` - Relationship to user
- `song` - Relationship to song
- `datetime` - License creation date
- `license_url` - S3 URL of the PDF
- `licensor` - Name of licensor
- `project` - Project name
- `description_of_usage` - Usage description
- `legal_name` - Legal name

## SQL Alternative (Advanced)

If you prefer to add fields via SQL (not recommended for Pods):

```sql
-- Note: Pods stores custom fields in post meta, not separate columns
-- Use the WordPress admin or Pods API instead
```

## Pods API Alternative

To add fields programmatically:

```php
// Add this to theme activation or a setup function
function fml_extend_license_pod_schema() {
    if (!function_exists('pods_api')) {
        return;
    }

    $api = pods_api();
    $pod = $api->load_pod(['name' => 'license']);

    if (!$pod) {
        return;
    }

    $fields_to_add = [
        [
            'name' => 'nft_asset_id',
            'label' => 'NFT Asset ID',
            'type' => 'text',
            'description' => 'Cardano asset ID of the minted NFT'
        ],
        [
            'name' => 'nft_transaction_hash',
            'label' => 'NFT Transaction Hash',
            'type' => 'text',
            'description' => 'Blockchain transaction hash'
        ],
        [
            'name' => 'nft_status',
            'label' => 'NFT Status',
            'type' => 'pick',
            'pick_format_type' => 'single',
            'pick_format_single' => 'dropdown',
            'data' => [
                'none' => 'None',
                'pending' => 'Pending',
                'minting' => 'Minting',
                'minted' => 'Minted',
                'failed' => 'Failed'
            ],
            'default' => 'none'
        ],
        [
            'name' => 'nft_minted_at',
            'label' => 'NFT Minted At',
            'type' => 'datetime'
        ],
        [
            'name' => 'wallet_address',
            'label' => 'Wallet Address',
            'type' => 'text',
            'description' => 'Cardano wallet address for NFT delivery'
        ],
        [
            'name' => 'mint_as_nft',
            'label' => 'Mint as NFT',
            'type' => 'boolean',
            'default' => 0
        ]
    ];

    foreach ($fields_to_add as $field) {
        // Check if field exists
        $existing = $api->load_field([
            'pod' => 'license',
            'name' => $field['name']
        ]);

        if (!$existing) {
            $field['pod'] = 'license';
            $api->save_field($field);
        }
    }
}
add_action('init', 'fml_extend_license_pod_schema', 20);
```
