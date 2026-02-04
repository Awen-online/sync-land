<?php
/**
 * My Artists/Music Template - Enhanced Dark Mode Version
 * Displays user's artists and their albums
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();

// Get all artists for the logged in user
$paramsArtist = array(
    'where' => "t.post_author = '" . $current_user->ID . "' AND t.post_status = 'Publish'",
    "orderby" => "t.post_date DESC"
);

$artists = pods('artist', $paramsArtist);
$total_artists = $artists->total();

// Count stats
$total_albums = 0;
$total_songs = 0;
?>

<style>
/* My Artists/Music Page - Dark Mode Styles */
.fml-artists-container {
    color: #e2e8f0;
}

/* Stats Cards */
.fml-artist-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
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

.fml-stat-card.albums .stat-number {
    color: #63b3ed;
}

.fml-stat-card.songs .stat-number {
    color: #48bb78;
}

/* Artist Card */
.fml-artist-card {
    background: #252540;
    border: 1px solid #404060;
    border-radius: 12px;
    margin-bottom: 25px;
    overflow: hidden;
}

.fml-artist-header {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 25px;
    background: linear-gradient(135deg, #1e1e32 0%, #252540 100%);
    border-bottom: 1px solid #404060;
}

.fml-artist-image {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #E7565A;
    box-shadow: 0 4px 15px rgba(231, 86, 90, 0.3);
}

.fml-artist-image-placeholder {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: #404060;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 3px solid #E7565A;
}

.fml-artist-image-placeholder i {
    font-size: 2.5rem;
    color: #718096;
}

.fml-artist-info {
    flex: 1;
}

.fml-artist-name {
    margin: 0 0 10px 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: #e2e8f0;
}

.fml-artist-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* Action Buttons */
.fml-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 16px;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none !important;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
}

.fml-action-btn.view {
    background: rgba(99, 179, 237, 0.2);
    color: #63b3ed !important;
    border: 1px solid rgba(99, 179, 237, 0.3);
}

.fml-action-btn.view:hover {
    background: #63b3ed;
    color: white !important;
    transform: translateY(-2px);
}

.fml-action-btn.edit {
    background: rgba(246, 224, 94, 0.2);
    color: #f6e05e !important;
    border: 1px solid rgba(246, 224, 94, 0.3);
}

.fml-action-btn.edit:hover {
    background: #f6e05e;
    color: #1a1a2e !important;
    transform: translateY(-2px);
}

.fml-action-btn.upload {
    background: rgba(72, 187, 120, 0.2);
    color: #48bb78 !important;
    border: 1px solid rgba(72, 187, 120, 0.3);
}

.fml-action-btn.upload:hover {
    background: #48bb78;
    color: white !important;
    transform: translateY(-2px);
}

.fml-action-btn.create {
    background: #E7565A;
    color: white !important;
}

.fml-action-btn.create:hover {
    background: #ff6b6f;
    transform: translateY(-2px);
}

/* Albums Section */
.fml-albums-section {
    padding: 25px;
}

.fml-albums-title {
    margin: 0 0 20px 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #a0aec0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.fml-albums-title i {
    color: #E7565A;
}

/* Albums Table */
.fml-albums-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.fml-albums-table thead th {
    background: #1a1a2e;
    color: #a0aec0;
    padding: 12px 15px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-align: left;
    border-bottom: 1px solid #404060;
}

.fml-albums-table thead th:first-child {
    border-radius: 8px 0 0 0;
}

.fml-albums-table thead th:last-child {
    border-radius: 0 8px 0 0;
}

.fml-albums-table tbody tr {
    transition: background 0.2s ease;
}

.fml-albums-table tbody tr:hover {
    background: #1e1e32;
}

.fml-albums-table tbody td {
    padding: 15px;
    border-bottom: 1px solid #2d2d44;
    vertical-align: middle;
}

.fml-albums-table tbody tr:last-child td {
    border-bottom: none;
}

