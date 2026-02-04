<?php
/**
 * My Playlists Template - Enhanced Dark Mode Version
 * Displays user's playlists with management features
 */

if (isset($_GET['edit'])) {
    include_once(get_stylesheet_directory() . "/user-registration/myaccount/modules/playlist-edit.php");
} else {

$current_user = wp_get_current_user();

// Get all playlists for the logged in user
$paramsPlaylist = array(
    'where' => "t.post_author = '" . $current_user->ID . "' AND (t.post_status = 'publish' OR t.post_status='private')",
    "orderby" => "t.post_date DESC"
);
$playlists = pods('playlist', $paramsPlaylist);
$total_playlists = $playlists->total();

// Count stats
$public_count = 0;
$private_count = 0;
$total_songs = 0;
?>

<style>
/* My Playlists Page - Dark Mode Styles */
.fml-playlists-container {
    color: #e2e8f0;
}

/* Stats Cards */
.fml-playlist-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
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

.fml-stat-card.public .stat-number {
    color: #48bb78;
}

.fml-stat-card.songs .stat-number {
    color: #63b3ed;
}

/* Visibility Badges */
.fml-visibility-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.fml-visibility-badge.public {
    background: rgba(72, 187, 120, 0.2);
    color: #48bb78;
    border: 1px solid rgba(72, 187, 120, 0.3);
}

.fml-visibility-badge.private {
    background: rgba(160, 174, 192, 0.2);
    color: #a0aec0;
    border: 1px solid rgba(160, 174, 192, 0.3);
}

.fml-visibility-badge.draft {
    background: rgba(246, 224, 94, 0.2);
    color: #f6e05e;
    border: 1px solid rgba(246, 224, 94, 0.3);
}

/* DataTables Dark Mode */
#playlist_table_wrapper {
    color: #e2e8f0;
}

#playlist_table {
    background: #1e1e32 !important;
    border-collapse: separate;
    border-spacing: 0;
    border-radius: 10px;
    overflow: hidden;
    width: 100% !important;
}

#playlist_table thead th {
    background: #252540 !important;
    color: #e2e8f0 !important;
    border-bottom: 2px solid #404060 !important;
    padding: 15px 12px !important;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 0.5px;
}

#playlist_table tbody tr {
    background: #1e1e32 !important;
    transition: background 0.2s ease;
}

#playlist_table tbody tr:hover {
    background: #252540 !important;
}

#playlist_table tbody tr.odd {
    background: #1a1a2e !important;
}

#playlist_table tbody tr.odd:hover {
    background: #252540 !important;
}

#playlist_table tbody td {
    padding: 12px !important;
    border-bottom: 1px solid #2d2d44 !important;
    color: #e2e8f0 !important;
    vertical-align: middle;
}

#playlist_table tbody td a {
    color: #E7565A !important;
    text-decoration: none;
}

#playlist_table tbody td a:hover {
    color: #ff6b6f !important;
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

/* Action Buttons */
.fml-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 500;
    text-decoration: none !important;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
    margin: 2px;
}

.fml-action-btn.view {
    background: rgba(99, 179, 237, 0.2);
    color: #63b3ed !important;
    border: 1px solid rgba(99, 179, 237, 0.3);
}

.fml-action-btn.view:hover {
    background: #63b3ed;
    color: white !important;
}

.fml-action-btn.edit {
    background: rgba(246, 224, 94, 0.2);
    color: #f6e05e !important;
    border: 1px solid rgba(246, 224, 94, 0.3);
}

.fml-action-btn.edit:hover {
    background: #f6e05e;
    color: #1a1a2e !important;
}

.fml-action-btn.delete {
    background: rgba(252, 129, 129, 0.2);
    color: #fc8181 !important;
    border: 1px solid rgba(252, 129, 129, 0.3);
}

.fml-action-btn.delete:hover {
    background: #fc8181;
    color: white !important;
}

/* Playlist Name Cell */
.fml-playlist-name {
    font-weight: 600;
}

.fml-playlist-name a {
    color: #e2e8f0 !important;
}

.fml-playlist-name a:hover {
    color: #E7565A !important;
}

/* Song Count Badge */
.fml-song-count {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    background: rgba(99, 179, 237, 0.15);
    border-radius: 20px;
    font-size: 0.85rem;
    color: #63b3ed;
}

.fml-song-count i {
    font-size: 0.75rem;
}

/* Add Playlist Form */
.fml-add-playlist-section {
    margin-top: 30px;
    padding: 25px;
    background: #252540;
    border: 1px solid #404060;
    border-radius: 12px;
}

.fml-add-playlist-section h3 {
    margin: 0 0 20px 0;
    color: #e2e8f0;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.fml-add-playlist-section h3 i {
    color: #E7565A;
}

.fml-add-playlist-form {
    display: flex;
    gap: 15px;
    align-items: flex-end;
    flex-wrap: wrap;
}

.fml-form-group {
    flex: 1;
    min-width: 200px;
}

