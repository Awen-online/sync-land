<?php
function song_upload_shortcode($atts) {
    // Shortcode attributes
    $atts = shortcode_atts(array(
        'artist_id' => '',
    ), $atts);

    // Validate artist ID
    $artistID = !empty($atts['artist_id']) ? htmlspecialchars($atts['artist_id']) : (isset($_GET['artist_id']) ? htmlspecialchars($_GET['artist_id']) : '');
    $artistPod = pods('artist', $artistID);

    // Check if artist exists and user is logged in
    if (!$artistPod->exists()) {
        return '<p>Something is wrong. No artist found.</p>';
    }
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to upload songs.</p>';
    }

    // Initialize output buffer
    ob_start();

    // Form processing logic

    if (isset($_POST['rightsholder']) && isset($_POST['termsandcopyright'])) {
        $albumTitle = sanitize_text_field($_POST['album-title']);
        $albumDesc = sanitize_textarea_field($_POST['albumdescription']);
        $releasedate = sanitize_text_field($_POST['releasedate']);
        $albumart = $_FILES['albumart'];
        $albumart_tmp = $_FILES['albumart']['tmp_name'];
        $albumart_name = $_FILES['albumart']['name'];
        $userIP = $_SERVER['REMOTE_ADDR'];
        $contentID = sanitize_text_field($_POST['youtube-contentID']);
        $distros = isset($_POST['distros']) && is_array($_POST['distros']) ? implode(',', array_map('sanitize_text_field', $_POST['distros'])) : '';
        $artist_id = sanitize_text_field($_POST['artistid']);

        // Add nonce verification for security
        if (!isset($_POST['song_upload_nonce']) || !wp_verify_nonce($_POST['song_upload_nonce'], 'song_upload_action')) {
            return '<p>Security check failed.</p>';
        }

        // Create album in Pods
        $albumPod = pods('album');
        $data = array(
            'post_title'   => $albumTitle,
            'album_name'   => $albumTitle,
            'post_content' => $albumDesc,
            'user_ip'      => $userIP,
            'artist'       => $artist_id,
            'distros'      => $distros,
            'content_id'   => $contentID,
            'release_date' => $releasedate,
        );
        $new_album_id = $albumPod->add($data);

        // Handle album art
        if (!empty($albumart_tmp) && !empty($albumart_name)) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $attachment_id = media_handle_upload('albumart', $new_album_id);
            if (is_wp_error($attachment_id)) {
                error_log('Album art upload failed: ' . $attachment_id->get_error_message());
                return '<p>Error uploading album art: ' . esc_html($attachment_id->get_error_message()) . '</p>';
            }
            $albumPod->save(array('cover_art' => $attachment_id), null, $new_album_id);
            set_post_thumbnail($new_album_id, $attachment_id);
        } else {
            return '<p>Error: No album art uploaded.</p>';
        }

        // Create songs in Pods
        $songPod = pods('song');
        $numoftracks = (int)$_POST['numberoftracks'];
        for ($i = 1; $i <= $numoftracks; $i++) {
            $wavormp3 = endsWith($_POST['awslink' . $i], '.wav') ? 'audio_url_lossless' : 'audio_url';
            $data = array(
                'post_title'   => sanitize_text_field($_POST['title' . $i]),
                'artist'       => $artist_id,
                'album'        => $new_album_id,
                'track_number' => $i,
                $wavormp3      => esc_url_raw($_POST['awslink' . $i]),
                'explicit'     => sanitize_text_field($_POST['explicit' . $i]),
                'instrumental' => sanitize_text_field($_POST['instrumental' . $i]),
                'bpm'          => sanitize_text_field($_POST['bpm' . $i]),
                'mood'         => sanitize_text_field($_POST['mood' . $i]),
                'genre'        => sanitize_text_field($_POST['genres' . $i]),
                'user_ip'      => $userIP,
            );
            $songPod->add($data);
        }

        // Publish album
        wp_update_post(array(
            'ID'          => $new_album_id,
            'post_status' => 'publish',
        ));

        // Send email notification
        $current_user = wp_get_current_user();
        $to = 'mc@cullah.com';
        $subject = 'New album submission by ' . esc_html($current_user->user_login);
        $body = json_encode($_POST, JSON_PRETTY_PRINT);
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($to, $subject, $body, $headers);

        // Display success message instead of redirecting
        ob_start();
        ?>
        <div class="song-upload-success" style="text-align: center; padding: 20px; background-color: #dff0d8; border: 1px solid #d6e9c6; border-radius: 5px; margin: 20px 0;">
            <i class="fas fa-check-circle" style="color: #3c763d; font-size: 40px; margin-bottom: 10px;"></i>
            <h2>Album Uploaded Successfully!</h2>
            <p>Your album "<?php echo esc_html($albumTitle); ?>" has been submitted.</p>
            <a href="<?php echo esc_url(home_url('/account/artists')); ?>" class="button" style="display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px;">Go to Artist Dashboard</a>
        </div>
        <?php
        return ob_get_clean();
    }

    // Enqueue styles and scripts
    wp_enqueue_style('song-upload-styles', get_stylesheet_directory_uri() . '/assets/css/song-upload-styles.css');
    wp_enqueue_script('song-upload-script', get_stylesheet_directory_uri() . '/assets/js/simpleUpload.js', array('jquery'), '1.3', true);

    // Inline CSS (from your template)
    ?>
    <style>
        /* Modern Song Upload Styles */
        .col { margin: 0px 0px 5px 0px !important; }

        /* Genre and Mood buttons */
        .genrebtn {
            background-color: #61ce70;
            transition: all 0.2s ease;
        }
        .genrebtn:hover { background-color: #4db85a; transform: scale(1.02); }
        .genrebtn.active, .genrebtn:has(input:checked) {
            background-color: #2d8a3a !important;
            box-shadow: 0 2px 8px rgba(45, 138, 58, 0.4);
        }

        .moodbtn {
            background-color: #13aff0;
            transition: all 0.2s ease;
        }
        .moodbtn:hover { background-color: #0d9bd8; transform: scale(1.02); }
        .moodbtn.active, .moodbtn:has(input:checked) {
            background-color: #0a7eb0 !important;
            box-shadow: 0 2px 8px rgba(10, 126, 176, 0.4);
        }

        .selectbtn {
            font-size: 11px;
            line-height: 1;
            padding: 12px 14px;
            border-radius: 20px;
            cursor: pointer;
            border: none;
            color: white;
            font-weight: 500;
        }

        /* Song card styling */
        .song {
            padding: 20px;
            margin-bottom: 20px;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        /* Progress bar container */
        .progress-container {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            overflow: hidden;
            margin: 15px 0;
            height: 35px;
            position: relative;
        }

        .progressBar {
            background: linear-gradient(90deg, #3E6FAD, #5a8fd4);
            width: 0%;
            height: 100%;
            border-radius: 8px;
            transition: width 0.3s ease-out, background 0.3s ease;
            position: relative;
        }

        .progressBar.success {
            background: linear-gradient(90deg, #28a745, #5cb85c) !important;
        }

        .progressBar.error {
            background: linear-gradient(90deg, #dc3545, #e4606d) !important;
        }

        .progress {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #fff;
            font-weight: 600;
            font-size: 13px;
            z-index: 2;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.5);
        }

        .filename {
            font-weight: 500;
            color: rgba(255, 255, 255, 0.9);
            padding: 8px 0;
            word-break: break-all;
        }

        .hidden { display: none !important; }
        .entry-content { padding-left: 15px; padding-right: 15px; }

        /* Track number header */
        .tracknumber-div {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.8) 0%, rgba(118, 75, 162, 0.8) 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 8px 8px 0 0;
            margin: -20px -20px 20px -20px;
            font-weight: 600;
            font-size: 16px;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }

        #search { width: 90%; }
        .searchicon { color: #5CB85C; }

        .items-collection {
            margin: 25px 0;
            padding: 15px;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .items-collection p {
            margin-bottom: 15px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
        }
        .items-collection label.btn-default.active { background-color: #007ba7; color: #FFF; }
        .items-collection label.btn-default {
            width: auto; border: 1px solid rgba(255, 255, 255, 0.3); margin: 5px; border-radius: 17px; color: rgba(255, 255, 255, 0.9);
        }
        .items-collection .btn-group { width: 90%; }

        /* Radio button styling for dark theme */
        #songs-upload-form input[type="radio"],
        #songs-upload-form input[type="checkbox"] {
            accent-color: #667eea;
        }

        .span_1_of_1 { padding-top: 12px; padding-bottom: 12px; }
        body .oceanwp-row .col { padding: 0 3px; }
        .required:after { content: " *"; color: #dc3545; }
        .visually-hidden { opacity: 0; width: 5px; position: absolute; }

        .box {
            display: inline-block; width: 70px; height: 70px; background-color: rgba(0, 0, 0, 0.5);
            border: 4px dashed rgba(255, 255, 255, 0.3); color: rgba(255, 255, 255, 0.5); font-size: 50px; text-align: center; padding: 30px;
        }

        /* Overall form styling for dark background */
        #songs-upload-form {
            color: rgba(255, 255, 255, 0.9);
        }
        #songs-upload-form h1,
        #songs-upload-form h2,
        #songs-upload-form h3 {
            color: #fff;
        }
        #content.site-content {
            color: rgba(255, 255, 255, 0.9);
        }
        #content.site-content h1 {
            color: #fff;
        }
        #content.site-content a {
            color: #667eea;
        }
        #content.site-content a:hover {
            color: #8fa4f0;
        }

        /* Upload button styling */
        .uploadbtn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        .uploadbtn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        .uploadbtn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* File input styling */
        .file-input {
            padding: 10px;
            border: 2px dashed rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            width: 100%;
            cursor: pointer;
            transition: border-color 0.2s;
            background: rgba(0, 0, 0, 0.3);
            color: #fff;
        }
        .file-input:hover {
            border-color: #667eea;
        }

        /* Form inputs */
        #songs-upload-form input[type="text"],
        #songs-upload-form input[type="number"],
        #songs-upload-form input[type="date"],
        #songs-upload-form textarea,
        #songs-upload-form select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: rgba(0, 0, 0, 0.5);
            color: #fff;
        }
        #songs-upload-form input::placeholder,
        #songs-upload-form textarea::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        #songs-upload-form input:focus,
        #songs-upload-form textarea:focus,
        #songs-upload-form select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.3);
        }
        #songs-upload-form label {
            color: rgba(255, 255, 255, 0.9);
        }
        #songs-upload-form select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='white' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 35px;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }
        #songs-upload-form select option {
            background-color: #1a1a1a;
            color: #fff;
        }

        /* Submit button */
        #songs-upload-form input[type="submit"] {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 30px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
        }
        #songs-upload-form input[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }

        /* Album art preview */
        #blah {
            max-width: 200px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
            margin-top: 10px;
            border: 2px solid rgba(255, 255, 255, 0.1);
        }

        /* Album art hint text */
        #songs-upload-form div[style*="font-size:80%"] {
            color: rgba(255, 255, 255, 0.6);
        }

        /* Distribution section */
        .distro-collection {
            margin-top: 20px;
        }
        .distro-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .distrobtn {
            background-color: rgba(255, 255, 255, 0.15);
            color: white !important;
            margin: 0;
            transition: all 0.2s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .distrobtn:hover {
            background-color: rgba(255, 255, 255, 0.25);
            transform: scale(1.02);
        }
        .distrobtn:has(input:checked),
        .distrobtn.active {
            background-color: #007bff !important;
            border-color: #007bff;
            box-shadow: 0 2px 8px rgba(0, 123, 255, 0.4);
        }

        /* Upload status indicator */
        .upload-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        .upload-status.pending { background: #ffc107; color: #000; }
        .upload-status.uploading { background: #17a2b8; color: #fff; }
        .upload-status.success { background: #28a745; color: #fff; }
        .upload-status.error { background: #dc3545; color: #fff; }
    </style>

    <?php
    // Inline JavaScript (from your template)
    wp_add_inline_script('song-upload-script', '
        (function($) {
            $(document).ready(function() {
                $(".overlay").remove();
                $("#albumart").change(function() { readURL(this); });
                for (var i = 0; i < 20; i++) {
                    $("#songs-number").append(new Option(i, i));
                }
                $("#songs-number").on("change", function() {
                    var songDivHTML = "<div class=\"song\">" + $(".song").html() + "</div>";
                    $(".song").remove();
                    var numOfTracks = $(this).val();
                    for (var i = 1; i <= numOfTracks; i++) {
                        var $div = $("<div>").html(songDivHTML);
                        $div.find("input[name=tracknumber]").attr("value", i);
                        $div.find(".tracknumber-value").html(i);
                        var inputs = $div.find("input");
                        $.each(inputs, function(index, elem) {
                            var jElem = $(elem);
                            var name = jElem.prop("name");
                            name = name.replace(/\d+/g, "");
                            if (jElem.hasClass("inputchk")) {
                                name += i + "[]";
                            } else {
                                name += i;
                            }
                            jElem.prop("name", name);
                        });
                        $("#songs-upload").append($div.html());
                        $(".agree-and-submit").removeClass("hidden");
                        $(".numberoftracks").attr("value", numOfTracks);
                    }
                    initializeSongUploads();
                    var requiredCheckboxes = $(".inputchk:checkbox[required]");
                    requiredCheckboxes.on("change", function(e) {
                        var checkboxGroup = requiredCheckboxes.filter("[name=\"" + $(this).attr("name") + "\"]");
                        var isChecked = checkboxGroup.filter(":checked").length;
                        var maxNumChecked = $(this).data("maxlength");
                        if ((isChecked > 0) && (isChecked <= maxNumChecked)) {
                            checkboxGroup.prop("required", false);
                        } else {
                            checkboxGroup.prop("required", true);
                        }
                        checkboxGroup.each(function() {
                            if ((isChecked > 0) && (isChecked <= maxNumChecked)) {
                                this.setCustomValidity("");
                            } else {
                                this.setCustomValidity("Please select 1-" + maxNumChecked + " items. You\'ve selected " + isChecked + ".");
                            }
                        });
                    });
                    requiredCheckboxes.trigger("change");
                });
            });

            function readURL(input) {
                if (input.files && input.files[0]) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        var img = new Image;
                        var minW = 1000;
                        var minH = 1000;
                        var maxW = 5000;
                        var maxH = 5000;
                        img.src = e.target.result;
                        img.onload = function() {
                            $("#blah").removeClass("hidden");
                            $("#blah").attr("src", img.src);
                            if (img.width < minW || img.height < minH || img.width > maxW || img.height > maxH) {
                                input.setCustomValidity("Image height and width must be between " + minH + "px - " + maxH + "px. Yours is " + img.width + " x " + img.height);
                                input.reportValidity();
                            } else {
                                if (img.width === img.height) {
                                    input.setCustomValidity("");
                                    input.reportValidity();
                                } else {
                                    input.setCustomValidity("Please upload a square image.");
                                    input.reportValidity();
                                }
                            }
                        };
                    };
                    reader.readAsDataURL(input.files[0]);
                }
            }

            function initializeSongUploads() {
                $(".uploadbtn").off("click").on("click", function(event) {
                    event.preventDefault();
                    event.stopPropagation();

                    var uploadButton = $(this);
                    var songDiv = $(this).closest(".song");
                    var fileinput = songDiv.find("input[type=file].file-input");

                    // Check if file is selected
                    if (!fileinput[0] || !fileinput[0].files || !fileinput[0].files[0]) {
                        alert("Please select a file first");
                        return false;
                    }

                    var file = fileinput[0].files[0];
                    console.log("Starting upload for:", file.name);

                    // Disable button during upload
                    uploadButton.prop("disabled", true).text("Uploading...");

                    fileinput.simpleUpload("https://www.soil.sync.land/s3-upload.php", {
                        allowedExts: ["mp3", "wav"],
                        expect: "json",
                        xhrFields: {
                            withCredentials: true
                        },
                        start: function(file) {
                            console.log("Upload started:", file.name);
                            songDiv.find(".filename").html("<strong>" + file.name + "</strong> <span class=\"upload-status uploading\">Uploading</span>");
                            songDiv.find(".progress").html("0%");
                            songDiv.find(".progressBar").removeClass("success error").css("width", "0%");
                            fileinput.prop("disabled", true);
                        },
                        progress: function(progress) {
                            var round = Math.round(progress);
                            if (round >= 100) { round = 99; }
                            songDiv.find(".progress").html(round + "%");
                            songDiv.find(".progressBar").css("width", progress + "%");
                            console.log("Upload progress:", round + "%");
                        },
                        success: function(data) {
                            console.log("Upload response:", data);

                            if (data && data.success) {
                                songDiv.find(".progress").html("Complete!");
                                songDiv.find(".progressBar").addClass("success").css("width", "100%");
                                songDiv.find(".filename").html("<strong>" + file.name + "</strong> <span class=\"upload-status success\">Uploaded</span>");
                                songDiv.find("input.awslink").val(data.url);

                                // Remove upload elements
                                uploadButton.remove();
                                fileinput.remove();
                                songDiv.find("input.visually-hidden").remove();
                                songDiv.find(".mp3Label").remove();

                                // Show uploaded URL (optional debug)
                                console.log("File uploaded to:", data.url);
                            } else {
                                var errorMsg = (data && data.error) ? data.error : "Upload failed";
                                songDiv.find(".progress").html("Failed: " + errorMsg);
                                songDiv.find(".progressBar").addClass("error").css("width", "100%");
                                songDiv.find(".filename").html("<strong>" + file.name + "</strong> <span class=\"upload-status error\">Failed</span>");
                                uploadButton.prop("disabled", false).text("Retry Upload");
                                fileinput.prop("disabled", false);
                                console.error("Upload failed:", errorMsg);
                            }
                        },
                        error: function(error) {
                            console.error("Upload error:", error);
                            var errorMsg = error.message || "Network error";

                            // Check for CORS error
                            if (error.name === "RequestError") {
                                errorMsg = "Upload failed - please check your connection";
                            }

                            songDiv.find(".progress").html("Error: " + errorMsg);
                            songDiv.find(".progressBar").addClass("error").css("width", "100%");
                            songDiv.find(".filename").html("<strong>" + file.name + "</strong> <span class=\"upload-status error\">Error</span>");
                            uploadButton.prop("disabled", false).text("Retry Upload");
                            fileinput.prop("disabled", false);
                        }
                    });
                });
            }
        })(jQuery);
    ');

    // Get genres and moods
    $genrePod = pods('genre', array('limit' => -1));
    $moodPod = pods('mood', array('limit' => -1));

    // Form HTML
    ?>
    <div id="content" class="site-content" role="main">
        <h1 class="aligncenter">Upload Album/EP/Single for <?php echo esc_html($artistPod->display('artist_name')); ?></h1>
        <a class="" href="/account/artists">
            <i aria-hidden="true" style="float:left" class="fas fa-arrow-left"></i> Return to Account
        </a>
        <div class="entry-content entry clr">
            <form id="songs-upload-form" method="post" enctype="multipart/form-data">
                <div class="oceanwp-row clr" style="margin: 0 0;">
                    <div class="span_1_of_1"><label class="required">Album Title: </label><input name="album-title" type="text" placeholder="" required></div>
                    <div class="span_1_of_1"><label class="required">Description: </label><textarea placeholder="Enter album description..." name="albumdescription" cols="30" rows="4"></textarea></div>
                    <div class="span_1_of_1">
                        <label for="releasedate" class="required">Release Date:</label>
                        <input type="date" id="releasedate" name="releasedate" required>
                    </div>
                    <div class="span_1_of_1">
                        <label class="required">Album Art: </label><input type="file" id="albumart" name="albumart" accept="image/*" required>
                    </div>
                    <div style="margin-top:-10px;font-size:80%;">You must upload an image as a square and at least 1000px</div>
                    <div><img id="blah" class="hidden" src="#" alt="your image" width="200px"/></div>
                    <br/>
                    <div class="items-collection distro-collection">
                        <p>These song(s) have also been submitted on...</p>
                        <div class="distro-buttons">
                            <?php
                            $distros = ['distrokid', 'tunecore', 'cdbaby', 'amuse', 'songtradr', 'ditto', 'landr', 'other'];
                            foreach ($distros as $distro) {
                                echo "<label class='btn button distrobtn selectbtn'><input class='inputchk' type='checkbox' name='distros[]' value='$distro'>" . ucfirst($distro) . "</label>";
                            }
                            ?>
                        </div>
                    </div>
                    <div class="span_1_of_1">
                        <label class="required">
                            These song(s) are currently in the <a target="_blank" href="https://en.wikipedia.org/wiki/Content_ID_(system)">YouTube Content ID system</a>.
                        </label>
                        <div><label><input type="radio" name="youtube-contentID" value="Yes" required> Yes</label></div>
                        <div><label><input type="radio" name="youtube-contentID" value="No" required> No</label></div>
                        <div><label><input type="radio" name="youtube-contentID" value="I Don't Know" required> I Don't Know</label></div>
                    </div>
                    <div class="span_1_of_1 clr"><label class="required">Number of Songs</label>
                        <select type="number" id="songs-number" style="width:100px;"></select>
                    </div>
                </div>
                <div id="songs-upload" class="oceanwp-row clr">
                    <div class="hidden song">
                        <div class="span_1_of_1 tracknumber-div">TRACK <span class="tracknumber-value"></span></div>
                        <input class="hidden" name="tracknumber" type="number" readonly>
                        <div class="span_1_of_1 filename"></div>
                        <div class="span_1_of_1 progress-container">
                            <div class="progressBar"></div>
                            <div class="progress"></div>
                        </div>
                        <div class="span_1_of_1"><label class="mp3Label required">Audio File (MP3,WAV): </label>
                            <input required type="file" name="file" class="file-input" accept=".mp3,.wav,audio/mpeg,audio/wav">
                            <input required type="file" name="file-upload-button" class="visually-hidden" oninvalid="this.setCustomValidity('Please Upload your song.')" oninput="this.setCustomValidity('')">
                            <button type="button" class="uploadbtn">Upload File</button>
                        </div>
                        <div class="span_1_of_1"><label class="required">Song Title: </label><input name="title" type="text" placeholder="" required></div>
                        <div class="span_1_of_1"><label>BPM: </label><input name="bpm" type="number" placeholder=""></div>
                        <div class="span_1_of_1"><label class="required">Is Explicit:</label>
                            <div><label><input type="radio" name="explicit" value="1" required> Yes</label></div>
                            <div><label><input type="radio" name="explicit" value="0" required> No</label></div>
                        </div>
                        <div class="span_1_of_1"><label class="required">Is Instrumental:</label>
                            <div><label><input type="radio" name="instrumental" value="1" required> Yes</label></div>
                            <div><label><input type="radio" name="instrumental" value="0" required> No</label></div>
                        </div>
                        <div class="span_1_of_1 items-collection"><p class="required">Mood (pick 1-3):</p>
                            <div class="oceanwp-row clr">
                                <?php
                                if ($moodPod->total() > 0) {
                                    $moodPod->reset(); // Reset to start of results
                                    while ($moodPod->fetch()) {
                                        echo "<div class='span_1_of_4 col'>";
                                        echo "<label class='btn button moodbtn selectbtn'><input class='inputchk mood-checkbox' data-maxlength='3' type='checkbox' name='mood' value='" . esc_attr($moodPod->id()) . "'>" . esc_html($moodPod->display('title')) . "</label>";
                                        echo "</div>";
                                    }
                                }
                                ?>
                            </div>
                        </div>
                        <div class="span_1_of_1 items-collection"><p class="required">Genre (pick 1-3):</p>
                            <div class="oceanwp-row clr">
                                <?php
                                if ($genrePod->total() > 0) {
                                    $genrePod->reset(); // Reset to start of results
                                    while ($genrePod->fetch()) {
                                        echo "<div class='span_1_of_4 col'>";
                                        echo "<label class='btn button genrebtn selectbtn'><input class='inputchk genre-checkbox' data-maxlength='3' type='checkbox' name='genres' value='" . esc_attr($genrePod->id()) . "'>" . esc_html($genrePod->display('title')) . "</label>";
                                        echo "</div>";
                                    }
                                }
                                ?>
                            </div>
                        </div>
                        <input class="awslink" type="hidden" name="awslink">
                    </div>
                </div>
                <div class="agree-and-submit hidden">
                    <input class="numberoftracks" type="hidden" name="numberoftracks">
                    <input class="artistid" type="hidden" name="artistid" value="<?php echo esc_attr($artistID); ?>">
                    <div class="span_1_of_1"><label class="required"><input required name="rightsholder" type="checkbox"> I am the rights-holder of these songs and/or authorized to license them.</label></div>
                    <div class="span_1_of_1"><label class="required"><input required name="termsandcopyright" type="checkbox"> I have read and agree to the <a href="/terms-of-use-copyright-policy/" target="_blank"> Terms of Use and Copyright Policy.</a></label></div>
                    <!-- Inside the form HTML -->
                    <?php wp_nonce_field('song_upload_action', 'song_upload_nonce'); ?>
                    <input type="submit" value="Submit">
                </div>
            </form>
        </div>
    </div>
    <?php

    return ob_get_clean();
}
add_shortcode('song_upload_form', 'song_upload_shortcode');

// Helper function for endsWith
function endsWith($haystack, $needle) {
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }
    return (substr($haystack, -$length) === $needle);
}

/**
 * Insert an attachment from an URL address.
 *
 * @param  String $url 
 * @param  Int    $post_id 
 * @param  Array  $meta_data 
 * @return Int    Attachment ID
 */
function crb_insert_attachment_from_url($url, $post_id = null) {

	if( !class_exists( 'WP_Http' ) )
		include_once( ABSPATH . WPINC . '/class-http.php' );

	$http = new WP_Http();
	$response = $http->request( $url );
	if( $response['response']['code'] != 200 ) {
		return false;
	}

	$upload = wp_upload_bits( basename($url), null, $response['body'] );
	if( !empty( $upload['error'] ) ) {
		return false;
	}

	$file_path = $upload['file'];
	$file_name = basename( $file_path );
	$file_type = wp_check_filetype( $file_name, null );
	$attachment_title = sanitize_file_name( pathinfo( $file_name, PATHINFO_FILENAME ) );
	$wp_upload_dir = wp_upload_dir();

	$post_info = array(
		'guid'				=> $wp_upload_dir['url'] . '/' . $file_name, 
		'post_mime_type'	=> $file_type['type'],
		'post_title'		=> $attachment_title,
		'post_content'		=> '',
		'post_status'		=> 'inherit',
	);

	// Create the attachment
	$attach_id = wp_insert_attachment( $post_info, $file_path, $post_id );

	// Include image.php
	require_once( ABSPATH . 'wp-admin/includes/image.php' );

	// Define attachment metadata
	$attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );

	// Assign metadata to attachment
	wp_update_attachment_metadata( $attach_id,  $attach_data );

	return $attach_id;

}