/* Album Row */
.fml-album-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.fml-album-cover {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    object-fit: cover;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.fml-album-cover-placeholder {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    background: #404060;
    display: flex;
    align-items: center;
    justify-content: center;
}

.fml-album-cover-placeholder i {
    font-size: 1.5rem;
    color: #718096;
}

.fml-album-title {
    font-weight: 600;
    color: #e2e8f0;
    margin: 0;
}

.fml-album-title a {
    color: #e2e8f0 !important;
    text-decoration: none;
}

.fml-album-title a:hover {
    color: #E7565A !important;
}

.fml-release-date {
    color: #a0aec0;
    font-size: 0.9rem;
}

/* No Albums State */
.fml-no-albums {
    text-align: center;
    padding: 30px;
    color: #718096;
}

.fml-no-albums i {
    font-size: 2rem;
    margin-bottom: 10px;
    display: block;
}

/* Upload Album Button */
.fml-upload-section {
    padding: 0 25px 25px 25px;
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
    margin-bottom: 20px;
}

/* Create Artist Section */
.fml-create-artist-section {
    text-align: center;
    padding: 30px;
    background: #252540;
    border: 2px dashed #404060;
    border-radius: 12px;
    margin-top: 20px;
}

.fml-create-artist-section p {
    color: #a0aec0;
    margin-bottom: 15px;
}

/* Song Count Badge */
.fml-song-count-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    background: rgba(72, 187, 120, 0.15);
    border-radius: 20px;
    font-size: 0.85rem;
    color: #48bb78;
}

/* Responsive */
@media (max-width: 768px) {
    .fml-artist-stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .fml-artist-header {
        flex-direction: column;
        text-align: center;
    }

    .fml-artist-actions {
        justify-content: center;
    }

    .fml-album-info {
        flex-direction: column;
        text-align: center;
    }

    .fml-action-btn span {
        display: none;
    }

    .fml-action-btn {
        padding: 10px;
    }
}
</style>

