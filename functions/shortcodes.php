<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


// Get an ordered list of each song on the album and its information
add_shortcode('paypal_donate_display', 'paypal_donate_display');
function paypal_donate_display( $artistID ){
    
//    $artistID = $atts["artistID"];
    $artistPod = pods("artist",$artistID);
    $paypalAddress = $artistPod->display("paypal_email");
    $artistName = $artistPod->display("");
    
    echo '<div>
                <form name="_xclick" action="https://www.paypal.com/cgi-bin/webscr" method="post">
                        <div class="elementor-form-fields-wrapper elementor-labels-above">
                                <input type="hidden" name="cmd" value="_xclick">
                                <input type="hidden" name="business" value="'.$paypalAddress.'">
                                <input type="hidden" name="item_name" value="Donation for '.$artistName.'">
                                <input type="hidden" name="currency_code" value="USD">
                                <input type="hidden" name="no_note" value="0">
                                <div class="elementor-field-type-text elementor-field-group elementor-column elementor-field-group-name elementor-col-100" style="margin-bottom:10px">
                                        <input name="cn" placeholder="Leave a note" class="elementor-field elementor-size-sm elementor-field-textual">
                                </div>
                                <div class="elementor-field-type-text elementor-field-group elementor-column elementor-field-group-name elementor-col-100" style="margin-bottom:10px">
                                        <input name="amount" required="required" required="true" placeholder="$15" class="elementor-field elementor-size-sm elementor-field-textual">
                                </div>
                                <div class="elementor-field-type-text elementor-field-group elementor-column elementor-field-group-name elementor-col-100" style="margin-bottom:10px;">
                                        <button class="elementor-button elementor-size-sm" type="submit" style="width:100%;"><i class="fab fa-paypal"></i> Donate with PayPal</button>
                                </div>
                        </div>
                </form>
        </div>';
}


    
// Get an ordered list of each song on the album and its information
add_shortcode('sort_album_list_songs', 'sort_album_list_songs');
function sort_album_list_songs( $albumID ){
    
    ?>
    <style>
        /* table{background-color: white; margin: 0; padding: 0;}
        tr {}
        tr:hover{background-color: #F8F8F8;}
        td {color: #162B47;} */
        .label a{
            color: white;
        }
        .track_information_title{
            color:white !important;
        }
        .track_information_artist{
            color:white !important;
        }
    </style>
    <table>
        
    <?php

    $songList = do_shortcode('[pods name="song" where="album.ID='.$albumID.'" orderby="LENGTH(track_number.meta_value),track_number.meta_value ASC"]{@ID};;{@track_number};;{@post_title};;{@audio_url};;{@permalink}*[/pods]'); // get song list, delineate with comma
    $songsArray1 = explode("*", $songList); // create array by exploding the songlist by the comma delineation, array_pop to remove final blank array value
    $songsArray = array_filter($songsArray1); // removing "null" or blank array entries using "array_filter"
    
    // find artist name via pods magic tags 
    $artistName = do_shortcode('[pods]{@artist.post_title}[/pods]');
    $artistPermalink = do_shortcode('[pods]{@artist.permalink}[/pods]');
    
    // find cover art URL name via pods magic tags
    $coverArtURL = do_shortcode('[pods]{@post_thumbnail_url}[/pods]');
        
    foreach( $songsArray as $key => $song ){
        
        // Establishing key variables from earlier magic tag generated array
        $songInfoArray = explode(";;",$song);
        
//        print_r($song);
//       print_r($songInfoArray);
        
        $songID = $songInfoArray[0];
        $trackNumber = $songInfoArray[1];
        $songName = addslashes($songInfoArray[2]);
        $songPlayURL = htmlentities($songInfoArray[3]);
        $songPermalink = $songInfoArray[4];

        // getting individual taxonomy values with "get_the_term_list()" wordpress function
        $songGenres = get_the_term_list($songID,"genre","","*");
        $songGenreArray = explode("*", $songGenres);
        $songMoods = get_the_term_list($songID,"mood","","*");
        $songMoodArray = explode("*", $songMoods);
        
        ?>      

        <!-- Song display containers-->
        <tr>
            <td style="width: 80px; height: 80px; padding: 0;">
                <?php //echo $songPod->template("Song Embed Code"); ?>
                <button type="button"
                        class="song-play" 
                        data-audiosrc="<?php echo $songPlayURL; ?>"
                        data-songname="<?php echo $songName; ?>"
                        data-artistname="<?php echo $artistName; ?>"
                        data-artsrc="<?php echo $coverArtURL; ?>"
                        style="width: 80px; height: 80px; background-image: url(<?php echo $coverArtURL; ?>); background-repeat: no-repeat; background-size: contain; border-color: #e0e0e0;" title="Play">
                    <i class="fas fa-play fa-lg" style="color: #FC0202;"></i>
                </button>
            </td>
            <td style=" width: auto; vertical-align: middle; word-wrap: normal; line-height: 1.2em; padding: 0% 1%;">
                <div>
                    <a href="<?php echo $songPermalink; ?>" title="Song Page" class="track_information_title" style="font-weight: bold;">
                        <?php echo $trackNumber.". ".$songName; ?>
                    </a>
                </div>
                <div>
                    <a href="<?php echo $artistPermalink; ?>" title="Artist Page" class="track_information_artist">
                        <?php echo $artistName; ?>
                    </a>
                </div>
                <div>
                    <?php 
                    if(!empty($songGenreArray)){ 
                        foreach ($songGenreArray as $key => $value) { ?>
                    <span class="label label-info"><?php echo $songGenreArray[$key]; ?></span>
                    <?php } }
                    if(!empty($songMoodArray)){ 
                        foreach ($songMoodArray as $key => $value) { ?>
                            <span class="label label-warning"><?php echo $songMoodArray[$key]; ?></span>
                    <?php } } ?>    
                </div>
            </td>
            <td style="text-align: center; vertical-align: middle; width: 20%; padding-right: 5px;">
                <a  href="<?php echo $songPermalink; ?>" 
                    class="elementor-button-link elementor-button elementor-size-sm" 
                    role="button" 
                    title="License & Download"
                    style="background-color: #E7565A; border-radius: 15% 15% 15% 15%; padding: 10% 10% 10% 10%; icon-spacing: 0%;">
                    <span class="elementor-button-content-wrapper">     
                        <span class="elementor-button-icon elementor-align-icon-center">         
                            <i aria-hidden="true" class="fas fa-download">    
                            </i>			     
                        </span>       
                    </span> 
                </a>  
            </td>
        </tr>
        
    <?php 
    }
    ?>  
        
    </table>

    <?php
       
} // End of function "sort_album_list_songs()"


