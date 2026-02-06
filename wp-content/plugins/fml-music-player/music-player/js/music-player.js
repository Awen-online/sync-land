/*
 * FML Music Player - Enhanced Version
 * Queue management, dark mode support, license tracking, audio visualization
 */

// Global audio data for visualizer integration
window.FMLAudioData = {
    intensity: 0,
    bass: 0,
    mid: 0,
    treble: 0,
    analyser: null,
    isPlaying: false
};

jQuery(function($) {

    // Initialize localStorage variables and songList
    var secondsLoad = parseFloat(localStorage.getItem('timeUpdate'));
    var songIndex = parseInt(localStorage.getItem("songIndex"));
    var percentage = parseFloat(localStorage.getItem("percentage"));
    var songs = [];
    var playlists = {};

    var doesExist = !isNaN(songIndex) && songIndex !== null && secondsLoad !== null && secondsLoad !== 0;
    if(doesExist){
        // Try loading the full queue first, fall back to single song
        var storedQueue = localStorage.getItem("fml_queue");
        var storedSong = localStorage.getItem("songList");

        if (storedQueue) {
            try {
                songs = JSON.parse(storedQueue);
                if (!Array.isArray(songs) || songs.length === 0) throw new Error('Invalid queue');
                console.log("LOADED full queue:", songs.length, "songs");
            } catch(e) {
                songs = [];
                doesExist = false;
            }
        }

        // Fallback: single song from songList
        if (songs.length === 0 && storedSong) {
            try {
                var single = JSON.parse(storedSong);
                if (single && single.url) {
                    songs = [single];
                    songIndex = 0;
                    console.log("LOADED single song fallback");
                }
            } catch(e) {
                console.log("Failed to parse stored song, using empty queue");
                doesExist = false;
                songs = [];
            }
        }

        if (songs.length === 0) {
            doesExist = false;
        }
    } else {
        songs = [];
    }

    // Amplitude callbacks shared across all init calls
    var amplitudeCallbacks = {
        stop: function(){
            console.log("Audio has been stopped.");
            window.FMLAudioData.isPlaying = false;
        },
        play: function() {
            console.log("Audio is playing.");
            window.FMLAudioData.isPlaying = true;
            initAudioAnalyser();
        },
        pause: function() {
            console.log("Audio is paused.");
            window.FMLAudioData.isPlaying = false;
        },
        song_change: function() {
            console.log("Song changed.");
            updateLicenseButton();
            if (window.updatePlayerMeta) window.updatePlayerMeta();
            updateQueueDisplay();
        }
    };

    // Only initialize Amplitude if we have songs; otherwise wait for user to play a song
    if (songs.length > 0) {
        Amplitude.init({
            songs: songs,
            debug: true,
            autoplay: false,
            preload: "metadata",
            callbacks: amplitudeCallbacks
        });

        console.log(Amplitude.getActiveSongMetadata());

        var percentage = localStorage.getItem("percentage");
        var isPlaying = localStorage.getItem("playing");
        var isPlayingTrue = (isPlaying === 'true');
        if(doesExist){
            Amplitude.skipTo(secondsLoad, songIndex);
            if(isPlayingTrue){
                Amplitude.play();
            } else {
                Amplitude.pause();
            }
        }
    } else {
        console.log("No songs in queue. Player will initialize when a song is played.");
    }

    // Store callbacks globally so playNow/playAll can use them
    window.FMLAmplitudeCallbacks = amplitudeCallbacks;

    // Update license button and player meta on initial load
    updateLicenseButton();
    if (window.updatePlayerMeta) window.updatePlayerMeta();

    //
    // LOCALSTORAGE
    //
    // When navigating away, save the full queue and current song
    jQuery(window).on('beforeunload', function() {
        try {
            var allSongs = Amplitude.getSongs();
            if (allSongs && allSongs.length > 0) {
                localStorage.setItem('fml_queue', JSON.stringify(allSongs));
            }
            localStorage.setItem('songList', JSON.stringify(Amplitude.getActiveSongMetadata()));
        } catch(e) {}
    });

    // Store current seconds and songIndex in localStorage (for page-to-page navigation)
    jQuery(Amplitude.getAudio()).on('timeupdate', function() {
        var seconds = Amplitude.getSongPlayedSeconds();
        localStorage.setItem('timeUpdate', seconds);
        localStorage.setItem('percentage', Amplitude.getSongPlayedPercentage());
        localStorage.setItem('songIndex', Amplitude.getActiveIndex());
    });

    jQuery(Amplitude.getAudio()).on('play', function() {
        localStorage.setItem('playing', true);
        console.log("play");
    });

    jQuery(Amplitude.getAudio()).on('pause', function() {
        localStorage.setItem('playing', false);
        console.log("pause");
    });

    //
    // END LOCALSTORAGE
    //

    //
    // QUEUE PANEL CONTROLS
    //

    // Toggle playlist/queue open and closed
    var queueOpen = false;
    var $queueContainer = jQuery('#white-player-playlist-container');

    function openQueue() {
        queueOpen = true;
        $queueContainer.removeClass('slide-out-top').addClass('slide-in-top');
        $queueContainer.show();
        updateQueueDisplay();
    }

    function closeQueue() {
        queueOpen = false;
        $queueContainer.removeClass('slide-in-top').addClass('slide-out-top');
        setTimeout(function() {
            if (!queueOpen) $queueContainer.hide();
        }, 500);
    }

    // Shows/hides the playlist/queue (toggle)
    if (typeof document.getElementsByClassName('show-playlist')[0] !== 'undefined') {
        document.getElementsByClassName('show-playlist')[0].addEventListener('click', function(){
            if (queueOpen) {
                closeQueue();
            } else {
                openQueue();
            }
        });
    }

    // Close button inside queue
    if (typeof document.getElementsByClassName('close-playlist')[0] !== 'undefined') {
        document.getElementsByClassName('close-playlist')[0].addEventListener('click', function(){
            closeQueue();
        });
    }

    //
    // LICENSE BUTTON FUNCTIONALITY
    //

    function updateLicenseButton() {
        var meta = Amplitude.getActiveSongMetadata();
        var $licenseBtn = $('#license-button');

        if (meta && meta.permalink && meta.permalink !== '') {
            $licenseBtn.attr('href', meta.permalink);
            $licenseBtn.show();
        } else {
            $licenseBtn.attr('href', '#');
            // Still show, but could hide if no permalink: $licenseBtn.hide();
        }
    }

    //
    // PLAYER META (artist + album links)
    //

    // Exposed globally so it can be called from the song-play handler
    window.updatePlayerMeta = function() {
        try {
            var meta = Amplitude.getActiveSongMetadata();
            if (!meta) return;

            var $artistLink = jQuery('#player-artist-link');
            var $albumLink = jQuery('#player-album-link');
            var $separator = jQuery('.player-meta-separator');

            // Artist link
            if (meta.artist && meta.artist !== 'Unknown Artist') {
                $artistLink.text(meta.artist);
                if (meta.artist_permalink) {
                    $artistLink.attr('href', meta.artist_permalink);
                } else {
                    $artistLink.removeAttr('href');
                }
                $artistLink.show();
            } else {
                $artistLink.hide();
            }

            // Album link
            if (meta.album && meta.album !== '') {
                $albumLink.text(meta.album);
                if (meta.album_permalink) {
                    $albumLink.attr('href', meta.album_permalink);
                } else {
                    $albumLink.removeAttr('href');
                }
                $albumLink.show();
            } else {
                $albumLink.hide();
            }

            // Separator only if both are visible
            if (meta.artist && meta.artist !== 'Unknown Artist' && meta.album && meta.album !== '') {
                $separator.show();
            } else {
                $separator.hide();
            }
        } catch(e) {
            // Amplitude not initialized yet
        }
    };

    //
    // AUDIO ANALYSER FOR VISUALIZER
    //

    function initAudioAnalyser() {
        try {
            var analyser = Amplitude.getAnalyser();
            if (analyser) {
                window.FMLAudioData.analyser = analyser;
                updateAudioData();
            }
        } catch (e) {
            console.log("Audio analyser not available:", e);
        }
    }

    function updateAudioData() {
        if (!window.FMLAudioData.analyser || !window.FMLAudioData.isPlaying) return;

        var analyser = window.FMLAudioData.analyser;
        var bufferLength = analyser.frequencyBinCount;
        var dataArray = new Uint8Array(bufferLength);
        analyser.getByteFrequencyData(dataArray);

        // Calculate frequency bands
        var bassSum = 0, midSum = 0, trebleSum = 0, totalSum = 0;
        var bassCount = Math.floor(bufferLength * 0.1);
        var midCount = Math.floor(bufferLength * 0.4);

        for (var i = 0; i < bufferLength; i++) {
            totalSum += dataArray[i];
            if (i < bassCount) {
                bassSum += dataArray[i];
            } else if (i < bassCount + midCount) {
                midSum += dataArray[i];
            } else {
                trebleSum += dataArray[i];
            }
        }

        window.FMLAudioData.bass = (bassSum / bassCount) / 255;
        window.FMLAudioData.mid = (midSum / midCount) / 255;
        window.FMLAudioData.treble = (trebleSum / (bufferLength - bassCount - midCount)) / 255;
        window.FMLAudioData.intensity = (totalSum / bufferLength) / 255;

        requestAnimationFrame(updateAudioData);
    }

    //
    // QUEUE DISPLAY FUNCTIONS
    //

    window.updateQueueDisplay = updateQueueDisplay;
    function updateQueueDisplay() {
        var songs = [];
        var activeIndex = 0;
        try {
            songs = Amplitude.getSongs() || [];
            activeIndex = Amplitude.getActiveIndex();
        } catch(e) {
            // Amplitude not initialized yet
        }
        var $playlist = $('.white-player-playlist');

        $playlist.empty();

        songs.forEach(function(song, index) {
            var isActive = (index === activeIndex) ? 'amplitude-active-song-container' : '';
            var songHtml = '<div class="white-player-playlist-song ' + isActive + '" data-amplitude-song-index="' + index + '">' +
                '<img src="' + (song.cover_art_url || '/wp-content/uploads/2020/06/art-8-150x150.jpg') + '" />' +
                '<div class="playlist-song-meta">' +
                    '<span class="playlist-song-name">' + song.name + '</span>' +
                    '<span class="playlist-artist-album">' + song.artist + '</span>' +
                '</div>' +
                '<button class="queue-item-remove" data-index="' + index + '" title="Remove from queue">' +
                    '<i class="fas fa-times"></i>' +
                '</button>' +
            '</div>';

            $playlist.append(songHtml);
        });

        // Bind click events for queue items
        $('.white-player-playlist-song').off('click').on('click', function(e) {
            if (!$(e.target).closest('.queue-item-remove').length) {
                var index = $(this).data('amplitude-song-index');
                Amplitude.playSongAtIndex(index);
                updateQueueDisplay();
            }
        });

        // Bind remove button events
        $('.queue-item-remove').off('click').on('click', function(e) {
            e.stopPropagation();
            var index = $(this).data('index');
            removeSongFromQueue(index);
        });
    }

    function appendToSongDisplay(song, index) {
        var $playlist = $('.white-player-playlist');
        var songHtml = '<div class="white-player-playlist-song" data-amplitude-song-index="' + index + '">' +
            '<img src="' + (song.cover_art_url || '/wp-content/uploads/2020/06/art-8-150x150.jpg') + '" />' +
            '<div class="playlist-song-meta">' +
                '<span class="playlist-song-name">' + song.name + '</span>' +
                '<span class="playlist-artist-album">' + song.artist + '</span>' +
            '</div>' +
            '<button class="queue-item-remove" data-index="' + index + '" title="Remove from queue">' +
                '<i class="fas fa-times"></i>' +
            '</button>' +
        '</div>';

        $playlist.append(songHtml);
        Amplitude.bindNewElements();
    }

    function removeSongFromQueue(index) {
        // AmplitudeJS doesn't have a native remove function, so we rebuild
        var songs = Amplitude.getSongs();
        var activeIndex = Amplitude.getActiveIndex();

        if (songs.length <= 1) {
            console.log("Cannot remove the last song from queue");
            return;
        }

        // If removing the currently playing song, pause first
        if (index === activeIndex) {
            Amplitude.pause();
        }

        // Remove the song from array
        songs.splice(index, 1);

        // Reinitialize with remaining songs
        var wasPlaying = window.FMLAudioData.isPlaying;
        var currentTime = Amplitude.getSongPlayedSeconds();
        var newIndex = activeIndex;

        if (index < activeIndex) {
            newIndex = activeIndex - 1;
        } else if (index === activeIndex) {
            newIndex = Math.min(index, songs.length - 1);
        }

        // Re-initialize Amplitude with new song list
        Amplitude.init({
            songs: songs,
            debug: true,
            autoplay: false,
            preload: "metadata",
            start_song: newIndex,
            callbacks: window.FMLAmplitudeCallbacks || {}
        });

        updateQueueDisplay();
    }

    //
    // HELPER FUNCTION TO BUILD SONG OBJECT
    //

    window.buildSongObject = function($el) {
        return {
            "name": $el.data("songname") || "Unknown",
            "artist": $el.data("artistname") || "Unknown Artist",
            "album": $el.data("albumname") || "",
            "url": $el.data("audiosrc"),
            "cover_art_url": $el.data("artsrc") || "/wp-content/uploads/2020/06/art-8-150x150.jpg",
            "song_id": $el.data("songid") || "",
            "permalink": $el.data("permalink") || "",
            "artist_permalink": $el.data("artistpermalink") || "",
            "album_permalink": $el.data("albumpermalink") || ""
        };
    };

});




