<?php
/**
 * My Licenses Template - Enhanced Dark Mode Version
 * Displays user's music licenses with NFT verification status
 */

$current_user = wp_get_current_user();

// Check for payment success
$payment_success = isset($_GET['payment']) && $_GET['payment'] === 'success';
$session_id = isset($_GET['session_id']) ? sanitize_text_field($_GET['session_id']) : '';

// Get all licenses for the logged in user
$paramsLicenses = array(
    'where' => "user.id = '" . $current_user->ID . "' AND t.post_status = 'publish'",
    "orderby" => "datetime.meta_value DESC",
    "limit" => -1  // Get all licenses, not just the default 15
);
$licenses = pods('license', $paramsLicenses);
$total_licenses = $licenses->total();

// Get counts by type - do a pre-count pass
$cc_by_count = 0;
$commercial_count = 0;
$nft_verified_count = 0;
$nft_pending_count = 0;

// Store licenses in array for re-use
$licenses_array = [];
while ($licenses->fetch()) {
    // Handle license_type - might be array, string, or empty
    $raw_license_type = $licenses->field('license_type');
    if (is_array($raw_license_type)) {
        $license_type = !empty($raw_license_type) ? $raw_license_type[0] : 'cc_by';
    } else {
        $license_type = !empty($raw_license_type) ? $raw_license_type : 'cc_by';
    }

    // Handle nft_status - might be array
    $raw_nft_status = $licenses->field('nft_status');
    if (is_array($raw_nft_status)) {
        $nft_status = !empty($raw_nft_status) ? $raw_nft_status[0] : 'none';
    } else {
        $nft_status = !empty($raw_nft_status) ? $raw_nft_status : 'none';
    }

    $license_data = [
        'ID' => $licenses->field('ID'),
        'license_type' => $license_type,
        'nft_status' => $nft_status,
        'nft_transaction_hash' => $licenses->field('nft_transaction_hash'),
        'license_url' => $licenses->field('license_url'),
        'licensor' => $licenses->field('licensor'),
        'project' => $licenses->field('project'),
        'datetime' => $licenses->field('datetime'),
        'song' => $licenses->field('song')
    ];

    // Count by type
    if ($license_type === 'non_exclusive') {
        $commercial_count++;
    } else {
        $cc_by_count++;
    }
    if ($license_data['nft_status'] === 'minted') {
        $nft_verified_count++;
    } elseif (in_array($license_data['nft_status'], ['pending', 'processing', 'ipfs_pending'])) {
        $nft_pending_count++;
    }

    $licenses_array[] = $license_data;
}
?>

<style>
/* My Licenses Page - Dark Mode Styles */
.fml-licenses-container {
    color: #e2e8f0;
}

/* Success Banner */
.fml-payment-success-banner {
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
    color: white;
    padding: 20px 25px;
    border-radius: 12px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 4px 15px rgba(72, 187, 120, 0.3);
    animation: slideDown 0.5s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.fml-payment-success-banner i {
    font-size: 2rem;
}

.fml-payment-success-banner .banner-content h3 {
    margin: 0 0 5px 0;
    font-size: 1.25rem;
}

.fml-payment-success-banner .banner-content p {
    margin: 0;
    opacity: 0.9;
    font-size: 0.95rem;
}

/* Stats Cards */
.fml-license-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.fml-stat-card {
    background: #252540;
    border: 1px solid #404060;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
}

.fml-stat-card .stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: #E7565A;
    display: block;
}

.fml-stat-card .stat-label {
    font-size: 0.85rem;
    color: #a0aec0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.fml-stat-card.nft-verified .stat-number {
    color: #48bb78;
}

/* License Type Badges */
.fml-license-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.fml-license-badge.cc-by {
    background: rgba(99, 179, 237, 0.2);
    color: #63b3ed;
    border: 1px solid rgba(99, 179, 237, 0.3);
}

.fml-license-badge.commercial {
    background: rgba(231, 86, 90, 0.2);
    color: #E7565A;
    border: 1px solid rgba(231, 86, 90, 0.3);
}

/* NFT Status Badges */
.fml-nft-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.fml-nft-badge.verified {
    background: rgba(72, 187, 120, 0.2);
    color: #48bb78;
    border: 1px solid rgba(72, 187, 120, 0.3);
}

.fml-nft-badge.pending {
    background: rgba(246, 224, 94, 0.2);
    color: #f6e05e;
    border: 1px solid rgba(246, 224, 94, 0.3);
    animation: pulse 2s infinite;
}

.fml-nft-badge.processing {
    background: rgba(99, 179, 237, 0.2);
    color: #63b3ed;
    border: 1px solid rgba(99, 179, 237, 0.3);
    animation: pulse 1.5s infinite;
}

