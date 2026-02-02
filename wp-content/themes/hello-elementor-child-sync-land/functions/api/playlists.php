<?php



//get user playlists ROUTE
add_action( 'rest_api_init', function () {
  register_rest_route( 'FML/v1', '/playlists/', array(
    'methods' => 'GET',
    'callback' => 'get_playlists',
    'permission_callback' => '__return_true'
  ) );
} );

add_action( 'rest_api_init', function () {
  register_rest_route( 'FML/v1', '/playlists/html', array(
    'methods' => 'GET',
    'callback' => 'getPlaylistsHTML',
    'permission_callback' => function (WP_REST_Request $request) {
        $userID = isset($request['userID']) ? intval($request['userID']) : 0;
        return is_user_logged_in() && get_current_user_id() === $userID;
    }
  ) );
} );

//add playlist
add_action( 'rest_api_init', function () {
  register_rest_route( 'FML/v1', '/playlists/add', array(
    'methods' => 'POST',
    'callback' => 'add_playlist',
    'permission_callback' => '__return_true'
  ) );
} );

//add song to playlist
add_action( 'rest_api_init', function () {
  register_rest_route( 'FML/v1', '/playlists/addsong', array(
    'methods' => 'POST',
    'callback' => 'add_song_to_playlist',
    'permission_callback' => '__return_true'
  ) );
} );


//delete playlist
add_action( 'rest_api_init', function () {
  register_rest_route( 'FML/v1', '/playlists/delete', array(
    'methods' => 'POST',
    'callback' => 'delete_playlist',
    'permission_callback' => '__return_true'
  ) );
} );

//edit playlist
add_action( 'rest_api_init', function () {
  register_rest_route( 'FML/v1', '/playlists/edit', array(
    'methods' => 'POST',
    'callback' => 'edit_playlist',
    'permission_callback' => '__return_true'
  ) );
} );

function add_song_to_playlist(){
    header("Content-Type: application/json; charset=utf-8");
    
    $nonce = check_ajax_referer( 'wp_rest', '_wpnonce' );
    if($nonce){
        //do things
        require_once $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';
        
        if(isset($_POST['songID']) && isset($_POST['playlistID'])){
            //get variables
            $songID = $_POST['songID'];
            $playlistID = $_POST['playlistID'];
            $playlists = explode(",",$playlistID);
            //build playlist 
            foreach($playlists as $listID){
               $playlistPod = pods("playlist",$listID);
               $songList = $playlistPod->field("songs");
               $idList = array();
               foreach($songList as $song){
                   $idList[] = $song["ID"];
               }
               $idList[] = $songID;
                $data = array(
                    'songs' => $idList
                );
                // Save the data as set above
               $playlistPod->save($data);
               
//               print_r($idList);
            }
            
            $success=true;
        }else{
            $success = false;
        }
        
        
    }else{
        $success = false;
        $error="Nonce issue.";
    }


    if ($success) {
        $output = array("success" => true, "message" => "Success!" );
    } else {
        $output = array("success" => false, "error" => "Failure! ".$error);
    }

    echo json_encode($output);
}

