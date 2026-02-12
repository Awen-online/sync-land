<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */



//
// GET SONGS (search)
//
add_action( 'rest_api_init', function () {
  register_rest_route( 'FML/v1', '/song-search', array(
    'methods' => 'GET',
    'callback' => 'get_songs',
  ) );
} );

add_action( 'rest_api_init', function () {
  register_rest_route( 'FML/v1', '/song-upload', array(
    'methods' => 'POST',
    'callback' => 'song_upload',
  ) );
} );


//SONG UPLOAD https://freemusic.land/FML/v1/song-upload
function song_upload( $data ) {
  
$remoteOrigin = "http://www.remote-domain.com"; //change to the origin of your webpage



/* FOR AJAX CORS REQUESTS */

//if ($_SERVER["HTTP_ORIGIN"]===$remoteOrigin) {
//
//	header("Access-Control-Allow-Origin: " . $_SERVER["HTTP_ORIGIN"]);
//
//	/* Uncomment to allow cookies across domains */
//	//header("Access-Control-Allow-Credentials: true");
//
//	/* Uncomment to improve performance after testing */
//	//header("Access-Control-Max-Age: 86400"); // cache for 1 day
//
//}

if ($_SERVER["REQUEST_METHOD"]==="OPTIONS") {

	if (isset($_SERVER["HTTP_ACCESS_CONTROL_REQUEST_METHOD"]))
		header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

	if (isset($_SERVER["HTTP_ACCESS_CONTROL_REQUEST_HEADERS"]))
		header("Access-Control-Allow-Headers: " . $_SERVER["HTTP_ACCESS_CONTROL_REQUEST_HEADERS"]);

	exit(0);

}


/* END AJAX CORS CONFIGURATION */

header("Content-Type: application/json; charset=utf-8");

/* AMAZON WEB SERVICES CONFIGURATION */
require_once get_stylesheet_directory()."/php/aws/aws-autoloader.php";

// Establish connection with DreamObjects with an S3 client.
// Credentials are loaded from wp-config.php constants
$client = new Aws\S3\S3Client([
    'version'     => '2006-03-01',
    'region'      => FML_AWS_REGION,
    'endpoint'    => FML_AWS_HOST,
    'credentials' => [
        'key'      => FML_AWS_KEY,
        'secret'   => FML_AWS_SECRET_KEY,
    ]
]);

// LIST BUCKETS
//$buckets = $client->listBuckets();
//try {
//    foreach ($buckets['Buckets'] as $bucket){
//        echo "{$bucket['Name']}\t{$bucket['CreationDate']}\n";
//    }
//} catch (S3Exception $e) {
//    echo $e->getMessage();
//    echo "\n";
//}

//CREATE AN OBJECT
$bucket = 'fml-songs';

foreach ($_FILES as $keyname=>$value) {
    if(stristr($keyname,'file')!==FALSE){
        $file_Path = $_FILES[$keyname]["tmp_name"];
        $key = $_FILES[$keyname]["name"];
    }
}

if(!empty($key)){
    try{
        $result = $client->putObject([
            'Bucket'     => $bucket,
            'Key'        => $key,
            'SourceFile' => $file_Path,
            'ACL'        => 'public-read',
        ]);

        $url = $result['ObjectURL'];
        $success = true;
    } catch (S3Exception $e) {
        $error = " ".$e->getMessage();
        $success = false;
    }
}else{
    $success=false;
}

/*
 * All of your application logic with $_FILES["file"] goes here.
 * It is important that nothing is outputted yet.
 */
//print_r($_FILES["file"]);



// $output will be converted into JSON

if ($success) {
	$output = array("success" => true, "message" => "Success!", "url"=>$url);
} else {
	$output = array("success" => false, "error" => "Failure!".$error);
}

echo json_encode($output);

//TODO : OLDER BROWSERS CODE

}


function get_songs(){
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
        if(isset($_GET['q'])){
            global $wpdb;
            $q = sanitize_text_field($_GET['q']);
            // Use wpdb->esc_like to prevent SQL injection in LIKE queries
            $escaped_q = $wpdb->esc_like($q);
            // Here's how to use find()
            $params = array(
                'limit' => 5,
                'where' => $wpdb->prepare("t.post_title LIKE %s", '%' . $escaped_q . '%')
            );
            $songs = pods("song", $params);
            $songObj = array($songs->export());

           
            $success = true;
        }else{
            $success = false;
            $error = "Input issue";
        }
    }else{
        $success = false;
        $error="Nonce issue.";
    }
    
    
    if ($success) {
	$output = array("success" => true, "message" => "Success!", "songs" => $songObj );
    } else {
        $output = array("success" => false, "error" => "Failure! ".$error);
    }
    
    echo json_encode($output);
}