//SINGLE ARTIST DISPLAY
//THIS is a list display
add_shortcode('artists_display', 'artists_display');
function artists_display( $artistID ){
      
    $params = array(
        'where' => 't.post_status = "Publish"',
        "orderby" => "RAND() DESC"
    );
    $artists = pods('artist', $params);
    
    echo '<div class="container-fluid">';
    echo '<div class="row">';
    
    if (0 < $artists->total()) {
        while ($artists->fetch()) {
            $artist = $artists->export();
           
            $artistID = $artist["ID"];
            $artistPod = pods("artist",$artistID);
            
            $firstAlbumID = array_pop($artist["albums"])["ID"];
            $albumPod = pods("album", $firstAlbumID);
            
//            $publicString = substr($firstAlbumCover, strpos($publicString, "/wp-content"), strlen($publicString));
            
            
            $artistName = do_shortcode('[pods name="artist" id="'.$artistID.'"]{@artist_name}[/pods]'); 
            $artistPermalink = do_shortcode('[pods name="artist" id="'.$artistID.'"]{@permalink}[/pods]'); 
            $coverArtURL = wp_get_attachment_image_src( $artistID, 'medium')[0];
            if(empty($coverArtURL)){
                $coverArtURL = wp_get_attachment_image_src( $artist["profile_image"]["ID"], 'medium')[0];
            }
//            $profile_pic = $artistPod->display("profile_image");
            
//            wp_get_attachment_image($profilePicID, "medium")
//            
//            if(!empty($profile_pic)){
//                $coverArtURL = $profile_pic;
//            }else{
//                $coverArtURL = $albumPod->display("cover_art");
//            }
            
    
                   
            echo ''
                . '<div class="image-block col-sm-4">';
            echo '<a href='.$artistPermalink.'>'
                    . '<img class=\'img-responsive\' src=\''.$coverArtURL.'\' />'
                    . '<div class="centered card-body text-white rgba-black-light p-2">'.$artistName.'</div>'
                . '</a>';
            echo '</div>';
        }

    }else{
    //            $success = false;
    //            $error = "no playlists found";
        }
        echo '</div>'
    //    . '</div>'
        . '</div>';
    
}
function getAlbumPhoto($artistID){
    
}