function getPlaylistsHTML(WP_REST_Request $request) {
    // Wider container (80% of screen width)
    $string = "<div style='background: #fff; color: #333; padding: 15px; font-family: Arial, sans-serif; max-width: 80%; margin: 0 auto; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>";

    $userID = isset($request['userID']) ? intval($request['userID']) : 0;
    $songID = isset($request['songID']) ? intval($request['songID']) : 0;

    if ($userID > 0 && $songID > 0) {
        $params = array(
            'where' => "t.post_author = '" . $userID . "' AND t.post_status IN ('publish', 'private')",
            "orderby" => "t.post_date DESC"
        );
        $playlists = pods('playlist', $params);

        $songName = do_shortcode('[pods name="song" id="' . $songID . '"]{@post_title}[/pods]');
        $artistName = do_shortcode('[pods name="song" id="' . $songID . '"]{@artist.post_title}[/pods]');

        $string .= "<h2>Add '$artistName - $songName' to Playlist</h2>";
        $string .= "<div class='list-group' style='margin-bottom: 15px;'>";
        $string .= "<form id='playlistForm' style='display: flex; flex-direction: column; gap: 5px;'>";

        if (0 < $playlists->total()) {
            while ($playlists->fetch()) {
                $playlistObj = $playlists->export();
                $playlistID = $playlistObj["id"];
                $status = $playlistObj["post_status"] === 'private' ? 'Private' : 'Public';
                $string .= "<label class='playlist-list-item' style='display: flex; align-items: center; padding: 8px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; font-size: 0.9em;'>"
                        . "<input name=\"form-check-input playlist-item-input\" type=\"checkbox\" value=\"{$playlistID}\" style='margin-right: 8px;'> "
                        . "<span>{$playlistObj["post_title"]} ({$status}, " . sizeof($playlistObj["songs"]) . " songs)</span>"
                        . "</label>";
            }
            $string .= "</form>";
            $string .= "<button data-nonce=\"" . wp_create_nonce("wp_rest") . "\" data-songID=\"$songID\" "
                    . "type=\"submit\" style=\"margin-top:10px;\" class=\"btn btn-primary save-addsong-to-playlist\">Save</button>";
            $string .= "</div>";
        } else {
            $string .= "<p style='margin: 0; color: #666; font-size: 0.9em;'>No playlists found.</p>";
        }
    } else {
        $string .= "<span style='display: block; text-align: center; color: #666; font-size: 0.9em;'>You must be logged in to use this feature.</span>";
    }

    $string .= "</div>";
    return $string;
}