<div class="fml-artists-container">
    <?php if ($total_artists > 0): ?>

    <?php
    // First pass to count albums and songs
    $artists_data = [];
    while ($artists->fetch()) {
        $artist_id = $artists->field('ID');
        $albums = $artists->field('albums');
        $album_count = is_array($albums) ? count($albums) : 0;
        $total_albums += $album_count;

        // Count songs in albums
        $song_count = 0;
        if (!empty($albums) && is_array($albums)) {
            foreach ($albums as $album) {
                $album_pod = pods('album', $album['ID']);
                $songs = $album_pod->field('songs');
                if (!empty($songs) && is_array($songs)) {
                    $song_count += count($songs);
                }
            }
        }
        $total_songs += $song_count;

        $artists_data[] = [
            'id' => $artist_id,
            'name' => $artists->field('post_title'),
            'image' => $artists->display('profile_image'),
            'albums' => $albums,
            'album_count' => $album_count,
            'song_count' => $song_count
        ];
    }
    ?>

    <!-- Stats Cards -->
    <div class="fml-artist-stats">
        <div class="fml-stat-card">
            <span class="stat-number"><?php echo $total_artists; ?></span>
            <span class="stat-label">Artists</span>
        </div>
        <div class="fml-stat-card albums">
            <span class="stat-number"><?php echo $total_albums; ?></span>
            <span class="stat-label">Albums</span>
        </div>
        <div class="fml-stat-card songs">
            <span class="stat-number"><?php echo $total_songs; ?></span>
            <span class="stat-label">Songs</span>
        </div>
    </div>

    <!-- Artist Cards -->
    <?php foreach ($artists_data as $artist): ?>
    <div class="fml-artist-card">
        <div class="fml-artist-header">
            <?php if (!empty($artist['image'])): ?>
                <img src="<?php echo esc_url($artist['image']); ?>" alt="<?php echo esc_attr($artist['name']); ?>" class="fml-artist-image">
            <?php else: ?>
                <div class="fml-artist-image-placeholder">
                    <i class="fas fa-user-music"></i>
                </div>
            <?php endif; ?>

            <div class="fml-artist-info">
                <h2 class="fml-artist-name"><?php echo esc_html($artist['name']); ?></h2>
                <div class="fml-artist-meta" style="display: flex; gap: 15px; margin-bottom: 15px;">
                    <span class="fml-song-count-badge">
                        <i class="fas fa-compact-disc"></i> <?php echo $artist['album_count']; ?> album<?php echo $artist['album_count'] !== 1 ? 's' : ''; ?>
                    </span>
                    <span class="fml-song-count-badge">
                        <i class="fas fa-music"></i> <?php echo $artist['song_count']; ?> song<?php echo $artist['song_count'] !== 1 ? 's' : ''; ?>
                    </span>
                </div>
                <div class="fml-artist-actions">
                    <a href="<?php echo esc_url(get_permalink($artist['id'])); ?>" target="_blank" class="fml-action-btn view">
                        <i class="fas fa-eye"></i><span>View Page</span>
                    </a>
                    <a href="/account/artist-edit/?artist_edit_id=<?php echo $artist['id']; ?>" class="fml-action-btn edit">
                        <i class="fas fa-edit"></i><span>Edit Artist</span>
                    </a>
                    <a href="/my-account/album-upload-add-songs/?artist_id=<?php echo $artist['id']; ?>" class="fml-action-btn upload">
                        <i class="fas fa-upload"></i><span>Upload Music</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="fml-albums-section">
            <?php if (!empty($artist['albums']) && is_array($artist['albums'])): ?>
                <h3 class="fml-albums-title"><i class="fas fa-compact-disc"></i> Albums & Singles</h3>
                <table class="fml-albums-table">
                    <thead>
                        <tr>
                            <th>Album</th>
                            <th>Release Date</th>
                            <th>Songs</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($artist['albums'] as $album):
                        $album_pod = pods('album', $album['ID']);
                        $cover_art = $album_pod->display('cover_art');
                        $release_date = $album_pod->display('release_date');
                        $album_songs = $album_pod->field('songs');
                        $album_song_count = is_array($album_songs) ? count($album_songs) : 0;
                    ?>
                        <tr>
                            <td>
                                <div class="fml-album-info">
                                    <?php if (!empty($cover_art)): ?>
                                        <img src="<?php echo esc_url($cover_art); ?>" alt="" class="fml-album-cover">
                                    <?php else: ?>
                                        <div class="fml-album-cover-placeholder">
                                            <i class="fas fa-compact-disc"></i>
                                        </div>
                                    <?php endif; ?>
                                    <h4 class="fml-album-title">
                                        <a href="<?php echo esc_url(get_permalink($album['ID'])); ?>">
                                            <?php echo esc_html($album['post_title']); ?>
                                        </a>
                                    </h4>
                                </div>
                            </td>
                            <td>
                                <span class="fml-release-date">
                                    <?php echo !empty($release_date) ? esc_html($release_date) : '-'; ?>
                                </span>
                            </td>
                            <td>
                                <span class="fml-song-count-badge">
                                    <i class="fas fa-music"></i> <?php echo $album_song_count; ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(get_permalink($album['ID'])); ?>" target="_blank" class="fml-action-btn view" title="View Album">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="fml-no-albums">
                    <i class="fas fa-compact-disc"></i>
                    <p>No albums uploaded yet</p>
                    <a href="/my-account/album-upload-add-songs/?artist_id=<?php echo $artist['id']; ?>" class="fml-action-btn upload">
                        <i class="fas fa-upload"></i><span>Upload First Album</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php else: ?>

    <div class="fml-empty-state">
        <i class="fas fa-user-music"></i>
        <h3>No Artists Yet</h3>
        <p>To upload your music, first create an artist profile.</p>
        <a href="/my-account/artist-registration" class="fml-action-btn create">
            <i class="fas fa-plus"></i> Create Artist
        </a>
    </div>

    <?php endif; ?>

    <?php if ($total_artists > 0 && $total_artists <= 1): ?>
    <div class="fml-create-artist-section">
        <p>Want to add another artist?</p>
        <a href="/my-account/artist-registration" class="fml-action-btn create">
            <i class="fas fa-plus"></i> Create Artist
        </a>
    </div>
    <?php endif; ?>
</div>
