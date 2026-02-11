<?php
/**
 * Plugin Name: Music Player
 * Plugin URI: https://github.com/mcculloughi
 * Description: Display the music player using a shortcode to insert into sticky footer
 * Version: 0.1
 * Text Domain: music-player
 * Author: mcculloughi
 * Author URI: https://github.com/mcculloughi
 */

 function fml_music_player_function($atts) {
     
     
     
	$Content = "<style>\r\n";
	$Content .= "h3.demoClass {\r\n";
	$Content .= "color: #26b158;\r\n";
	$Content .= "}\r\n";
	$Content .= "</style>\r\n";
	$Content .= '<h3 class="demoClass">Check it out!</h3>';
	 
        ?>

<div class="example-container">
        <div id="white-player">
        <div id="white-player-controls">
            <div class="amplitude-prev" id="previous" title="Previous"></div>
            <div class="amplitude-play-pause" id="play-pause" title="Play/Pause"></div>
            <div class="amplitude-next" id="next" title="Next"></div>
            <div class="small-controls">
                <div class="amplitude-shuffle amplitude-shuffle-off" id="shuffle" title="Shuffle"></div>
                <div class="amplitude-repeat" id="repeat" title="Repeat"></div>
            </div>
        </div>
            <img data-amplitude-song-info="cover_art_url" class="main-album-art"/>
            
          <div id="white-player-center">
            

            <div class="song-meta-data">
              <span data-amplitude-song-info="name" class="song-name"></span>
              <span class="song-meta-links">
                  <a id="player-artist-link" href="#" class="player-artist-link"></a>
                  <span class="player-meta-separator" style="display:none;"> &mdash; </span>
                  <a id="player-album-link" href="#" class="player-album-link"></a>
              </span>
            </div>

            <div class="time-progress">
              <div id="progress-container">
                <input type="range" class="amplitude-song-slider"/>
                <progress id="song-played-progress" class="amplitude-song-played-progress"></progress>
                <progress id="song-buffered-progress" class="amplitude-buffered-progress" value="0"></progress>
              </div>

              <div class="time-container">
                <span class="current-time">
                  <span class="amplitude-current-minutes"></span>:<span class="amplitude-current-seconds"></span>
                </span>
                <span class="duration">
                    <span class="amplitude-duration-minutes"></span>:<span class="amplitude-duration-seconds"></span>
                  </span>
              </div >
            </div>
            
          </div>
            <!-- License button -->
            <a id="license-button" href="#" class="player-license-btn" title="License this song" target="_blank">
                <i class="fas fa-file-contract"></i>
            </a>

            <!-- Visualizer toggle -->
            <button id="toggle-visualizer" class="visualizer-toggle" title="Toggle visualizer">
                <i class="fas fa-wave-square"></i>
            </button>

            <div id="volume-control">
                <div id="volume-toggle" class="volume-icon" title="Mute/Unmute">
                    <i class="fas fa-volume-up"></i>
                </div>
                <input type="range" class="amplitude-volume-slider"/>
            </div>
            <div class="show-playlist">
                <i class="fas fa-list" style="color: var(--player-text-muted); font-size: 20px;"></i>
            </div>
          

          <div id="white-player-playlist-container">
            <!--   QUEUE MARKUP
-->            <div class="white-player-playlist-top">
              <div>

              </div>
              <div>
                <span class="queue">Queue</span>
              </div>
              <div>
                  <span class="close-playlist" style="cursor:pointer; float:right; margin-right:16px; margin-top:20px; font-size:20px; color:var(--player-text-muted);">
                      <i class="fas fa-times"></i>
                  </span>
              </div>
             </div>
            <div class="white-player-up-next">
              Up Next
            </div>

            <div class="white-player-playlist">
            </div>

            <div class="white-player-playlist-controls">
              <img data-amplitude-song-info="cover_art_url" class="playlist-album-art"/>

              <div class="playlist-controls">
                <div class="playlist-meta-data">
                    <span data-amplitude-song-info="name" class="song-name"></span>
                    <span data-amplitude-song-info="artist" class="song-artist"></span>
                </div>

                <div class="playlist-control-wrapper">
                  <div class="amplitude-prev" id="playlist-previous"></div>
                  <div class="amplitude-play-pause" id="playlist-play-pause"></div>
                  <div class="amplitude-next" id="playlist-next"></div>
                </div>
              </div>
            </div>
          </div>
          
        </div>

      
    </div>


            
    <?php 
        //return plugins_url('', dirname(__FILE__) )."/fml-music-player/wavesurfer-player.js-master/index.html";
        
        
    //return $Content;
}
if( ! is_admin()   ){
    add_shortcode('fml_music_player', 'fml_music_player_function');
}
//
//LOAD SCRIPTS
//