.fml-form-group label {
    display: block;
    margin-bottom: 8px;
    color: #a0aec0;
    font-size: 0.85rem;
    font-weight: 500;
}

.fml-form-group input[type="text"] {
    width: 100%;
    padding: 12px 15px;
    background: #1a1a2e;
    border: 1px solid #404060;
    border-radius: 8px;
    color: #e2e8f0;
    font-size: 0.95rem;
    transition: all 0.2s ease;
}

.fml-form-group input[type="text"]:focus {
    outline: none;
    border-color: #E7565A;
    box-shadow: 0 0 0 3px rgba(231, 86, 90, 0.2);
}

.fml-form-group input[type="text"]::placeholder {
    color: #718096;
}

.fml-submit-btn {
    padding: 12px 24px;
    background: #E7565A;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.fml-submit-btn:hover {
    background: #ff6b6f;
    transform: translateY(-2px);
}

/* Empty State */
.fml-empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #252540;
    border-radius: 12px;
    border: 1px solid #404060;
    margin-bottom: 30px;
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
    margin-bottom: 0;
}

/* Responsive */
@media (max-width: 768px) {
    .fml-playlist-stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .fml-add-playlist-form {
        flex-direction: column;
    }

    .fml-form-group {
        width: 100%;
    }

    .fml-action-btn span {
        display: none;
    }

    .fml-action-btn {
        padding: 8px;
    }
}
</style>