jQuery(function($) {

    //
    // WHEN A USER PLAYS A SONG (immediate play)
    // Adds the song to the top of the queue and plays it.
    // If the song is already in the queue, it moves it to the top.
    // Uses event delegation so it works after PJAX content swaps.
    //
    $(document).on('click', '.song-play', function(){
        var songObj = window.buildSongObject($(this));
        window.FMLPlaySongAtTop(songObj);
    });

    /**
     * Play a song at the top of the queue.
     * - If queue is empty, initializes Amplitude with this song.
     * - If song is already in the queue, removes the duplicate first.
     * - Inserts the song at index 0 and plays it.
     */
    window.FMLPlaySongAtTop = function(songObj) {
        var currentSongs = [];
        try { currentSongs = Amplitude.getSongs() || []; } catch(e) {}

        // Stop and clean up the old audio element before reinitializing
        try {
            var oldAudio = Amplitude.getAudio();
            if (oldAudio) {
                oldAudio.pause();
                oldAudio.src = '';
                oldAudio.load();
            }
        } catch(e) {}

        if (currentSongs.length === 0) {
            // No queue yet — initialize with this song
            Amplitude.init({
                songs: [songObj],
                debug: true,
                autoplay: true,
                preload: "metadata",
                callbacks: window.FMLAmplitudeCallbacks || {}
            });
        } else {
            // Remove duplicate if it already exists in the queue (match by url)
            var filtered = currentSongs.filter(function(s) {
                return s.url !== songObj.url;
            });

            // Put the new song at the top
            filtered.unshift(songObj);

            // Reinitialize Amplitude with the new queue, playing index 0
            Amplitude.init({
                songs: filtered,
                debug: true,
                autoplay: true,
                preload: "metadata",
                start_song: 0,
                callbacks: window.FMLAmplitudeCallbacks || {}
            });
        }

        Amplitude.bindNewElements();

        // Update player meta immediately
        if (window.updatePlayerMeta) window.updatePlayerMeta();
    };

    //
    // ADD TO QUEUE (without immediate play)
    //
    $(document).on('click', '.song-add-queue', function(e) {
        e.preventDefault();
        e.stopPropagation();

        var songObj = window.buildSongObject($(this));

        // If Amplitude has no songs yet, initialize it first
        var currentSongs = [];
        try { currentSongs = Amplitude.getSongs() || []; } catch(ex) {}

        var newIndex;
        if (currentSongs.length === 0) {
            Amplitude.init({
                songs: [songObj],
                debug: true,
                autoplay: false,
                preload: "metadata",
                callbacks: window.FMLAmplitudeCallbacks || {}
            });
            newIndex = 0;
        } else {
            newIndex = Amplitude.addSong(songObj);
        }
        Amplitude.bindNewElements();

        // Show feedback
        var $btn = $(this);
        var originalText = $btn.html();
        $btn.html('<i class="fas fa-check"></i> Added');
        setTimeout(function() {
            $btn.html(originalText);
        }, 1500);

        console.log("Song added to queue at index:", newIndex);
    });

    //
    // PLAY ALL - Album or Playlist
    //
    $(document).on('click', '.play-all-album, .play-all-playlist', function(e) {
        e.preventDefault();

        var $container = $(this).closest('table, .song-list-container, div').first();
        var $songs = $container.find('.song-play');

        if ($songs.length === 0) {
            // Try finding songs in sibling/parent elements
            $songs = $(this).parent().siblings().find('.song-play');
        }

        if ($songs.length === 0) {
            // Last resort: find all songs on the page within the same section
            $songs = $(this).closest('section, article, .elementor-widget-container').find('.song-play');
        }

        if ($songs.length === 0) {
            console.log("No songs found to play");
            return;
        }

        console.log("Playing all " + $songs.length + " songs");

        // Build songs array
        var songsArray = [];
        $songs.each(function() {
            songsArray.push(window.buildSongObject($(this)));
        });

        // Stop and clean up the old audio element before reinitializing
        try {
            var oldAudio = Amplitude.getAudio();
            if (oldAudio) {
                oldAudio.pause();
                oldAudio.src = '';
                oldAudio.load();
            }
        } catch(e) {}

        // Initialize with all songs and start playing
        Amplitude.init({
            songs: songsArray,
            debug: true,
            autoplay: true,
            preload: "metadata",
            callbacks: window.FMLAmplitudeCallbacks || {}
        });

        Amplitude.bindNewElements();
        if (window.updatePlayerMeta) window.updatePlayerMeta();

        // Show feedback
        var $btn = $(this);
        var originalHtml = $btn.html();
        $btn.html('<i class="fas fa-check"></i> Playing...');
        setTimeout(function() {
            $btn.html(originalHtml);
        }, 2000);
    });

    //
    // ALBUM COVER ART CLICK - Play entire album
    //
    $(document).on('click', '.album-cover-art', function(){
        // Stop and clean up the old audio element before reinitializing
        try {
            var oldAudio = Amplitude.getAudio();
            if (oldAudio) {
                oldAudio.pause();
                oldAudio.src = '';
                oldAudio.load();
            }
        } catch(e) {}

        var albumSongs = [];
        $(".song-play").each(function() {
            albumSongs.push(window.buildSongObject($(this)));
        });

        if (albumSongs.length > 0) {
            Amplitude.init({
                songs: albumSongs,
                debug: true,
                autoplay: true,
                preload: "metadata",
                callbacks: window.FMLAmplitudeCallbacks || {}
            });
            Amplitude.bindNewElements();
            if (window.updatePlayerMeta) window.updatePlayerMeta();
        }
    });

});