//SINGLE PLAYLIST DISPLAY SONGS
//THIS is a list display
add_shortcode('playlists_songs_display', 'playlists_songs_display');
function playlists_songs_display( $args ){
    
    global $post;
    $current_id = $post->ID;
    
//    $playlistID = $args["playlistID"];
    $playlistPod = pods("playlist",$current_id)->export();
    if(!empty($playlistPod)){
        foreach($playlistPod["songs"] as $song){
           $songID = $song["ID"];
           single_song_display($songID); 
        }
    }
    
   
 
}

//TAXONOMY DISPLAY
add_shortcode('taxonomy_display', 'taxonomy_display');
function taxonomy_display( $args ){
    $taxonomy = $args["taxonomy"];
    $terms = get_terms( array(
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
    ) );
    
    
    //ARRAY
//    array(1) {
//        [0]=>
//        object(WP_Term) (11) {
//          ["term_id"]=>  //int
//          ["name"]=>   //string 
//          ["slug"]=>  //string 
//          ["term_group"]=>  //int
//          ["term_taxonomy_id"]=> //int
//          ["taxonomy"]=>   //string
//          ["description"]=>    //string
//          ["parent"]=> //int
//          ["count"]=>  // int
//          ["filter"]=> //string
//          ["meta"]=> array(0) { // presumably this would be some returned meta-data?
//          }
//        }
//      }
    
    
    foreach($terms as $key => $term){
        
        echo '<a href="'.get_term_link($term).'"> '
            . '<div class="col-md-4">'.$term->name.'</div>';
        echo '</a>';

//        $string .= '$term';
    }
//    print_r($terms);
    
}

// homepage playlist display
add_shortcode('playlist_display', 'playlist_display');
function playlist_display( $atts ){
    $params = array(
        'where' => 't.post_status = "Publish"',
        "orderby" => "RAND() DESC"
    );
    $playlists = pods('playlist', $params);
    
    echo '<div class="container-fluid">';
    echo '<div class="row">';
    
    if (0 < $playlists->total()) {
        while ($playlists->fetch()) {
            $playlist = $playlists->export();
            $playlistID = $playlist["ID"];
            $playlistURL = get_permalink($playlistID);
            $playlistName = $playlist["post_name"];
            $playlistAuthorID = $playlist["post_author"];
            $firstSong = $playlist["songs"];
            if(!empty($firstSong)){

                $firstSongID = key($firstSong);
                $firstSongAlbumID = $firstSong[$firstSongID]['album']["ID"];
//                print_r($firstSongAlbumID);
//                echo "key: $firstSongID";
               
                $firstSongThunbnail = get_the_post_thumbnail_url( $firstSongAlbumID, 'medium' );
            }else{
                $firstSongThunbnail = "/wp-content/uploads/2022/05/Untitled-1.jpg";
            }

            
            
            echo ''
                . '<div class="image-block col-sm-4">';
            echo '<a href='.$playlistURL.'>'
                    . '<img class=\'img-responsive\' src=\''.$firstSongThunbnail.'\' />'
                    . '<div class="centered card-body text-white rgba-black-light p-2">'.$playlistName.'</div>'
                . '</a>';
            echo '</div>';
            
        }
    }else{
//            $success = false;
//            $error = "no playlists found";
    }
    
    echo '</div>'
//    . '</div>'
    . '</div>';
}