//
//function to get playlists
//
function get_playlists(){
    header("Content-Type: application/json; charset=utf-8");

//    print_r($_POST);
//    print_r($_REQUEST);
//    $output = "YERS";
    
//    $nonce = check_ajax_referer( 'wp_rest', '_wpnonce' );
//    if($nonce){
    
    require_once $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';

    //TODO -- ADD NONCE OR SOME OTHER AUTHENTICATION
//    if(is_user_logged_in()){
        //DEFINE VARIABLES
//        $user_id = apply_filters( 'determine_current_user', false );
//        wp_set_current_user( $user_id );
//        $userID = get_current_user_id();
        if(isset($_GET['userID']) && $_GET['userID']>0){
            $userID = $_GET['userID']; 
            $params = array(
                'where' => "t.post_author = '" . $userID . "' AND t.post_status = 'Publish'",
                "orderby" => "t.post_date DESC"
            );
            $playlists = pods('playlist', $params);
            if (0 < $playlists->total()) {

                $playlistObj = array();
                while ($playlists->fetch()) {
                    $playlistObj[] =  $playlists->export();
                }

                $success = true;
            }else{
//            $success = false;
//            $error = "no playlists found";
            }
    //        print_r($playlistObj);


    //        echo "TEST $userID at $currentDateTime";

    //    }else{
    //        $success = false;
    //        $error="Nonce issue.";
    //    }
    //    }else{
    //        $error = "User Authentication Error.";
    //        $success = false;
    //    }
        
        }else{
            $success = false;
            $error = "invalid inputs";
        }
    
    if ($success) {
	$output = array("success" => true, "message" => "Success!", "playists" =>$playlistObj);
    } else {
        $output = array("success" => false, "error" => "Failure! ".$error);
    }
    
    echo json_encode($output);
//    wp_die(); // required. to end AJAX request.

} 
//
//function to add playlist
//
function add_playlist(){
//echo "YERS";
//    header("Content-type: application/pdf"); 
//    header("Content-Disposition: inline; filename=cc-license.pdf");
    header("Content-Type: application/json; charset=utf-8");
//    echo "test";
    
//    $_POST = json_decode(file_get_contents("php://input"), true);
//    print_r($_POST);
//    print_r($_REQUEST);
    
    $nonce = check_ajax_referer( 'wp_rest', '_wpnonce' );
    
    
//    $output = "YERS";
    //authenticate (check if user is logged in)
    //echo "nonce: ".$nonce;
    
    if($nonce){
    
        require_once $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';

        if(isset($_POST["playlist_name"]) ){
            $playlistname = $_POST["playlist_name"];
            
        }
        //get the checkbox input.
        if(isset($_POST["isprivate"])){
            $isprivate = $_POST["isprivate"];
        }
        
        //DEFINE VARIABLES
        $user_id = apply_filters( 'determine_current_user', false );
        wp_set_current_user( $user_id );
        $userID = get_current_user_id();
        
       
        //current datetime
        $currentDateTime = gmdate("Y-m-d\TH:i:s\Z");
        
        // Get the book pod object
        $pod = pods( 'playlist' );
        // To add a new item, let's set the data first
        $data = array(
            'name' => $playlistname,
            'author' => $userID // User ID for relationship field
        );

        // Add the new item now and get the new ID
        $new_playlist_id = $pod->add( $data );
        
        //IF SUCCESSFULL, CHANGE ALBUM TO PUBLISHED
        //$albumPod = pods("album", );
        
        if($isprivate == "on"){
            $status = "private";
        }else{
            $status = "publish";
        }
        
        
        wp_update_post(array(
            'ID' => $new_playlist_id,
            'post_status' => $status
        ));
        
//        $output = "TEST $userID at $currentDateTime";
        
        $success = true;
        
        
    }else{
        $success = false;
        $error="Nonce issue.";
    }
    
    
    if ($success) {
	$output = array("success" => true, "message" => "Success!", "playlistID" => $new_playlist_id );
    } else {
        $output = array("success" => false, "error" => "Failure! ".$error);
    }
    
    echo json_encode($output);
//    wp_die(); // required. to end AJAX request.

} 
//
//function to delete playlist
//
function delete_playlist(){

    header("Content-Type: application/json; charset=utf-8");
//    echo "test";
    
//    $_POST = json_decode(file_get_contents("php://input"), true);
//    print_r($_POST);
//    print_r($_REQUEST);
    
    $nonce = check_ajax_referer( 'wp_rest', '_wpnonce' );
    
    if($nonce){
    
        require_once $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';

        if(isset($_POST["playlistID"]) && !empty($_POST["playlistID"])){
//           print_r($_POST);
           // $playlist = pods("playlist", $_POST["playlistID"]);
            $trashPost = wp_trash_post( $_POST["playlistID"] );
            
            if(is_null($trashPost) || $trashPost === false){
                $success = false;
            }else{
                $success = true;
            }

        }else{
            $success = false;
        }
        
        
    }else{
        $success = false;
        $error="Nonce issue.";
    }
    
    
    if ($success) {
	$output = array("success" => true, "message" => "Success!");
    } else {
        $output = array("success" => false, "error" => "Failure! ".$error);
    }
    
    echo json_encode($output);
//    wp_die(); // required. to end AJAX request.

}
//
//function to edit playlist
//
function edit_playlist(){
//echo "YERS";
//    header("Content-type: application/pdf"); 
//    header("Content-Disposition: inline; filename=cc-license.pdf");
    header("Content-Type: application/json; charset=utf-8");
//    echo "test";
    
//    $_POST = json_decode(file_get_contents("php://input"), true);
//    print_r($_POST);
//    print_r($_REQUEST);
    
    $nonce = check_ajax_referer( 'wp_rest', '_wpnonce' );
    
    
//    $output = "YERS";
    //authenticate (check if user is logged in)
    //echo "nonce: ".$nonce;
    
    if($nonce){
    
        require_once $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';

        if(isset($_POST["post_title"]) ){
            $playlistname = $_POST["post_title"];
            
        }
        if(isset($_POST["isprivate"])){
            $isprivate = $_POST["isprivate"];
            if($isprivate == "on"){
                $status = "private";
            }else{
                $status = "publish";
            }
        }else{
            $status = "publish";
        }
        
        if(isset($_POST["songs"])){
            $songs = $_POST["songs"];
        }
        
        if(isset($_POST["playlistID"])){
            $playlistID = $_POST["playlistID"];
        }
        
        //DEFINE VARIABLES
        $user_id = apply_filters( 'determine_current_user', false );
        wp_set_current_user( $user_id );
        $userID = get_current_user_id();

        
        // Get the book pod object
        $pod = pods( 'playlist', $playlistID );
        // To add a new item, let's set the data first
        $data = array(
            'post_title' => $playlistname,
            'post_status' => $status,
            'songs' => $songs
        );

        // Add the new item now and get the new ID
        $new_playlist_id = $pod->save( $data );

        
        $success = true;
        
        
    }else{
        $success = false;
        $error="Nonce issue.";
    }
    
    
    if ($success) {
	$output = array("success" => true, "message" => "Success!", "playlistID" => $new_playlist_id );
    } else {
        $output = array("success" => false, "error" => "Failure! ".$error);
    }
    
    echo json_encode($output);
//    wp_die(); // required. to end AJAX request.

} 