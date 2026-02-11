/**
 * Hero Planet Controller
 *
 * Controls the hero planet display with random song metadata overlay.
 * Integrates with the inner-planet.js Three.js visualization and the FML music player.
 */

(function() {
    'use strict';

    // Store current song data for each hero planet instance
    const instances = new Map();

    // Track if we've attached audio element listeners
    let audioListenersAttached = false;

    /**
     * Initialize a hero planet instance
     */
    function initHeroPlanet(wrapper) {
        const instanceId = wrapper.id;
        const canvas = wrapper.querySelector('.hero-planet-canvas');
        const loading = wrapper.querySelector('.hero-planet-loading');
        const content = wrapper.querySelector('.hero-planet-content');
        const playBtn = wrapper.querySelector('.hero-planet-play-btn');
        const refreshBtn = wrapper.querySelector('.hero-planet-refresh-btn');

        // Get filter options from data attributes
        const genre = wrapper.dataset.genre || '';
        const mood = wrapper.dataset.mood || '';

        // Store instance data
        instances.set(instanceId, {
            wrapper,
            canvas,
            loading,
            content,
            playBtn,
            refreshBtn,
            genre,
            mood,
            currentSong: null,
            planetInstance: null,
            songReady: false
        });

        // Disable play button until song is loaded
        playBtn.style.opacity = '0.5';
        playBtn.style.cursor = 'wait';

        // Initialize the 3D planet
        initPlanet(instanceId);

        // Load initial random song
        loadRandomSong(instanceId);

        // Set up event listeners
        playBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var inst = instances.get(instanceId);
            if (!inst || !inst.songReady) {
                console.log('Hero Planet: Song not ready yet');
                return;
            }

            togglePlay(instanceId);
        });

        refreshBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            loadRandomSong(instanceId);
        });
    }

    /**
     * Initialize the 3D planet visualization
     */
    function initPlanet(instanceId) {
        const instance = instances.get(instanceId);
        if (!instance) return;

        // Wait for innerPlanetModule to be available
        if (typeof window.innerPlanetModule === 'undefined') {
            setTimeout(function() { initPlanet(instanceId); }, 100);
            return;
        }

        // Create the planet in the canvas container
        instance.planetInstance = window.innerPlanetModule.createInnerPlanet(instance.canvas);
    }

    /**
     * Load a random song from the API
     */
    function loadRandomSong(instanceId) {
        const instance = instances.get(instanceId);
        if (!instance) return;

        // Show loading state and disable play button
        instance.loading.style.display = 'flex';
        instance.content.style.display = 'none';
        instance.songReady = false;
        instance.playBtn.style.opacity = '0.5';
        instance.playBtn.style.cursor = 'wait';

        // Build API URL with optional filters
        let apiUrl = '/wp-json/FML/v1/songs/random';
        const params = new URLSearchParams();

        if (instance.genre) {
            params.append('genre', instance.genre);
        }
        if (instance.mood) {
            params.append('mood', instance.mood);
        }

        const queryString = params.toString();
        if (queryString) {
            apiUrl += '?' + queryString;
        }

        // Fetch random song
        fetch(apiUrl)
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Failed to fetch song');
                }
                return response.json();
            })
            .then(function(data) {
                // API returns { success: true, song: {...} }
                if (data.success && data.song) {
                    displaySong(instanceId, data.song);
                } else {
                    showError(instanceId, 'No songs available');
                }
            })
            .catch(function(error) {
                console.error('Hero Planet: Error loading song', error);
                showError(instanceId, 'Failed to load song');
            });
    }

    /**
     * Display song metadata in the overlay
     */
    function displaySong(instanceId, song) {
        const instance = instances.get(instanceId);
        if (!instance) return;

        // Store current song
        instance.currentSong = song;

        // Update metadata display
        const titleEl = instance.wrapper.querySelector('.hero-planet-title');
        const artistEl = instance.wrapper.querySelector('.hero-planet-artist');
        const tagsEl = instance.wrapper.querySelector('.hero-planet-tags');
        const albumArtEl = instance.wrapper.querySelector('.hero-planet-album-art');

        // Get song name (handle if object) and decode HTML entities
        var songName = song.name;
        if (typeof songName === 'object' && songName !== null) {
            songName = songName.name || songName.post_title || 'Unknown Song';
        }
        songName = decodeHtml(songName || 'Unknown Song');

        // Get artist name (handle if object) and decode HTML entities
        var artistName = song.artist_name;
        if (typeof artistName === 'object' && artistName !== null) {
            artistName = artistName.name || artistName.post_title || 'Unknown Artist';
        }
        artistName = decodeHtml(artistName || 'Unknown Artist');

        // Display album art
        if (albumArtEl) {
            if (song.cover_art_url) {
                albumArtEl.src = song.cover_art_url;
                albumArtEl.style.display = 'block';
            } else {
                albumArtEl.style.display = 'none';
            }
        }

        // Set title with link to song page
        if (song.permalink) {
            titleEl.innerHTML = '<a href="' + escHtml(song.permalink) + '">' + escHtml(songName) + '</a>';
        } else {
            titleEl.textContent = songName;
        }

        // Set artist with link to artist page
        if (song.artist_permalink) {
            artistEl.innerHTML = '<a href="' + escHtml(song.artist_permalink) + '">' + escHtml(artistName) + '</a>';
        } else {
            artistEl.textContent = artistName;
        }

        // Build tags
        tagsEl.innerHTML = '';

        // Genres - array of objects with name and permalink
        if (song.genres && Array.isArray(song.genres)) {
            song.genres.forEach(function(g) {
                if (g && g.name) {
                    var tag = document.createElement('span');
                    tag.className = 'hero-planet-tag hero-planet-tag-genre';

                    if (g.permalink) {
                        var link = document.createElement('a');
                        link.href = g.permalink;
                        link.textContent = g.name;
                        tag.appendChild(link);
                    } else {
                        tag.textContent = g.name;
                    }

                    tagsEl.appendChild(tag);
                }
            });
        }

        // Moods - array of objects with name and permalink
        if (song.moods && Array.isArray(song.moods)) {
            song.moods.forEach(function(m) {
                if (m && m.name) {
                    var tag = document.createElement('span');
                    tag.className = 'hero-planet-tag hero-planet-tag-mood';

                    if (m.permalink) {
                        var link = document.createElement('a');
                        link.href = m.permalink;
                        link.textContent = m.name;
                        tag.appendChild(link);
                    } else {
                        tag.textContent = m.name;
                    }

                    tagsEl.appendChild(tag);
                }
            });
        }

        // BPM
        if (song.bpm) {
            var bpmVal = song.bpm;
            // Handle if bpm is somehow still an object
            if (typeof bpmVal === 'object') {
                bpmVal = bpmVal.value || bpmVal.name || '';
            }
            if (bpmVal) {
                var tag = document.createElement('span');
                tag.className = 'hero-planet-tag hero-planet-tag-bpm';
                tag.textContent = bpmVal + ' BPM';
                tagsEl.appendChild(tag);
            }
        }

        // Hide loading, show content
        instance.loading.style.display = 'none';
        instance.content.style.display = 'flex';

        // Mark song as ready and enable play button only if we have an audio URL
        if (song.audio_url) {
            instance.songReady = true;
            instance.playBtn.style.opacity = '1';
            instance.playBtn.style.cursor = 'pointer';
        } else {
            instance.songReady = false;
            instance.playBtn.style.opacity = '0.5';
            instance.playBtn.style.cursor = 'not-allowed';
        }
    }

    /**
     * Show error state
     */
    function showError(instanceId, message) {
        const instance = instances.get(instanceId);
        if (!instance) return;

        instance.loading.style.display = 'none';
        instance.content.style.display = 'flex';

        const titleEl = instance.wrapper.querySelector('.hero-planet-title');
        const artistEl = instance.wrapper.querySelector('.hero-planet-artist');
        const tagsEl = instance.wrapper.querySelector('.hero-planet-tags');

        titleEl.textContent = message || 'Error';
        artistEl.textContent = 'Click refresh to try again';
        tagsEl.innerHTML = '';
    }

    /**
     * Check if audio is currently playing
     */
    function isAudioPlaying() {
        if (typeof Amplitude === 'undefined') return false;
        try {
            var audio = Amplitude.getAudio();
            return audio && !audio.paused;
        } catch(e) {
            return false;
        }
    }

    /**
     * Toggle play/pause for the current song
     */
    function togglePlay(instanceId) {
        const instance = instances.get(instanceId);
        if (!instance) {
            console.error('Hero Planet: No instance');
            return;
        }

        // Check if song is loaded
        if (!instance.currentSong) {
            console.log('Hero Planet: Song not loaded yet, waiting...');
            return;
        }

        // Check if audio URL exists
        if (!instance.currentSong.audio_url) {
            console.error('Hero Planet: No audio URL for song');
            return;
        }

        // Check actual audio state
        var currentlyPlaying = isAudioPlaying();

        if (currentlyPlaying) {
            // Pause
            if (typeof Amplitude !== 'undefined') {
                Amplitude.pause();
                // Rebind elements to ensure main player button updates
                try { Amplitude.bindNewElements(); } catch(e) {}
            }
            // Button state will be updated by audio event listener
        } else {
            // Play - check if we need to load the song first or just resume
            if (typeof Amplitude !== 'undefined') {
                try {
                    var currentSongs = Amplitude.getSongs() || [];
                    var currentSong = instance.currentSong;

                    // Check if our song is already loaded (by URL match)
                    var songLoaded = currentSongs.some(function(s) {
                        return s.url === currentSong.audio_url;
                    });

                    if (songLoaded) {
                        // Just play - button state will be updated by audio event listener
                        Amplitude.play();
                        // Rebind elements to ensure main player button updates
                        try { Amplitude.bindNewElements(); } catch(e) {}
                    } else {
                        // Load and play
                        playSong(instanceId);
                    }
                } catch(e) {
                    playSong(instanceId);
                }
            } else {
                playSong(instanceId);
            }
        }
    }

    /**
     * Play the current song using FMLPlaySongAtTop
     */
    function playSong(instanceId) {
        const instance = instances.get(instanceId);
        if (!instance || !instance.currentSong) {
            console.error('Hero Planet: No instance or current song');
            return;
        }

        const song = instance.currentSong;

        // Build song object for the player - decode HTML entities
        const songObj = {
            name: decodeHtml(song.name || ''),
            artist: decodeHtml(song.artist_name || ''),
            album: decodeHtml(song.album_name || ''),
            url: song.audio_url || '',
            cover_art_url: song.cover_art_url || '',
            song_id: song.id,
            permalink: song.permalink || '',
            artist_permalink: song.artist_permalink || '',
            album_permalink: song.album_permalink || ''
        };

        // Check if audio URL exists
        if (!songObj.url) {
            console.error('Hero Planet: No audio URL for song');
            return;
        }

        // Use the shared play function from music-player.js
        if (typeof window.FMLPlaySongAtTop === 'function') {
            window.FMLPlaySongAtTop(songObj);

            // FMLPlaySongAtTop reinitializes Amplitude, so we need to reattach listeners
            // and ensure Amplitude's element bindings are updated
            setTimeout(function() {
                reattachAudioListeners();

                // Rebind all Amplitude elements to ensure main player buttons sync
                if (typeof Amplitude !== 'undefined') {
                    try {
                        Amplitude.bindNewElements();
                    } catch(e) {}

                    // Amplitude may not autoplay due to browser restrictions
                    // Try to explicitly play after reattaching listeners
                    try {
                        Amplitude.play();
                    } catch(e) {
                        console.error('Hero Planet: Error calling Amplitude.play()', e);
                    }

                    // Rebind again after play to ensure state is correct
                    setTimeout(function() {
                        try { Amplitude.bindNewElements(); } catch(e) {}
                    }, 100);
                }
            }, 150);
        } else {
            console.error('Hero Planet: FMLPlaySongAtTop not available');
        }
    }

    /**
     * Update play button icon
     */
    function updatePlayButton(instanceId, isPlaying) {
        const instance = instances.get(instanceId);
        if (!instance) return;

        const btn = instance.playBtn;
        if (isPlaying) {
            // Show pause icon
            btn.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>';
            btn.setAttribute('aria-label', 'Pause song');
        } else {
            // Show play icon
            btn.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>';
            btn.setAttribute('aria-label', 'Play song');
        }
    }

    /**
     * Sync all play buttons with current audio state
     */
    function syncAllPlayButtons() {
        var isPlaying = isAudioPlaying();
        instances.forEach(function(instance, id) {
            updatePlayButton(id, isPlaying);
        });

        // Also sync the main music player's play/pause buttons
        syncMainPlayerButtons(isPlaying);
    }

    /**
     * Sync the main music player's play/pause buttons
     * These use Amplitude's CSS class system
     */
    function syncMainPlayerButtons(isPlaying) {
        // Amplitude uses amplitude-playing/amplitude-paused classes on amplitude-play-pause elements
        var playPauseElements = document.querySelectorAll('.amplitude-play-pause');
        playPauseElements.forEach(function(el) {
            if (isPlaying) {
                el.classList.remove('amplitude-paused');
                el.classList.add('amplitude-playing');
            } else {
                el.classList.remove('amplitude-playing');
                el.classList.add('amplitude-paused');
            }
        });
    }

    /**
     * Attach listeners to the actual audio element for reliable state sync
     */
    function attachAudioListeners() {
        if (audioListenersAttached) return;

        function tryAttach() {
            if (typeof Amplitude === 'undefined') {
                setTimeout(tryAttach, 200);
                return;
            }

            try {
                var audio = Amplitude.getAudio();
                if (!audio) {
                    setTimeout(tryAttach, 200);
                    return;
                }

                // Listen to native audio events - these are the most reliable
                audio.addEventListener('play', function() {
                    instances.forEach(function(instance, id) {
                        updatePlayButton(id, true);
                    });
                    syncMainPlayerButtons(true);
                });

                audio.addEventListener('playing', function() {
                    instances.forEach(function(instance, id) {
                        updatePlayButton(id, true);
                    });
                    syncMainPlayerButtons(true);
                });

                audio.addEventListener('pause', function() {
                    instances.forEach(function(instance, id) {
                        updatePlayButton(id, false);
                    });
                    syncMainPlayerButtons(false);
                });

                audio.addEventListener('ended', function() {
                    instances.forEach(function(instance, id) {
                        updatePlayButton(id, false);
                    });
                    syncMainPlayerButtons(false);
                });

                audioListenersAttached = true;
                console.log('Hero Planet: Audio element listeners attached');

                // Sync initial state
                syncAllPlayButtons();
            } catch(e) {
                setTimeout(tryAttach, 200);
            }
        }

        tryAttach();
    }

    /**
     * Re-attach audio listeners after Amplitude reinitializes
     * This is needed because FMLPlaySongAtTop calls Amplitude.init() which creates a new audio element
     */
    function reattachAudioListeners() {
        audioListenersAttached = false;
        attachAudioListeners();
    }

    // Start trying to attach audio listeners
    attachAudioListeners();

    // Also set up a mutation observer or periodic check for audio element changes
    setInterval(function() {
        if (typeof Amplitude !== 'undefined') {
            try {
                var audio = Amplitude.getAudio();
                if (audio && !audioListenersAttached) {
                    attachAudioListeners();
                }
            } catch(e) {}
        }
    }, 500);

    /**
     * Escape HTML to prevent XSS
     */
    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    /**
     * Decode HTML entities (e.g., &amp; -> &)
     */
    function decodeHtml(str) {
        if (!str) return '';
        var txt = document.createElement('textarea');
        txt.innerHTML = str;
        return txt.value;
    }

    /**
     * Initialize all hero planets on the page
     */
    function init() {
        const wrappers = document.querySelectorAll('.hero-planet-wrapper');
        wrappers.forEach(initHeroPlanet);
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose for external use if needed
    window.HeroPlanet = {
        refresh: function(instanceId) {
            loadRandomSong(instanceId);
        },
        getInstance: function(instanceId) {
            return instances.get(instanceId);
        }
    };

})();