//SINGLE SONG DISPLAY
//THIS is a list display
add_shortcode('single_song_display', 'single_song_display');
function single_song_display( $songID ){

    // get and organize key song information
    $songInfo = do_shortcode('[pods name="song" id="'.$songID.'"]{@post_title}*{@audio_url}*{@permalink}[/pods]'); // get song info, delineate with semicolon
    $songInfoArray = explode("*", $songInfo); // create array by exploding the songlist by the comma delineation, array_pop to remove final blank array value
    
    // find artist name via pods magic tags 
    $artistName = do_shortcode('[pods name="song" id="'.$songID.'"]{@artist.post_title}[/pods]');
    $artistPermalink = do_shortcode('[pods name="song" id="'.$songID.'"]{@artist.permalink}[/pods]');
    
    //find the album name via pods magic tags
    $albumName = do_shortcode('[pods name="song" id="'.$songID.'"]{@album.post_title}[/pods]');
    $albumPermalink = do_shortcode('[pods name="song" id="'.$songID.'"]{@album.permalink}[/pods]');
    
    // find cover art URL name via pods magic tags
    $coverArtURL = do_shortcode('[pods name="song" id="'.$songID.'"]{@album.post_thumbnail_url}[/pods]');
    
    // Establishing key variables from earlier magic tag generated array
//    urlencode(
//    print_r($songInfoArray);
    $songName = addslashes($songInfoArray[0]);
    $songPlayURL = htmlentities($songInfoArray[1]);
    $songPermalink = addslashes($songInfoArray[2]);

    // getting individual taxonomy values with "get_the_term_list()" wordpress function
    $songGenres = get_the_term_list($songID,"genre","","*");
    $songGenreArray = explode("*", $songGenres);
    $songMoods = get_the_term_list($songID,"mood","","*");
    $songMoodArray = explode("*", $songMoods);

    ?>
    
    
    <!--HTML TEMPLATE-->
    <!-- Song display containers-->
<!--    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">-->

<style>
        /* table{background-color: white; margin: 0; padding: 0;}
        tr {}
        tr:hover{background-color: #F8F8F8;}
        td {color: #162B47;} */
        .label a{
            color: white;
        }
        .track_information_title{
            color:white !important;
        }
        .track_information_artist{
            color:white !important;
        }
    </style>
    
<div >
    <table >
        <tr>
            <td style="width: 80px; height: 80px; padding: 0;">
                <button type="button"
                        class="song-play" 
                        data-audiosrc="<?php echo $songPlayURL; ?>"
                        data-songname="<?php echo $songName; ?>"
                        data-artistname="<?php echo $artistName; ?>"
                        data-albumname="<?php echo $albumName; ?>"
                        data-artsrc="<?php echo $coverArtURL; ?>"
                        style="width: 80px; height: 80px; background-image: url(<?php echo $coverArtURL; ?>); background-repeat: no-repeat; background-size: contain; border-color: #e0e0e0;" title="Play">
                    <i class="fas fa-play fa-lg" style="color: #FC0202;"></i>
                </button>
            </td>
            <td style=" width: auto; vertical-align: middle; word-wrap: normal; line-height: 1.2em; padding: 0% 1%;">
                
                <div class="track_information_title"  style="font-weight: bold;">
                    <a href="<?php echo $songPermalink; ?>">
                        <?php echo $songName; ?>
                            </a>
                                </div>
                <div class="track_information_artist">
                    <a href="<?php echo $artistPermalink; ?>">
                        <?php echo $artistName; ?>
                            </a>
                                </div>
                <div>
                    <?php 
                    if(!empty($songGenreArray)){ 
                        foreach ($songGenreArray as $key => $value) { ?>
                    <span class="label label-info"><?php echo $songGenreArray[$key]; ?></span>
                    <?php } }
                    if(!empty($songMoodArray)){ 
                        foreach ($songMoodArray as $key => $value) { ?>
                            <span class="label label-warning"><?php echo $songMoodArray[$key]; ?></span>
                    <?php } } ?>    
                </div>
            </td>
            <td style="text-align: center; vertical-align: middle; width: 15%; padding-right: 5px;">
                <a  href="<?php echo $songPermalink; ?>"
                    class="elementor-button-link elementor-button elementor-size-sm" 
                    role="button" 
                    title="License & Download"
                    style="background-color: #E7565A; border-radius: 15% 15% 15% 15%; padding: 10% 10% 10% 10%; icon-spacing: 0%;"
                    onclick="">
                    <span class="elementor-button-content-wrapper">     
                        <span class="elementor-button-icon elementor-align-icon-center">         
                            <i aria-hidden="true" class="fas fa-download">    
                            </i>			     
                        </span>       
                    </span> 
                </a>
                <button  href="<?php echo get_site_url()."/wp-json/FML/v1/playlists/html?_wpnonce=".wp_create_nonce( "wp_rest" )."&songID=$songID&userID=".get_current_user_id(); ?>"
                    class="elementor-button-link elementor-button elementor-size-sm add-to-playlist add-to-playlist-button oceanwp-lightbox" 
                    role="button" 
                    title="Add to Playlist"
                    data-userid="<?php echo is_user_logged_in() ? get_current_user_id() : null; ?>"
                    style="background-color: #E7565A; border-radius: 15% 15% 15% 15%; padding: 10% 10% 10% 10%; icon-spacing: 0%;"
                    onclick="">
                    <span class="elementor-button-content-wrapper">     
                        <span class="elementor-button-icon elementor-align-icon-center">         
                            <i aria-hidden="true" class="fas fa-plus">    
                            </i>			     
                        </span>       
                    </span> 
                </button>
                
            </td>
        </tr>
    </table>
</div>
    <!--END HTML TEMPLAET-->
    
  
    <?php
        
} 