.fml-nft-badge.failed {
    background: rgba(245, 101, 101, 0.2);
    color: #fc8181;
    border: 1px solid rgba(245, 101, 101, 0.3);
}

.fml-nft-badge.none {
    background: rgba(160, 174, 192, 0.1);
    color: #718096;
    border: 1px solid rgba(160, 174, 192, 0.2);
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

/* DataTables Dark Mode Override */
#license_table_wrapper {
    color: #e2e8f0;
}

#license_table {
    background: #1e1e32 !important;
    border-collapse: separate;
    border-spacing: 0;
    border-radius: 10px;
    overflow: hidden;
    width: 100% !important;
}

#license_table thead th {
    background: #252540 !important;
    color: #e2e8f0 !important;
    border-bottom: 2px solid #404060 !important;
    padding: 15px 12px !important;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 0.5px;
}

#license_table tbody tr {
    background: #1e1e32 !important;
    transition: background 0.2s ease;
}

#license_table tbody tr:hover {
    background: #252540 !important;
}

#license_table tbody tr.odd {
    background: #1a1a2e !important;
}

#license_table tbody tr.odd:hover {
    background: #252540 !important;
}

#license_table tbody td {
    padding: 12px !important;
    border-bottom: 1px solid #2d2d44 !important;
    color: #e2e8f0 !important;
    vertical-align: middle;
}

#license_table tbody td a {
    color: #E7565A !important;
    text-decoration: none;
}

#license_table tbody td a:hover {
    color: #ff6b6f !important;
    text-decoration: underline;
}

/* DataTables Controls */
.dataTables_wrapper .dataTables_filter input {
    background: #252540 !important;
    border: 1px solid #404060 !important;
    color: #e2e8f0 !important;
    padding: 8px 12px !important;
    border-radius: 6px !important;
    margin-left: 10px;
}

.dataTables_wrapper .dataTables_filter input:focus {
    outline: none;
    border-color: #E7565A !important;
    box-shadow: 0 0 0 2px rgba(231, 86, 90, 0.2);
}

.dataTables_wrapper .dataTables_filter label {
    color: #a0aec0 !important;
}

.dataTables_wrapper .dataTables_info {
    color: #a0aec0 !important;
    padding-top: 15px !important;
}

.dataTables_wrapper .dataTables_paginate {
    padding-top: 15px !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
    background: #252540 !important;
    border: 1px solid #404060 !important;
    color: #e2e8f0 !important;
    border-radius: 6px !important;
    margin: 0 3px !important;
    padding: 5px 12px !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: #E7565A !important;
    border-color: #E7565A !important;
    color: white !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: #E7565A !important;
    border-color: #E7565A !important;
    color: white !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Action Buttons */
.fml-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 500;
    text-decoration: none !important;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
    margin: 2px;
}

.fml-action-btn.pdf {
    background: rgba(231, 86, 90, 0.2);
    color: #E7565A !important;
    border: 1px solid rgba(231, 86, 90, 0.3);
}

.fml-action-btn.pdf:hover {
    background: #E7565A;
    color: white !important;
}

.fml-action-btn.mp3 {
    background: rgba(99, 179, 237, 0.2);
    color: #63b3ed !important;
    border: 1px solid rgba(99, 179, 237, 0.3);
}

.fml-action-btn.mp3:hover {
    background: #63b3ed;
    color: white !important;
}

.fml-action-btn.blockchain {
    background: rgba(72, 187, 120, 0.2);
    color: #48bb78 !important;
    border: 1px solid rgba(72, 187, 120, 0.3);
}

.fml-action-btn.blockchain:hover {
    background: #48bb78;
    color: white !important;
}

/* Empty State */
.fml-empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #252540;
    border-radius: 12px;
    border: 1px solid #404060;
}

.fml-empty-state i {
    font-size: 4rem;
    color: #404060;
    margin-bottom: 20px;
}

.fml-empty-state h3 {
    color: #e2e8f0;
    margin-bottom: 10px;
}

.fml-empty-state p {
    color: #a0aec0;
    margin-bottom: 20px;
}

.fml-empty-state .fml-btn {
    display: inline-block;
    background: #E7565A;
    color: white;
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.2s ease;
}

.fml-empty-state .fml-btn:hover {
    background: #ff6b6f;
    transform: translateY(-2px);
}

/* Song Info Cell */
.fml-song-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.fml-song-thumb {
    width: 40px;
    height: 40px;
    border-radius: 6px;
    object-fit: cover;
    background: #404060;
}

.fml-song-details {
    line-height: 1.3;
}