//
// VISUALIZER TOGGLE - Controls audio reactivity on the background particles (Three.js)
//
jQuery(function($) {
    var visualizerActive = false;
    var $btn = $('#toggle-visualizer');

    $btn.on('click', function() {
        visualizerActive = !visualizerActive;
        $(this).toggleClass('active', visualizerActive);

        // Toggle audio reactivity on the main Three.js background particles
        // This should affect background-particles.js, NOT player-visualizer.js
        if (typeof window.toggleBackgroundAudioVisualizer === 'function') {
            window.toggleBackgroundAudioVisualizer(visualizerActive);
            console.log('[Visualizer] Three.js background particles:', visualizerActive ? 'ON' : 'OFF');
        } else {
            console.warn('[Visualizer] window.toggleBackgroundAudioVisualizer not found - background-particles.js may not be loaded');
            // Still toggle the visual state so user knows they clicked
            $(this).attr('title', visualizerActive ? 'Visualizer ON (waiting for background)' : 'Toggle visualizer');
        }
    });

    // Expose state globally so background-particles.js can check initial state
    window.isVisualizerActive = function() {
        return visualizerActive;
    };
});


//
// VOLUME MUTE/UNMUTE TOGGLE
//
jQuery(function($) {
    var savedVolume = 80;
    var isMuted = false;

    $('#volume-toggle').on('click', function() {
        isMuted = !isMuted;
        var $icon = $(this).find('i');

        if (isMuted) {
            savedVolume = Amplitude.getConfig().volume || 80;
            Amplitude.setVolume(0);
            $icon.removeClass('fa-volume-up fa-volume-down').addClass('fa-volume-mute');
            $(this).addClass('muted');
            $('.amplitude-volume-slider').val(0);
        } else {
            Amplitude.setVolume(savedVolume);
            $icon.removeClass('fa-volume-mute').addClass(savedVolume > 50 ? 'fa-volume-up' : 'fa-volume-down');
            $(this).removeClass('muted');
            $('.amplitude-volume-slider').val(savedVolume);
        }
    });

    // Update icon when slider changes
    $(document).on('input change', '.amplitude-volume-slider', function() {
        var vol = parseInt($(this).val());
        var $icon = $('#volume-toggle').find('i');

        if (vol === 0) {
            $icon.removeClass('fa-volume-up fa-volume-down').addClass('fa-volume-mute');
            $('#volume-toggle').addClass('muted');
            isMuted = true;
        } else {
            isMuted = false;
            $('#volume-toggle').removeClass('muted');
            $icon.removeClass('fa-volume-mute fa-volume-down fa-volume-up');
            $icon.addClass(vol > 50 ? 'fa-volume-up' : 'fa-volume-down');
            savedVolume = vol;
        }
    });
});