//              //
//  LICENSING   //
//              //


add_shortcode('agree_download_song_button', 'agree_download_song_button');
function agree_download_song_button(){

    if(empty($_GET['songID'])){
         $songID = get_the_ID();
    }else{
        $songID = $_GET['songID'];
    }
    
    //Set Your Nonce
    $ajax_nonce = wp_create_nonce( "wp_rest" );
   
    $songPlayURL = do_shortcode('[pods name="song" where="id='.$songID.'"]{@audio_url}[/pods]');
    
    //get user info
    $userID = get_current_user_id();
    $last_name = get_user_meta( $userID, 'last_name', true );
    $first_name = get_user_meta( $userID, 'first_name', true );
    $fullname = "";
    
    if(!empty($last_name) && !empty($first_name)){
        $fullname = $first_name." ".$last_name;
    }
    
?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.3/jquery.validate.min.js" integrity="sha512-37T7leoNS06R80c8Ulq7cdCDU5MNQBwlYoy1TX/WUsLFC2eYNqtKlV0QjH7r8JpG/S0GUMZwebnVFLPd6SU5yg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    
    <style>
        .error {
          color: red;
       }
       
       #projectname{
           width:50%;
       }
       #licensor{
           width:50%;
       }
       #length{
           width:50%
       }
        
    </style>
    
    
    <!--//LICENSE GENERATOR-->
    <script>
     jQuery(document).ready(function(){

        jQuery('#license-generator').validate({
            rules: {
                licensor: {
                   required: true
                },
                projectname: {
                   required: true
                },
                iagree: {
                    required: true
                }
             },
            submitHandler: function(form) {
//                e.preventDefault();
                               
                var licensorval = jQuery('#licensor').val();
                var lengthval = jQuery('#length').val();
                var projectname = jQuery('#projectname').val();
                
                var dataToSend = { songID: "<?php echo $songID; ?>", licensor: licensorval, projectname: projectname, lengthval: lengthval, _wpnonce : '<?php echo $ajax_nonce; ?>'};

                jQuery.ajax({
                    url: "/wp-json/FML/v1/PDF_license_generator/", 
                    type: "POST",             
                    data: dataToSend,
                    dataType: "json",
                    cache: false,                 
                    success: function(data) {
                            
                    }
                }).done(function (response) {
//                    console.log(response);
                    if (response.success) {               
                        jQuery(".gif-loader").hide();
                        jQuery("#api_response").append("<div><p>Your license has been generated. </p><p><button><a href ='"+response.url+"'><i class='fa fa-solid fa-download'></i> Download </a></button><button><a href ='/account/my-licenses'><i class='fa fa-solid fa-files'></i> View License History </a></button></p></div>");
                    } else {
                        alert('fail: '+response);
                    }
                }).fail(function() {
                    alert('request failed'); 
                });;
                return false;
            }
            // other options
        });
        
        

    });
    </script>
    
    
    <div class="container-fluid">
         <div class="row">
            <div style="text-align: center; background: white; padding-right: 5px; margin: auto;">
                
                <form id="license-generator">
                    <div class="form-group">
                        <label for="licensor">Licensor</label>
                        <input type="text" id="licensor" name="licensor" placeholder="Enter your name.." value="<?php echo $fullname; ?>">
                    </div>
                    <div class="form-group">
                        <label for="projectname">Project Name</label>
                        <input type="text" id="projectname" name="projectname" placeholder="Enter the project name using this song..">
                    </div>
