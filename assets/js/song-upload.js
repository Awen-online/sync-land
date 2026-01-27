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
            initializeCheckboxLimits();
        });

        function initializeCheckboxLimits() {
            $(".song").each(function() {
                var $song = $(this);
                var $moodCheckboxes = $song.find(".mood-checkbox");
                $moodCheckboxes.on("change", function() {
                    var checkedCount = $moodCheckboxes.filter(":checked").length;
                    if (checkedCount >= 3) {
                        $moodCheckboxes.not(":checked").prop("disabled", true);
                    } else {
                        $moodCheckboxes.prop("disabled", false);
                    }
                    $moodCheckboxes.each(function() {
                        if (checkedCount > 0 && checkedCount <= 3) {
                            this.setCustomValidity("");
                        } else {
                            this.setCustomValidity("Please select 1-3 moods. You\'ve selected " + checkedCount + ".");
                        }
                    });
                });
                $moodCheckboxes.trigger("change");

                var $genreCheckboxes = $song.find(".genre-checkbox");
                $genreCheckboxes.on("change", function() {
                    var checkedCount = $genreCheckboxes.filter(":checked").length;
                    if (checkedCount >= 3) {
                        $genreCheckboxes.not(":checked").prop("disabled", true);
                    } else {
                        $genreCheckboxes.prop("disabled", false);
                    }
                    $genreCheckboxes.each(function() {
                        if (checkedCount > 0 && checkedCount <= 3) {
                            this.setCustomValidity("");
                        } else {
                            this.setCustomValidity("Please select 1-3 genres. You\'ve selected " + checkedCount + ".");
                        }
                    });
                });
                $genreCheckboxes.trigger("change");
            });
        }

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
                        } else if (img.width !== img.height) {
                            input.setCustomValidity("Please upload a square image.");
                            input.reportValidity();
                        } else {
                            input.setCustomValidity("");
                            input.reportValidity();
                        }
                    };
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function initializeSongUploads() {
            $(".uploadbtn").click(function(event) {
                event.stopPropagation();
                var uploadButton = $(this);
                var songDiv = $(this).parent();
                var fileinput = songDiv.children("input[type=file].file-input");
                fileinput.simpleUpload("https://www.soil.sync.land/s3-upload.php", {
                    allowedExts: ["mp3", "wav"],
                    expect: "json",
                    dataType: "json",
                    start: function(file) {
                        songDiv.find(".filename").html(file.name);
                        songDiv.find(".progress").html("");
                        songDiv.find(".progressBar").css("background-color", "#005b8f");
                        songDiv.find(".progressBar").width(0);
                        fileinput.attr("disabled", "disabled");
                    },
                    progress: function(progress) {
                        var round = Math.round(progress);
                        if (round == 100) { round = 99; }
                        songDiv.find(".progress").html("Progress: " + round + "%");
                        songDiv.find(".progressBar").width(progress + "%");
                    },
                    success: function(data) {
                        if (!data.success) {
                            songDiv.find(".progress").html("Failure!<br>");
                            songDiv.find(".progressBar").css("background-color", "#d9534f");
                        } else {
                            songDiv.find(".progress").html("Success!");
                            songDiv.find(".progressBar").css("background-color", "#5cb85c");
                            songDiv.find("input.awslink").val(data.url);
                            uploadButton.remove();
                            songDiv.find("input.file-input").remove();
                            songDiv.find("input.visually-hidden").remove();
                            songDiv.find(".mp3Label").remove();
                        }
                    },
                    error: function(error) {
                        songDiv.find(".progress").html("<span style=\'color:red\'>Failure!<br>" + error.name + ": " + error.message + "</span>");
                        songDiv.find(".progressBar").css("background-color", "#d9534f");
                        console.log(error);
                    }
                });
            });
        }
    });
 })(jQuery);