function fml_music_player_load_styles() {
//	if(strpos($_SERVER['PHP_SELF'], 'wp-admin') !== false) { //loads css in admin
//            
//		$page = (isset($_GET['page'])) ? $_GET['page'] : '';
//		if(preg_match('/LBG_AUDIO7_HTML5/i', $page)) {
//			//wp_enqueue_style('lbg-audio7-html5_jquery-custom_css', plugins_url('css/custom-theme/jquery-ui-1.8.10.custom.css', __FILE__));
//			//wp_enqueue_style('lbg-audio7-html5_jquery-custom_css', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/themes/eggplant/jquery-ui.min.css');
//			//wp_enqueue_style('lbg-audio7-html5_jquery-custom_css', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/themes/overcast/jquery-ui.min.css');
//			//wp_enqueue_style('lbg-audio7-html5_jquery-custom_css', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/themes/ui-lightness/jquery-ui.min.css');
////			wp_enqueue_style('lbg-audio7-html5_jquery-custom_css', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/themes/pepper-grinder/jquery-ui.min.css');
////			//wp_enqueue_style('lbg-audio7-html5_jquery-custom_css', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/themes/excite-bike/jquery-ui.min.css');
////			wp_enqueue_style('lbg-audio7-html5_css', plugins_url('css/styles.css', __FILE__));
////			wp_enqueue_style('lbg-audio7-html5_colorpicker_css', plugins_url('css/colorpicker/colorpicker.css', __FILE__));
////			wp_enqueue_style('thickbox');
//		}
//	} else if (!is_admin()) { //loads css in front-end
//		wp_enqueue_style('fml_music_player_site_css', plugins_url('wavesurfer-player.js-master/src/css/style.css', __FILE__));
//                
//	}
        
        wp_enqueue_style('fml_music_player_site_css', plugins_url('music-player/css/music-player.css', __FILE__), '', '1.5.0');
        // wp_enqueue_style('fml_wavesurfer_player_site_css', plugins_url('wavesurfer-player.js-master/src/css/style.css', __FILE__));
        wp_enqueue_style('jquery_modal_css', 'https://cdnjs.cloudflare.com/ajax/libs/jquery-modal/0.9.1/jquery.modal.min.css');


}


function fml_music_player__load_scripts() {
	$page = (isset($_GET['page'])) ? $_GET['page'] : '';
	if (!is_admin()) { //loads scripts in front-end
//                wp_deregister_script('jquery-ui-core');
	}
        
        if (strpos($_SERVER['REQUEST_URI'], '/fundraisers/') == false) {
            // You're not on the /specific-endpoint/ endpoint

            //amplitude -- the library running teh music player
    //        wp_register_script('amplitude', "https://cdn.jsdelivr.net/npm/amplitudejs@5.3.2/dist/amplitude.js");
            wp_register_script('amplitude', plugins_url("fml-music-player/music-player/js/amplitudejs/amplitude.js"));        
            wp_enqueue_script('amplitude');

            //custom music scripts to initialize amplitude
            wp_register_script('music-player', plugins_url('music-player/js/music-player.js', __FILE__ ), array( 'jquery' ),'1.5.0',false);
            wp_enqueue_script('music-player');

            // player-visualizer.js no longer needed; visualizer uses background-particles.js in theme

            //jquery modal scripts
            wp_register_script('jquery-modal', 'https://cdnjs.cloudflare.com/ajax/libs/jquery-modal/0.9.1/jquery.modal.min.js', array( 'jquery' ));
            wp_enqueue_script('jquery-modal');
        }else{
            echo "IM IN FUNDRAISER";
        }
//        wp_register_script('music-player', plugins_url('wavesurfer-player.js-master/src/js/wavesurfer-player.js', __FILE__));
//        wp_enqueue_script('music-player');

}

if( ! is_admin() ){
    add_action('init', 'fml_music_player_load_styles');	// loads required styles
    add_action('init', 'fml_music_player__load_scripts');	// loads required scripts

    include_once "rest-api.php";
    }