<!--                    <div class="form-group">
                        <label for="length">Length of Term</label>
                        <select id="length" name="length">
                          <option value="1">1 year</option>
                          <option value="2">2 years</option>
                          <option value="5">5 years</option>
                        </select>
                    </div>-->
                
                <div class="form-group form-check">
                    <input type="checkbox" class="form-check-input" id="iagree" name="iagree">
                    <label class="form-check-label" for="iagree">I agree to <a href="https://creativecommons.org/licenses/by/4.0/">CCBY 4.0 license</a> and understand to give artist written attribution on all works.</label>
                </div>


                
                <button type="submit" 
                    href="" 
                    class="elementor-button-link elementor-button elementor-size-lg"
                    style="background-color: #0275d8;"
                    download="">
                    License
                </button>  
                <div id="api_response">
                    
                </div>
                </form>
 
            </div>
        </div>
<!--        <tr>
            <td style="text-align: center; vertical-align: middle; width: 20%; padding-right: 5px; border-bottom: none;">
                <a  class="button" 
                    href="<?php //echo $songPlayURL; ?>" 
                    style="background-color: #0275d8;"
                    download="">
                    Download
                </a>  
            </td>
        </tr>-->
    </div>
    <div class="overlay"></div>
    
<?php
    
}

add_shortcode('attribution_example', 'attribution_example');
function attribution_example(){

    if(empty($_GET['songID'])){
         $songID = get_the_ID();
    }else{
        $songID = $_GET['songID'];
    }
?>
      
    <div style="font-weight: bold; text-align: center; font-size:x-large;">
        Attribution Example:
    </div>
<!--    <div style="text-align: center; font-size:large;">
        Music by
        <?php echo do_shortcode('[pods name="song" id="'.$songID.'"]{@artist.post_title}[/pods]'); ?>:
        <?php echo do_shortcode('[pods name="song" id="'.$songID.'"]{@artist.permalink}[/pods]'); ?>
    </div>-->
    
    <div class="prismjs-default copy-to-clipboard ">
            <div class="code-toolbar" >
                <pre data-line="" style="text-align: center; font-size:large;" class="highlight-height line-numbers language-markup">
                    <code readonly="true" class="language-markup">
                        Music by <?php echo do_shortcode('[pods name="song" id="'.$songID.'"]{@artist.post_title}[/pods]'); ?>: <?php echo do_shortcode('[pods name="song" id="'.$songID.'"]{@artist.permalink}[/pods]'); ?>
                    </code>
                </pre>
<!--                <div class="toolbar">
                    <div class="toolbar-item">
                        <button type="button">Copy</button>
                    </div>
                </div>-->
            </div>
    </div>
    
    
<?php
    
}



