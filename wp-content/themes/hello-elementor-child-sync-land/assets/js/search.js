(function() {
    'use strict';

    var DEBOUNCE_MS = 300;
    var MIN_CHARS = 2;
    var API_URL = '/wp-json/FML/v1/search';
    var DEFAULT_ART = '/wp-content/uploads/2020/06/art-8-150x150.jpg';

    var overlay = null;
    var input = null;
    var resultsContainer = null;
    var debounceTimer = null;
    var currentRequest = null;

    // Category config: key, label, icon
    var categories = [
        { key: 'songs',     label: 'Songs',     icon: 'fa-music' },
        { key: 'artists',   label: 'Artists',    icon: 'fa-user' },
        { key: 'albums',    label: 'Albums',     icon: 'fa-compact-disc' },
        { key: 'playlists', label: 'Playlists',  icon: 'fa-list' },
        { key: 'genres',    label: 'Genres',     icon: 'fa-tag' },
        { key: 'moods',     label: 'Moods',      icon: 'fa-heart' }
    ];

    function init() {
        createOverlay();
        bindTriggers();
    }

    function createOverlay() {
        overlay = document.createElement('div');
        overlay.className = 'fml-search-overlay';
        overlay.innerHTML =
            '<button class="fml-search-close" title="Close"><i class="fas fa-times"></i></button>' +
            '<div class="fml-search-input-wrap">' +
                '<i class="fas fa-search"></i>' +
                '<input type="text" class="fml-search-input" placeholder="Search songs, artists, albums..." autocomplete="off" />' +
            '</div>' +
            '<div class="fml-search-results">' +
                '<div class="fml-search-hint">Type to search across all of Sync.Land</div>' +
            '</div>';

        document.body.appendChild(overlay);

        input = overlay.querySelector('.fml-search-input');
        resultsContainer = overlay.querySelector('.fml-search-results');

        // Close button
        overlay.querySelector('.fml-search-close').addEventListener('click', closeSearch);

        // Click on overlay background to close
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) closeSearch();
        });

        // Input handler with debounce
        input.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            var q = input.value.trim();

            if (q.length < MIN_CHARS) {
                if (q.length === 0) {
                    resultsContainer.innerHTML = '<div class="fml-search-hint">Type to search across all of Sync.Land</div>';
                } else {
                    resultsContainer.innerHTML = '<div class="fml-search-hint">Keep typing...</div>';
                }
                return;
            }

            debounceTimer = setTimeout(function() {
                doSearch(q);
            }, DEBOUNCE_MS);
        });

        // Escape key
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeSearch();
            }
        });
    }

    function bindTriggers() {
        // Search icon in player bar
        document.addEventListener('click', function(e) {
            var trigger = e.target.closest('.show-search');
            if (trigger) {
                e.preventDefault();
                openSearch();
            }
        });

        // "/" keyboard shortcut
        document.addEventListener('keydown', function(e) {
            // Don't trigger if user is typing in an input/textarea/contenteditable
            var tag = (e.target.tagName || '').toLowerCase();
            if (tag === 'input' || tag === 'textarea' || e.target.isContentEditable) return;
            if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                e.preventDefault();
                openSearch();
            }
        });
    }

    function openSearch() {
        if (!overlay) return;
        overlay.classList.add('active');
        overlay.classList.remove('fading-out');
        input.value = '';
        resultsContainer.innerHTML = '<div class="fml-search-hint">Type to search across all of Sync.Land</div>';
        // Delay focus slightly so overlay transition completes
        setTimeout(function() { input.focus(); }, 50);
        document.body.style.overflow = 'hidden';
    }

    function closeSearch() {
        if (!overlay) return;
        overlay.classList.add('fading-out');
        setTimeout(function() {
            overlay.classList.remove('active', 'fading-out');
        }, 200);
        document.body.style.overflow = '';
        if (currentRequest) {
            currentRequest.abort();
            currentRequest = null;
        }
    }

    function doSearch(query) {
        // Cancel previous request
        if (currentRequest) {
            currentRequest.abort();
        }

        resultsContainer.innerHTML = '<div class="fml-search-loading"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';

        currentRequest = new XMLHttpRequest();
        currentRequest.open('GET', API_URL + '?q=' + encodeURIComponent(query), true);
        currentRequest.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        currentRequest.onload = function() {
            currentRequest = null;
            if (this.status >= 200 && this.status < 300) {
                try {
                    var json = JSON.parse(this.responseText);
                    if (json.success && json.data) {
                        renderResults(json.data);
                    } else {
                        resultsContainer.innerHTML = '<div class="fml-search-empty">Search failed. Please try again.</div>';
                    }
                } catch (e) {
                    resultsContainer.innerHTML = '<div class="fml-search-empty">Search failed. Please try again.</div>';
                }
            } else {
                resultsContainer.innerHTML = '<div class="fml-search-empty">Search failed. Please try again.</div>';
            }
        };

        currentRequest.onerror = function() {
            currentRequest = null;
            resultsContainer.innerHTML = '<div class="fml-search-empty">Network error. Please try again.</div>';
        };

        currentRequest.onabort = function() {
            currentRequest = null;
        };

        currentRequest.send();
    }

    function renderResults(data) {
        var html = '';
        var hasResults = false;

        for (var i = 0; i < categories.length; i++) {
            var cat = categories[i];
            var items = data[cat.key];
            if (!items || items.length === 0) continue;

            hasResults = true;
            html += '<div class="fml-search-category">';
            html += '<div class="fml-search-category-header"><i class="fas ' + cat.icon + '"></i> ' + cat.label + '</div>';

            for (var j = 0; j < items.length; j++) {
                html += renderItem(cat.key, items[j]);
            }

            html += '</div>';
        }

        if (!hasResults) {
            html = '<div class="fml-search-empty">No results found</div>';
        }

        resultsContainer.innerHTML = html;

        // Bind play buttons
        var playBtns = resultsContainer.querySelectorAll('.fml-search-play-btn');
        for (var k = 0; k < playBtns.length; k++) {
            playBtns[k].addEventListener('click', handlePlayClick);
        }

        // Close overlay and restore scroll when clicking any result link
        var links = resultsContainer.querySelectorAll('a.no-pjax');
        for (var l = 0; l < links.length; l++) {
            links[l].addEventListener('click', function() {
                // Restore body scroll immediately so the target page is scrollable
                document.body.style.overflow = '';
                overlay.classList.remove('active', 'fading-out');
            });
        }
    }

    function renderItem(type, item) {
        switch (type) {
            case 'songs':
                return renderSong(item);
            case 'artists':
                return renderArtist(item);
            case 'albums':
                return renderAlbum(item);
            case 'playlists':
                return renderPlaylist(item);
            case 'genres':
            case 'moods':
                return renderTaxonomy(type, item);
            default:
                return '';
        }
    }

    function renderSong(song) {
        var art = song.cover_art_url || DEFAULT_ART;
        var thumb = '<img class="fml-search-thumb" src="' + escHtml(art) + '" alt="" />';
        var sub = escHtml(song.artist_name || '');
        if (song.album_name) sub += ' &mdash; ' + escHtml(song.album_name);

        return '<div class="fml-search-item" data-type="song">' +
            '<a href="' + escHtml(song.permalink || '#') + '" class="no-pjax" style="display:contents; color:inherit; text-decoration:none;">' +
                thumb +
                '<div class="fml-search-item-info">' +
                    '<div class="fml-search-item-title">' + escHtml(song.name) + '</div>' +
                    '<div class="fml-search-item-sub">' + sub + '</div>' +
                '</div>' +
            '</a>' +
            '<button class="fml-search-play-btn" title="Play"' +
                ' data-url="' + escHtml(song.audio_url || '') + '"' +
                ' data-name="' + escHtml(song.name || '') + '"' +
                ' data-artist="' + escHtml(song.artist_name || '') + '"' +
                ' data-album="' + escHtml(song.album_name || '') + '"' +
                ' data-art="' + escHtml(art) + '"' +
                ' data-artist-permalink="' + escHtml(song.artist_permalink || '') + '"' +
                ' data-album-permalink="' + escHtml(song.album_permalink || '') + '"' +
            '><i class="fas fa-play"></i></button>' +
        '</div>';
    }

    function renderArtist(artist) {
        var thumb = artist.thumbnail
            ? '<img class="fml-search-thumb artist" src="' + escHtml(artist.thumbnail) + '" alt="" />'
            : '<div class="fml-search-icon-placeholder artist"><i class="fas fa-user"></i></div>';

        return '<a href="' + escHtml(artist.permalink || '#') + '" class="fml-search-item no-pjax">' +
            thumb +
            '<div class="fml-search-item-info">' +
                '<div class="fml-search-item-title">' + escHtml(artist.name) + '</div>' +
                '<div class="fml-search-item-sub">Artist</div>' +
            '</div>' +
        '</a>';
    }

    function renderAlbum(album) {
        var art = album.cover_art_url || DEFAULT_ART;
        var thumb = '<img class="fml-search-thumb" src="' + escHtml(art) + '" alt="" />';

        return '<a href="' + escHtml(album.permalink || '#') + '" class="fml-search-item no-pjax">' +
            thumb +
            '<div class="fml-search-item-info">' +
                '<div class="fml-search-item-title">' + escHtml(album.title) + '</div>' +
                '<div class="fml-search-item-sub">' + escHtml(album.artist_name || '') + '</div>' +
            '</div>' +
        '</a>';
    }

    function renderPlaylist(playlist) {
        return '<a href="' + escHtml(playlist.permalink || '#') + '" class="fml-search-item no-pjax">' +
            '<div class="fml-search-icon-placeholder"><i class="fas fa-list"></i></div>' +
            '<div class="fml-search-item-info">' +
                '<div class="fml-search-item-title">' + escHtml(playlist.name) + '</div>' +
                '<div class="fml-search-item-sub">Playlist</div>' +
            '</div>' +
        '</a>';
    }

    function renderTaxonomy(type, term) {
        var icon = type === 'genres' ? 'fa-tag' : 'fa-heart';
        var label = type === 'genres' ? 'Genre' : 'Mood';

        return '<a href="' + escHtml(term.link || '#') + '" class="fml-search-item no-pjax">' +
            '<div class="fml-search-icon-placeholder"><i class="fas ' + icon + '"></i></div>' +
            '<div class="fml-search-item-info">' +
                '<div class="fml-search-item-title">' + escHtml(term.name) + '</div>' +
                '<div class="fml-search-item-sub">' + label + '</div>' +
            '</div>' +
        '</a>';
    }

    function handlePlayClick(e) {
        e.preventDefault();
        e.stopPropagation();

        var btn = e.currentTarget;
        var songObj = {
            name: btn.getAttribute('data-name'),
            artist: btn.getAttribute('data-artist'),
            album: btn.getAttribute('data-album'),
            url: btn.getAttribute('data-url'),
            cover_art_url: btn.getAttribute('data-art'),
            artist_permalink: btn.getAttribute('data-artist-permalink') || '',
            album_permalink: btn.getAttribute('data-album-permalink') || ''
        };

        if (!songObj.url) return;

        // Use the shared play-at-top function from music-player.js
        if (typeof window.FMLPlaySongAtTop === 'function') {
            window.FMLPlaySongAtTop(songObj);
        }

        closeSearch();
    }

    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
