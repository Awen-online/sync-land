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

        // Set crossOrigin for CORS audio analysis
        setTimeout(function() {
            var audio = Amplitude.getAudio();
            if (audio && !audio.crossOrigin) {
                audio.crossOrigin = "anonymous";
                console.log("[Audio] Set crossOrigin=anonymous on audio element");
            }
        }, 50);

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

    // Initialize the audio analyser after a short delay
    setTimeout(function() {
        initAudioAnalyser();
    }, 500);

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

        if (meta) {
            // Store song data on the button for modal use
            $licenseBtn.data('song-id', meta.song_id || '');
            $licenseBtn.data('song-name', meta.name || '');
            $licenseBtn.data('song-artist', meta.artist || '');
            $licenseBtn.data('song-cover', meta.cover_art_url || '');
            $licenseBtn.data('song-permalink', meta.permalink || '');

            if (meta.song_id) {
                $licenseBtn.show();
            } else {
                $licenseBtn.hide();
            }
        } else {
            $licenseBtn.hide();
        }
    }

    // Expose globally so it can be called after Amplitude reinit
    window.updateLicenseButton = updateLicenseButton;

    //
    // LICENSE MODAL FUNCTIONALITY
    //
    function initLicenseModal() {
        var $modal = $('#license-modal');
        var $overlay = $('#license-modal-overlay');
        var $licenseBtn = $('#license-button');

        // Open modal when license button is clicked
        $licenseBtn.on('click', function(e) {
            e.preventDefault();

            var songId = $(this).data('song-id');
            var songName = $(this).data('song-name');
            var songArtist = $(this).data('song-artist');
            var songCover = $(this).data('song-cover');

            if (!songId) {
                console.log('No song ID available');
                return;
            }

            // Populate modal header with song info
            $('#license-modal-title').text(songName || 'Unknown Song');
            $('#license-modal-artist').text(songArtist || 'Unknown Artist');
            $('#license-modal-cover').attr('src', songCover || '');

            // Show loading state
            $('#license-modal-content').html(
                '<div class="fml-license-modal-loading">' +
                '<i class="fas fa-spinner fa-spin"></i> Loading...' +
                '</div>'
            );

            // Show modal
            $modal.show();
            $overlay.show();
            $('body').css('overflow', 'hidden');

            // Load add-to-cart content via AJAX
            $.ajax({
                url: '/wp-json/FML/v1/license-modal/' + songId,
                method: 'GET',
                success: function(response) {
                    if (response.success && response.html) {
                        $('#license-modal-content').html(response.html);
                        // Reinitialize any cart JS bindings
                        if (window.FMLCart && window.FMLCart.bindAddToCartEvents) {
                            window.FMLCart.bindAddToCartEvents();
                        }
                    } else {
                        $('#license-modal-content').html(
                            '<p style="color: #fc8181; text-align: center;">Failed to load licensing options.</p>'
                        );
                    }
                },
                error: function() {
                    $('#license-modal-content').html(
                        '<p style="color: #fc8181; text-align: center;">Failed to load licensing options. Please try again.</p>'
                    );
                }
            });
        });

        // Close modal
        $('.fml-license-modal-close, #license-modal-overlay').on('click', function() {
            closeLicenseModal();
        });

        // Close on escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $modal.is(':visible')) {
                closeLicenseModal();
            }
        });

        // Close when "View full song page" link is clicked
        $(document).on('click', '.fml-license-modal-link a', function() {
            closeLicenseModal();
            // Allow the link to navigate normally
        });

        function closeLicenseModal() {
            $modal.hide();
            $overlay.hide();
            $('body').css('overflow', '');
        }

        // Expose globally so cart.js can close the modal on success
        window.closeLicenseModal = closeLicenseModal;
    }

    // Initialize modal after DOM ready
    $(document).ready(function() {
        initLicenseModal();
    });

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
    // Uses Web Audio API for real audio reactivity
    // Requires CORS headers on audio source and crossOrigin attribute on audio element
    //

    var audioContext = null;
    var analyserNode = null;
    var sourceNode = null;
    var connectedAudioElement = null;
    var isAnalyserRunning = false;
    var useSimulatedAudio = false; // Fall back to simulated if CORS fails

    function initAudioAnalyser() {
        console.log("[Audio Analyser] Initializing...");

        try {
            var audio = Amplitude.getAudio();
            if (!audio) {
                console.log("[Audio Analyser] No audio element available yet");
                return;
            }

            console.log("[Audio Analyser] Audio element found:", audio);
            console.log("[Audio Analyser] Current src:", audio.src);
            console.log("[Audio Analyser] crossOrigin attribute:", audio.crossOrigin);

            // Set crossOrigin attribute if not already set (required for CORS)
            if (!audio.crossOrigin) {
                console.log("[Audio Analyser] Setting crossOrigin to 'anonymous'");
                audio.crossOrigin = "anonymous";
            }

            // If already connected to this audio element, just ensure loop is running
            if (connectedAudioElement === audio && analyserNode && !useSimulatedAudio) {
                console.log("[Audio Analyser] Already connected to this audio element");
                if (!isAnalyserRunning) {
                    isAnalyserRunning = true;
                    updateAudioData();
                }
                if (audioContext && audioContext.state === 'suspended') {
                    audioContext.resume();
                }
                return;
            }

            // Create AudioContext if needed
            if (!audioContext) {
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
                console.log("[Audio Analyser] Created AudioContext, state:", audioContext.state);
            }

            // Resume context if suspended
            if (audioContext.state === 'suspended') {
                audioContext.resume().then(function() {
                    console.log("[Audio Analyser] AudioContext resumed");
                });
            }

            // Create analyser node
            if (!analyserNode) {
                analyserNode = audioContext.createAnalyser();
                analyserNode.fftSize = 256;
                analyserNode.smoothingTimeConstant = 0.8;
                console.log("[Audio Analyser] Created AnalyserNode, fftSize:", analyserNode.fftSize);
            }

            // Disconnect old source if exists
            if (sourceNode) {
                try {
                    sourceNode.disconnect();
                    console.log("[Audio Analyser] Disconnected old source node");
                } catch(e) {
                    console.log("[Audio Analyser] Error disconnecting old source:", e.message);
                }
                sourceNode = null;
            }

            // Create new source from audio element
            console.log("[Audio Analyser] Creating MediaElementSource...");
            sourceNode = audioContext.createMediaElementSource(audio);
            sourceNode.connect(analyserNode);
            analyserNode.connect(audioContext.destination);

            connectedAudioElement = audio;
            useSimulatedAudio = false;
            window.FMLAudioData.analyser = analyserNode;

            console.log("[Audio Analyser] SUCCESS - Connected to audio element");

            // Start the update loop
            if (!isAnalyserRunning) {
                isAnalyserRunning = true;
                updateAudioData();
            }

        } catch (e) {
            console.error("[Audio Analyser] ERROR:", e.message);
            console.log("[Audio Analyser] Falling back to simulated audio reactivity");
            useSimulatedAudio = true;

            // Start simulated loop
            if (!isAnalyserRunning) {
                isAnalyserRunning = true;
                updateAudioData();
            }
        }
    }

    function updateAudioData() {
        requestAnimationFrame(updateAudioData);

        // Use real analyser if available and working
        if (analyserNode && !useSimulatedAudio) {
            var bufferLength = analyserNode.frequencyBinCount;
            var dataArray = new Uint8Array(bufferLength);
            analyserNode.getByteFrequencyData(dataArray);

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

            // Check if we're getting real data or zeros (CORS issue)
            if (totalSum > 0) {
                window.FMLAudioData.bass = (bassSum / bassCount) / 255;
                window.FMLAudioData.mid = (midSum / midCount) / 255;
                window.FMLAudioData.treble = (trebleSum / (bufferLength - bassCount - midCount)) / 255;
                window.FMLAudioData.intensity = (totalSum / bufferLength) / 255;
            } else if (window.FMLAudioData.isPlaying) {
                // Getting zeros while playing = CORS issue
                console.warn("[Audio Analyser] Getting zeros while playing - possible CORS issue");
                // Don't switch to simulated mid-playback, just log
            } else {
                // Not playing, reset to zero
                window.FMLAudioData.bass = 0;
                window.FMLAudioData.mid = 0;
                window.FMLAudioData.treble = 0;
                window.FMLAudioData.intensity = 0;
            }
        } else if (useSimulatedAudio) {
            // Simulated audio reactivity fallback
            if (window.FMLAudioData.isPlaying) {
                var time = Date.now() * 0.001;
                var beatPhase = (time * 2) % 1;
                var beat = Math.pow(Math.max(0, Math.sin(beatPhase * Math.PI * 2)), 4);

                var bassPulse = Math.pow(Math.max(0, Math.sin(time * 2 * Math.PI)), 2) * 0.7 + beat * 0.3;
                var midPulse = Math.sin(time * 3.5 * Math.PI) * 0.3 + 0.4 + Math.sin(time * 7 * Math.PI) * 0.15;
                var treblePulse = Math.sin(time * 8 * Math.PI) * 0.2 + 0.3 + Math.sin(time * 15 * Math.PI) * 0.1 + Math.random() * 0.1;

                window.FMLAudioData.bass = Math.min(1, Math.max(0, bassPulse));
                window.FMLAudioData.mid = Math.min(1, Math.max(0, midPulse));
                window.FMLAudioData.treble = Math.min(1, Math.max(0, treblePulse));
                window.FMLAudioData.intensity = (bassPulse + midPulse + treblePulse) / 3;
            } else {
                window.FMLAudioData.bass *= 0.95;
                window.FMLAudioData.mid *= 0.95;
                window.FMLAudioData.treble *= 0.95;
                window.FMLAudioData.intensity *= 0.95;
                if (window.FMLAudioData.intensity < 0.01) {
                    window.FMLAudioData.bass = 0;
                    window.FMLAudioData.mid = 0;
                    window.FMLAudioData.treble = 0;
                    window.FMLAudioData.intensity = 0;
                }
            }
        }
    }

    // Expose function to reinitialize analyser (called when Amplitude reinits)
    window.FMLReinitAudioAnalyser = function() {
        console.log("[Audio Analyser] Reinitializing for new audio element...");
        connectedAudioElement = null;
        sourceNode = null;

        setTimeout(function() {
            initAudioAnalyser();
        }, 100);
    };

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

        // Reinitialize audio analyser for the new audio element
        setTimeout(function() {
            if (window.FMLReinitAudioAnalyser) {
                window.FMLReinitAudioAnalyser();
            }
        }, 100);
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

        // Set crossOrigin for CORS audio analysis
        try {
            var audio = Amplitude.getAudio();
            if (audio) {
                audio.crossOrigin = "anonymous";
                console.log("[Audio] Set crossOrigin=anonymous on new audio element");
            }
        } catch(e) {}

        // Update player meta and license button immediately
        if (window.updatePlayerMeta) window.updatePlayerMeta();
        if (window.updateLicenseButton) window.updateLicenseButton();

        // Reinitialize audio analyser for the new audio element
        setTimeout(function() {
            if (window.FMLReinitAudioAnalyser) {
                window.FMLReinitAudioAnalyser();
            }
        }, 100);
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

            // Update player meta and license button for first song
            if (window.updatePlayerMeta) window.updatePlayerMeta();
            if (window.updateLicenseButton) window.updateLicenseButton();

            // Reinitialize audio analyser for the new audio element
            setTimeout(function() {
                if (window.FMLReinitAudioAnalyser) {
                    window.FMLReinitAudioAnalyser();
                }
            }, 100);
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
        if (window.updateLicenseButton) window.updateLicenseButton();

        // Reinitialize audio analyser for the new audio element
        setTimeout(function() {
            if (window.FMLReinitAudioAnalyser) {
                window.FMLReinitAudioAnalyser();
            }
        }, 100);

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
            if (window.updateLicenseButton) window.updateLicenseButton();

            // Reinitialize audio analyser for the new audio element
            setTimeout(function() {
                if (window.FMLReinitAudioAnalyser) {
                    window.FMLReinitAudioAnalyser();
                }
            }, 100);
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