//
//
//ARTIST TEMPLATE PAGE
//
//
//
//SHOW ALL SOCIAL MEDIAS
add_shortcode('social_media_conditional_display', 'social_media_conditional_display');
function social_media_conditional_display($artistID) {
    
    // grabbing artist social medias via artist ID references magic tags
    $appleURL = do_shortcode('[pods name="artist" id="' . $artistID . '"]{@apple}[/pods]');
    $bandcampURL = do_shortcode('[pods name="artist" id="' . $artistID . '"]{@bandcamp}[/pods]');
    $facebookURL = do_shortcode('[pods name="artist" id="' . $artistID . '"]{@facebook}[/pods]');
    $instagramURL = do_shortcode('[pods name="artist" id="' . $artistID . '"]{@instagram}[/pods]');
    $soundcloudURL = do_shortcode('[pods name="artist" id="' . $artistID . '"]{@soundcloud}[/pods]');
    $spotifyURL = do_shortcode('[pods name="artist" id="' . $artistID . '"]{@spotify}[/pods]');
    $twitchURL = do_shortcode('[pods name="artist" id="' . $artistID . '"]{@twitch}[/pods]');
    $xURL = do_shortcode('[pods name="artist" id="' . $artistID . '"]{@twitter}[/pods]');  // Assuming your Pods field is still called @twitter
    $websiteURL = do_shortcode('[pods name="artist" id="' . $artistID . '"]{@website}[/pods]');
    $youtubeURL = do_shortcode('[pods name="artist" id="' . $artistID . '"]{@youtube}[/pods]');

    $html = '';

    if (!empty($websiteURL)) {
        $sanitizedWebsiteURL = sanitize_url($websiteURL);
        $html .= '<a class="elementor-icon elementor-social-icon" title="Artist Website" href="' . $sanitizedWebsiteURL . '" target="_blank" style="border-radius: 10%;"><span class="elementor-screen-only">Website</span><i class="fas fa-globe"></i></a>';
    }

    if (!empty($appleURL)) {
        $sanitizedAppleURL = sanitize_url($appleURL);
        $html .= '<a class="elementor-icon elementor-social-icon elementor-social-icon-apple elementor-repeater-item-83de896" href="' . $sanitizedAppleURL . '" title="Artist Apple Music Page" target="_blank" style="border-radius: 10%;"><span class="elementor-screen-only">Apple</span><i class="fab fa-apple"></i></a>';
    }

    if (!empty($bandcampURL)) {
        $sanitizedBandcampURL = sanitize_url($bandcampURL);
        $html .= '<a class="elementor-icon elementor-social-icon elementor-repeater-item-1e49893" href="' . $sanitizedBandcampURL . '" title="Artist Bandcamp Page" target="_blank" style="border-radius: 10%;"><span class="elementor-screen-only">Bandcamp</span><i class="fab fa-bandcamp"></i></a>';
    }

    if (!empty($facebookURL)) {
        $sanitizedFacebookURL = sanitize_url($facebookURL);
        $html .= '<a class="elementor-icon elementor-social-icon elementor-social-icon-facebook" href="' . $sanitizedFacebookURL . '" title="Artist Facebook Page" target="_blank" style="border-radius: 10%;"><span class="elementor-screen-only">Facebook</span><i class="fab fa-facebook"></i></a>';
    }

    if (!empty($instagramURL)) {
        $sanitizedInstagramURL = sanitize_url($instagramURL);
        $html .= '<a class="elementor-icon elementor-social-icon" href="' . $sanitizedInstagramURL . '" title="Artist Instagram Page" target="_blank"><span class="elementor-screen-only">Instagram</span><i class="fab fa-instagram"></i></a>';
    }

    if (!empty($soundcloudURL)) {
        $sanitizedSoundcloudURL = sanitize_url($soundcloudURL);
        $html .= '<a class="elementor-icon elementor-social-icon elementor-social-icon-soundcloud" href="' . $sanitizedSoundcloudURL . '" title="Artist Soundcloud Page" target="_blank" style="border-radius: 10%;"><span class="elementor-screen-only">Soundcloud</span><i class="fab fa-soundcloud"></i></a>';
    }

    if (!empty($spotifyURL)) {
        $sanitizedSpotifyURL = sanitize_url($spotifyURL);
        $html .= '<a class="elementor-icon elementor-social-icon elementor-social-icon-spotify" href="' . $sanitizedSpotifyURL . '" title="Artist Spotify Page" target="_blank" style="border-radius: 10%;"><span class="elementor-screen-only">Spotify</span><i class="fab fa-spotify"></i></a>';
    }

    if (!empty($twitchURL)) {
        $sanitizedTwitchURL = sanitize_url($twitchURL);
        $html .= '<a class="elementor-icon elementor-social-icon elementor-social-icon-twitch" href="' . $sanitizedTwitchURL . '" title="Artist Twitch Page" target="_blank" style="border-radius: 10%;"><span class="elementor-screen-only">Twitch</span><i class="fab fa-twitch"></i></a>';
    }

    if (!empty($xURL)) {
        $sanitizedXURL = sanitize_url($xURL);
        $html .= '<a class="elementor-icon elementor-social-icon elementor-social-icon-x" href="' . $sanitizedXURL . '" title="Artist X Page" target="_blank" style="border-radius: 10%;"><span class="elementor-screen-only">X</span><i class="fab fa-x-twitter"></i></a>';
    }

    if (!empty($youtubeURL)) {
        $sanitizedYoutubeURL = sanitize_url($youtubeURL);
        $html .= '<a class="elementor-icon elementor-social-icon elementor-social-icon-youtube" href="' . $sanitizedYoutubeURL . '" title="Artist Youtube Page" target="_blank" style="border-radius: 10%;"><span class="elementor-screen-only">Youtube</span><i class="fab fa-youtube"></i></a>';
    }

    return $html;
}