.fml-song-title {
    font-weight: 600;
    color: #e2e8f0 !important;
}

.fml-song-artist {
    font-size: 0.85rem;
    color: #a0aec0 !important;
}

/* Responsive */
@media (max-width: 768px) {
    .fml-license-stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .fml-action-btn span {
        display: none;
    }

    .fml-action-btn {
        padding: 8px;
    }
}
</style>

<div class="fml-licenses-container">
    <?php if ($payment_success): ?>
    <div class="fml-payment-success-banner">
        <i class="fas fa-check-circle"></i>
        <div class="banner-content">
            <h3>Payment Successful!</h3>
            <p>Your license<?php echo $total_licenses > 1 ? 's have' : ' has'; ?> been generated. <strong>Download your license PDF and song files now</strong> using the buttons below.</p>
            <?php if ($nft_pending_count > 0): ?>
            <p style="margin-top: 8px; font-size: 0.9rem; opacity: 0.95;">
                <i class="fas fa-cube"></i> NFT verification is being processed on the blockchain.
                <span style="display: block; margin-top: 4px; font-size: 0.85rem;">Your downloads are ready now - NFT status will update automatically.</span>
            </p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($total_licenses > 0): ?>

    <script>
    jQuery(document).ready(function() {
        var table = jQuery("#license_table").DataTable({
            "paging": true,
            "lengthChange": true,
            "searching": true,
            "ordering": true,
            "info": true,
            "autoWidth": false,
            "pageLength": 10,
            "language": {
                "search": "Search licenses:",
                "info": "Showing _START_ to _END_ of _TOTAL_ licenses",
                "paginate": {
                    "previous": "<i class='fas fa-chevron-left'></i>",
                    "next": "<i class='fas fa-chevron-right'></i>"
                },
                "emptyTable": "No licenses found"
            },
            "order": [[5, "desc"]],
            "columnDefs": [
                { "orderable": false, "targets": [6] }
            ]
        });
    });
    </script>

    <table id="license_table" class="display dark" style="width:100%">
        <thead>
            <tr>
                <th>Song</th>
                <th>License Type</th>
                <th>Licensee</th>
                <th>Project</th>
                <th>NFT Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php
        foreach ($licenses_array as $license_data) {
            $license_id = $license_data['ID'];
            $license_type = $license_data['license_type'];
            $nft_status = $license_data['nft_status'];
            $nft_tx_hash = $license_data['nft_transaction_hash'];
            $license_url = $license_data['license_url'];
            $licensor = $license_data['licensor'];
            $project = $license_data['project'];
            $datetime = $license_data['datetime'];

            // Get song info
            $song_data = $license_data['song'];
            $song_id = is_array($song_data) ? $song_data['ID'] : $song_data;
            $song_pod = pods('song', $song_id);
            $song_title = $song_pod->field('post_title');
            $song_mp3 = $song_pod->field('audio_url');
            $song_permalink = get_permalink($song_id);

            // Get artist info
            $artist_data = $song_pod->field('artist');
            $artist_name = 'Unknown Artist';
            $artist_permalink = '#';
            if (!empty($artist_data)) {
                $artist_id = is_array($artist_data) ? $artist_data['ID'] : $artist_data;
                $artist_pod = pods('artist', $artist_id);
                if ($artist_pod && $artist_pod->exists()) {
                    $artist_name = $artist_pod->field('post_title');
                    $artist_permalink = get_permalink($artist_id);
                }
            }

            // Get album thumbnail
            $album_data = $song_pod->field('album');
            $thumbnail = '';
            if (!empty($album_data)) {
                $album_id = is_array($album_data) ? $album_data['ID'] : $album_data;
                $thumbnail = get_the_post_thumbnail_url($album_id, 'thumbnail');
            }

            // Format date
            $formatted_date = !empty($datetime) ? date('M j, Y', strtotime($datetime)) : '-';
            $date_sort = !empty($datetime) ? date('Y-m-d', strtotime($datetime)) : '';

            // License type badge
            $license_badge = ($license_type === 'non_exclusive')
                ? '<span class="fml-license-badge commercial">Commercial</span>'
                : '<span class="fml-license-badge cc-by">CC-BY 4.0</span>';

            // NFT status badge
            if ($nft_status === 'minted') {
                $nft_badge = '<span class="fml-nft-badge verified"><i class="fas fa-certificate"></i> Verified</span>';
            } elseif ($nft_status === 'processing') {
                $nft_badge = '<span class="fml-nft-badge processing"><i class="fas fa-spinner fa-spin"></i> Minting...</span>';
            } elseif ($nft_status === 'pending') {
                $nft_badge = '<span class="fml-nft-badge pending"><i class="fas fa-hourglass-half"></i> Queued</span>';
            } elseif ($nft_status === 'ipfs_pending') {
                $nft_badge = '<span class="fml-nft-badge pending" title="NFT submitted, waiting for IPFS confirmation"><i class="fas fa-cloud-upload-alt"></i> Processing</span>';
            } elseif ($nft_status === 'failed') {
                $nft_badge = '<span class="fml-nft-badge failed" title="NFT minting failed - contact support for retry"><i class="fas fa-exclamation-triangle"></i> Failed</span>';
            } else {
                $nft_badge = '<span class="fml-nft-badge none">-</span>';
            }
            ?>
            <tr>
                <td>
                    <div class="fml-song-info">
                        <?php if ($thumbnail): ?>
                        <img src="<?php echo esc_url($thumbnail); ?>" alt="" class="fml-song-thumb">
                        <?php endif; ?>
                        <div class="fml-song-details">
                            <a href="<?php echo esc_url($song_permalink); ?>" class="fml-song-title"><?php echo esc_html($song_title); ?></a>
                            <div class="fml-song-artist"><a href="<?php echo esc_url($artist_permalink); ?>"><?php echo esc_html($artist_name); ?></a></div>
                        </div>
                    </div>
                </td>
                <td><?php echo $license_badge; ?></td>
                <td><?php echo esc_html($licensor); ?></td>
                <td><?php echo esc_html($project ?: '-'); ?></td>
                <td><?php echo $nft_badge; ?></td>
                <td data-sort="<?php echo $date_sort; ?>"><?php echo $formatted_date; ?></td>
                <td>
                    <?php if ($license_url): ?>
                    <a href="<?php echo esc_url($license_url); ?>" class="fml-action-btn pdf" title="Download License PDF" target="_blank">
                        <i class="fas fa-file-pdf"></i><span>PDF</span>
                    </a>
                    <?php endif; ?>

                    <?php if ($song_mp3): ?>
                    <a href="<?php echo esc_url($song_mp3); ?>" class="fml-action-btn mp3" title="Download Song" download>
                        <i class="fas fa-music"></i><span>MP3</span>
                    </a>
                    <?php endif; ?>

                    <?php if ($nft_status === 'minted' && $nft_tx_hash): ?>
                    <a href="https://cardanoscan.io/transaction/<?php echo esc_attr($nft_tx_hash); ?>" class="fml-action-btn blockchain" title="View on Blockchain" target="_blank">
                        <i class="fas fa-link"></i><span>Chain</span>
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
        }
        ?>
        </tbody>
    </table>

    <!-- Stats Cards (populated after counting) -->
    <script>
    jQuery(document).ready(function() {
        // Insert stats after the table is loaded
        var statsHtml = '<div class="fml-license-stats">' +
            '<div class="fml-stat-card"><span class="stat-number"><?php echo $total_licenses; ?></span><span class="stat-label">Total Licenses</span></div>' +
            '<div class="fml-stat-card"><span class="stat-number"><?php echo $cc_by_count; ?></span><span class="stat-label">CC-BY 4.0</span></div>' +
            '<div class="fml-stat-card"><span class="stat-number"><?php echo $commercial_count; ?></span><span class="stat-label">Commercial</span></div>' +
            '<div class="fml-stat-card nft-verified"><span class="stat-number"><?php echo $nft_verified_count; ?></span><span class="stat-label">NFT Verified</span></div>' +
            <?php if ($nft_pending_count > 0): ?>
            '<div class="fml-stat-card nft-pending"><span class="stat-number" style="color: #f6e05e;"><?php echo $nft_pending_count; ?></span><span class="stat-label">NFT Minting</span></div>' +
            <?php endif; ?>
            '</div>';
        jQuery('.fml-licenses-container .fml-payment-success-banner').after(statsHtml);
        if (!jQuery('.fml-payment-success-banner').length) {
            jQuery('.fml-licenses-container').prepend(statsHtml);
        }

        <?php if ($nft_pending_count > 0): ?>
        // Auto-refresh page every 30 seconds if there are pending NFTs
        setTimeout(function() {
            location.reload();
        }, 30000);
        <?php endif; ?>
    });
    </script>

    <?php else: ?>

    <div class="fml-empty-state">
        <i class="fas fa-file-contract"></i>
        <h3>No Licenses Yet</h3>
        <p>You haven't licensed any music yet. Browse our library and find the perfect track for your project!</p>
        <a href="<?php echo home_url('/songs/'); ?>" class="fml-btn">
            <i class="fas fa-music"></i> Browse Music
        </a>
    </div>

    <?php endif; ?>
</div>