<div class="fml-playlists-container">
    <?php if ($total_playlists > 0): ?>

    <script>
    jQuery(document).ready(function($) {
        // Initialize DataTable
        var table = $('#playlist_table').DataTable({
            "paging": true,
            "lengthChange": false,
            "searching": true,
            "ordering": true,
            "info": true,
            "autoWidth": false,
            "pageLength": 10,
            "language": {
                "search": "Search playlists:",
                "info": "Showing _START_ to _END_ of _TOTAL_ playlists",
                "paginate": {
                    "previous": "<i class='fas fa-chevron-left'></i>",
                    "next": "<i class='fas fa-chevron-right'></i>"
                }
            },
            "order": [[0, "desc"]],
            "columnDefs": [
                { "orderable": false, "targets": [4] }
            ]
        });

        // Add Playlist AJAX
        $('form[name="add-playlist"]').submit(function(event) {
            event.preventDefault();

            var form = {};
            $.each($(this).serializeArray(), function() {
                form[this.name] = this.value;
            });

            var $btn = $(this).find('button[type="submit"]');
            var originalText = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Creating...');

            $.ajax({
                url: "/wp-json/FML/v1/playlists/add",
                type: "POST",
                data: {
                    _wpnonce: '<?php echo wp_create_nonce("wp_rest"); ?>',
                    playlist_name: form["playlistname"],
                    isprivate: form["isprivate"],
                    user_id: <?php echo $current_user->ID; ?>
                },
                dataType: "json"
            }).done(function(response) {
                if (response.success) {
                    var playlistID = response.playlistID;
                    var visibility = form["isprivate"] === "on"
                        ? '<span class="fml-visibility-badge private"><i class="fas fa-lock"></i> Private</span>'
                        : '<span class="fml-visibility-badge draft"><i class="fas fa-eye-slash"></i> Draft</span>';

                    var actions = '<a href="?edit&playlistID=' + playlistID + '" class="fml-action-btn edit" title="Edit"><i class="fas fa-edit"></i></a>' +
                        '<button class="fml-action-btn delete playlist-delete" data-playlistid="' + playlistID + '" title="Delete"><i class="fas fa-trash-alt"></i></button>';

                    table.row.add([
                        playlistID,
                        '<span class="fml-playlist-name">' + form["playlistname"] + '</span>',
                        visibility,
                        '<span class="fml-song-count"><i class="fas fa-music"></i> 0</span>',
                        actions
                    ]).draw();

                    $('input[name="playlistname"]').val('');

                    // Update stats
                    updateStats();
                } else {
                    alert('Failed to create playlist');
                }
            }).fail(function() {
                alert('Request failed');
            }).always(function() {
                $btn.prop('disabled', false).html(originalText);
            });

            return false;
        });

        // Delete Playlist
        $(document).on('click', 'button.playlist-delete', function() {
            if (!confirm('Are you sure you want to delete this playlist? This cannot be undone.')) {
                return;
            }

            var $btn = $(this);
            var playlistID = $btn.data('playlistid');
            var $row = $btn.closest('tr');

            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            $.ajax({
                url: "/wp-json/FML/v1/playlists/delete",
                type: "POST",
                data: {
                    _wpnonce: '<?php echo wp_create_nonce("wp_rest"); ?>',
                    playlistID: playlistID,
                    user_id: <?php echo $current_user->ID; ?>
                },
                dataType: "json"
            }).done(function(response) {
                if (response.success) {
                    table.row($row).remove().draw();
                    updateStats();
                } else {
                    alert('Failed to delete playlist');
                    $btn.prop('disabled', false).html('<i class="fas fa-trash-alt"></i>');
                }
            }).fail(function() {
                alert('Request failed');
                $btn.prop('disabled', false).html('<i class="fas fa-trash-alt"></i>');
            });
        });

        function updateStats() {
            // Recalculate stats from table
            var total = table.rows().count();
            var publicCount = 0;
            var privateCount = 0;

            table.rows().every(function() {
                var data = this.data();
                if (data[2] && data[2].includes('public')) publicCount++;
                if (data[2] && data[2].includes('private')) privateCount++;
            });

            $('.stat-total').text(total);
            $('.stat-public').text(publicCount);
            $('.stat-private').text(privateCount);
        }
    });
    </script>

    <table id="playlist_table" class="display dark" style="width:100%">
        <thead>
            <tr>
                <th>ID</th>
                <th>Playlist Name</th>
                <th>Visibility</th>
                <th>Songs</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php
        while ($playlists->fetch()) {
            $playlistID = $playlists->field('ID');
            $playlistName = $playlists->field('post_title');
            $playlistStatus = $playlists->field('post_status');
            $playlistUrl = get_permalink($playlistID);

            // Count songs
            $songs = $playlists->field('songs');
            $numSongs = 0;
            if (!empty($songs) && is_array($songs)) {
                $numSongs = count($songs);
            }
            $total_songs += $numSongs;

            // Track visibility stats
            if ($playlistStatus === 'publish') {
                $public_count++;
            } elseif ($playlistStatus === 'private') {
                $private_count++;
            }

            // Visibility badge
            if ($playlistStatus === 'publish') {
                $visibility_badge = '<span class="fml-visibility-badge public"><i class="fas fa-globe"></i> Public</span>';
            } elseif ($playlistStatus === 'private') {
                $visibility_badge = '<span class="fml-visibility-badge private"><i class="fas fa-lock"></i> Private</span>';
            } else {
                $visibility_badge = '<span class="fml-visibility-badge draft"><i class="fas fa-eye-slash"></i> Draft</span>';
            }
            ?>
            <tr>
                <td><?php echo $playlistID; ?></td>
                <td>
                    <span class="fml-playlist-name">
                        <a href="<?php echo esc_url($playlistUrl); ?>"><?php echo esc_html($playlistName); ?></a>
                    </span>
                </td>
                <td><?php echo $visibility_badge; ?></td>
                <td>
                    <span class="fml-song-count">
                        <i class="fas fa-music"></i> <?php echo $numSongs; ?>
                    </span>
                </td>
                <td>
                    <a href="<?php echo esc_url($playlistUrl); ?>" class="fml-action-btn view" title="View Playlist">
                        <i class="fas fa-eye"></i>
                    </a>
                    <a href="?edit&playlistID=<?php echo $playlistID; ?>" class="fml-action-btn edit" title="Edit Playlist">
                        <i class="fas fa-edit"></i>
                    </a>
                    <button class="fml-action-btn delete playlist-delete" data-playlistid="<?php echo $playlistID; ?>" title="Delete Playlist">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            </tr>
            <?php
        }
        ?>
        </tbody>
    </table>

    <!-- Stats Cards -->
    <script>
    jQuery(document).ready(function() {
        var statsHtml = '<div class="fml-playlist-stats">' +
            '<div class="fml-stat-card"><span class="stat-number stat-total"><?php echo $total_playlists; ?></span><span class="stat-label">Total Playlists</span></div>' +
            '<div class="fml-stat-card public"><span class="stat-number stat-public"><?php echo $public_count; ?></span><span class="stat-label">Public</span></div>' +
            '<div class="fml-stat-card"><span class="stat-number stat-private"><?php echo $private_count; ?></span><span class="stat-label">Private</span></div>' +
            '<div class="fml-stat-card songs"><span class="stat-number"><?php echo $total_songs; ?></span><span class="stat-label">Total Songs</span></div>' +
            '</div>';
        jQuery('.fml-playlists-container').prepend(statsHtml);
    });
    </script>

    <?php else: ?>

    <div class="fml-empty-state">
        <i class="fas fa-list-music"></i>
        <h3>No Playlists Yet</h3>
        <p>Create your first playlist to start organizing your favorite music!</p>
    </div>

    <?php endif; ?>

    <!-- Add Playlist Form -->
    <div class="fml-add-playlist-section">
        <h3><i class="fas fa-plus-circle"></i> Create New Playlist</h3>
        <form name="add-playlist" method="POST" class="fml-add-playlist-form">
            <div class="fml-form-group">
                <label for="playlistname">Playlist Name</label>
                <input type="text" name="playlistname" id="playlistname" required placeholder="Enter playlist name...">
            </div>
            <input type="hidden" name="userID" value="<?php echo $current_user->ID; ?>">
            <button type="submit" class="fml-submit-btn">
                <i class="fas fa-plus"></i> Create Playlist
            </button>
        </form>
    </div>
</div>

<?php
}