add_shortcode('song_list_by_taxonomy', 'song_list_by_taxonomy');
function song_list_by_taxonomy(){

    $tax = do_shortcode('[pods]{@taxonomy}[/pods]');
    $taxName = do_shortcode('[pods]{@name}[/pods]');
    
    $relatedSongList = do_shortcode('[pods name="song" where="'.$tax.'.name =\''.$taxName.'\'" orderby="rand()"]<div>{@ID,single_song_display}</div>[/pods]');
    return $relatedSongList;

}

add_shortcode('post_view_count', 'post_view_count');
function post_view_count(){

    gt_set_post_view();
    echo gt_get_post_view();

}


add_shortcode('home_page_play_button', 'home_page_play_button');
function home_page_play_button( $songID ){

    $songInfo = do_shortcode('[pods name="song" id="'.$songID.'"]{@post_title}*{@audio_url}*{@permalink}[/pods]'); // get song info, delineate with semicolon
    $songInfoArray = explode("*", $songInfo); // create array by exploding the songlist by the comma delineation, array_pop to remove final blank array value
    
    // find artist name via pods magic tags 
    $artistName = do_shortcode('[pods name="song" id="'.$songID.'"]{@artist.post_title}[/pods]');
    $artistPermalink = do_shortcode('[pods name="song" id="'.$songID.'"]{@artist.permalink}[/pods]');
    
    // find album name via pods magic tags
    $albumName = do_shortcode('[pods name="song" id="'.$songID.'"]{@album.post_title}[/pods]');
    $albumPermalink = do_shortcode('[pods name="song" id="'.$songID.'"]{@album.permalink}[/pods]');

    
    // find cover art URL name via pods magic tags
    $coverArtURL = do_shortcode('[pods name="song" id="'.$songID.'"]{@album.post_thumbnail_url}[/pods]');
    $coverArtLarge = do_shortcode('[pods name="song" id="'.$songID.'"]{@album.post_thumbnail_url.large}[/pods]');

    
    // Establishing key variables from earlier magic tag generated array

    $songName = $songInfoArray[0];
    $songPlayURL = $songInfoArray[1];
    $songPermalink = $songInfoArray[2];

    // getting individual taxonomy values with "get_the_term_list()" wordpress function
    $songGenres = get_the_term_list($songID,"genre","","*");
    $songGenreArray = explode("*", $songGenres);
    $songMoods = get_the_term_list($songID,"mood","","*");
    $songMoodArray = explode("*", $songMoods);
    
    
    
    ?>
    
    <a href="<?php echo $albumPermalink; ?>"><img src="<?php echo $coverArtLarge; ?>" alt="" width="100%"></a>
    
    
    <div style="text-align: center;">
        <h3>
            <a href="<?php echo $albumPermalink; ?>" style="color: white; cursor: pointer;">
                "<?php echo $albumName; ?>"
            </a>
        </h3>
    </div>
    
    <div style="text-align: center;">
        <h4>
            <a href="<?php echo $artistPermalink; ?>" style="color: white; cursor: pointer;">
                <?php echo $artistName; ?>
            </a>
        </h4>
    </div>
    
    <div>
        <button type="btn"
                class="song-play" 
                data-audiosrc="<?php echo $songPlayURL; ?>"
                data-songname="<?php echo $songName; ?>"
                data-artistname="<?php echo $artistName; ?>"
                data-artsrc="<?php echo $coverArtURL; ?>"
                title="Play"
                style="font-weight: bold; width: 100%; background-color: #61CE70; border: none; padding: 2%; border-radius: 10px;">
            <i class="fa fa-play-circle-o"></i>
            PLAY
        </button>
    </div>
    
    <?php
    
}
add_shortcode('pods_count', 'pods_count');
function pods_count( $atts ){
    $name = $atts["name"];
    if(!empty($name)){
//        $pod = $atts["name"];
//        echo $atts["name"];
//        print_r($name);
        
        print_r(pods("$name")->total_found());
    }else{
        echo "empty";
    }
    
    
}