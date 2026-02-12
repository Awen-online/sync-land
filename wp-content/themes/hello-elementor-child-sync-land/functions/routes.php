<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


//
//function to generate a pdf, upload it to AWS, update pods with info
//
function PDF_license_generator($songID){

//    header("Content-type: application/pdf"); 
//    header("Content-Disposition: inline; filename=cc-license.pdf");
    
    
    header("Content-Type: application/json; charset=utf-8");
    //authenticate (check if user is logged in)
    
    if(is_user_logged_in()){
    
        require_once $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';

        //DEFINE VARIABLES

        $songID = $_GET['songID'];
        $user_id = apply_filters( 'determine_current_user', false );
        wp_set_current_user( $user_id );
        $userID = get_current_user_id();
        $songName = do_shortcode('[pods name="song" id="'.$songID.'"]{@post_title}[/pods]');
        $artistName = do_shortcode('[pods name="song" id="'.$songID.'"]{@artist.post_title}[/pods]');
        $currentDate = date("d M Y");
        $currentTime = date("h:i:sa");
        $userFirstName = get_userdata( $userID )->first_name;
        $userLastName = get_userdata( $userID )->last_name;
        $userEmail = get_userdata( $userID )->user_email;

    //    $custom_logo_id = get_theme_mod( 'custom_logo' );
    //    $image = wp_get_attachment_image_src( $custom_logo_id , 'full' );
        $sitelogo = "/wp-content/uploads/2020/06/FML-Title.png";


        $mpdf = new \Mpdf\Mpdf();
        $mpdf->AddPage();
        $mpdf->WriteHTML(''
                . '<style> a {color: #277acc; text-decoration: none;}</style>'
                . '<body style="font-family: sans-serif"><div style="width: 75%; text-align: center; margin: auto;"><img src="'.$sitelogo.'" /></div>'
                . '<div style="width: 100%; text-align: center;"><h2>Statement of Licensure</h2></div>'
                . '<div>I, '
                . '<strong><em>'.$userFirstName.' '.$userLastName.'</em></strong> '
                . '('.$userEmail.') '
                . 'hereby agree to the license terms herein, as of '. $currentDate.' at '.$currentTime.' GMT, '
                . 'for the song <strong><em>'. $songName.'</em></strong> '
                . 'created by <strong><em>'. $artistName.'</em></strong>.'
                . '</div><br />'
                . '<div style="width: 100%; text-align: center;"><img alt="" src="/wp-content/uploads/2020/05/cc.svg"><img alt="" src="/wp-content/uploads/2020/04/by.svg"></div>'
                . '<div style="width: 100%; text-align: center;"><h1>Attribution 4.0 International</h1></div>'
                . '<br>'
                . '<div style="width: 100%; text-align: center;"><em>This is a human-readable summary of the license that follows:</em></div>'
                . '<br>'
                . '<div style="width: 100%; text-align: center;"><h3>You are free to:</h3></div>'
                . '<ul><li><strong>Share</strong> - Copy and redistribute the material in any medium or format</li>'
                . '<li><strong>Adapt</strong> - Remix, transform, and build upon the material for any purpose, even commercially.</li></ul>'
                . '<div style="width: 100%; text-align: center;">The licensor cannot revoke these freedoms as long as you follow the license terms.</div>'
                . '<br>'
                . '<div style="width: 100%; text-align: center;"><h3>When doing so, you must comply with these terms:</h3></div>'
                . '<ul><li><strong>Attribution</strong> - You must give <a href="https://wiki.creativecommons.org/wiki/License_Versions#Detailed_attribution_comparison_chart">appropriate credit</a>, provide a link to the license, and <a href="https://wiki.creativecommons.org/wiki/License_Versions#Modifications_and_adaptations_must_be_marked_as_such">indicate if changes were made</a>. You may do so in any reasonable manner, but not in any way that suggests the licensor endorses you or your use.</li>'
                . '<li><strong>No Additional Restrictions</strong> - You may not apply legal terms or <a href="https://wiki.creativecommons.org/wiki/License_Versions#Application_of_effective_technological_measures_by_users_of_CC-licensed_works_prohibited">technological measures</a> that legally restrict others from doing anything the license permits.</li></ul>'
                . '<br>'
                . '<div style="width: 100%; text-align: center;"><h3>Notices:</h3></div>'
                . '<ul><li>You do not have to comply with the license for works in the public domain or where your use is permitted by an applicable <a href="https://wiki.creativecommons.org/Frequently_Asked_Questions#Do_Creative_Commons_licenses_affect_exceptions_and_limitations_to_copyright.2C_such_as_fair_dealing_and_fair_use.3F">exception or limitation</a>.</li>'
                . '<li>No warranties are given. The license may not give you all of the permissions necessary for your intended use. For example, other rights such as <a href="https://wiki.creativecommons.org/Considerations_for_licensors_and_licensees">publicity, privacy, or moral rights</a> may limit how you use the material.</li></ul>'
                . ''
                . '</body>');



        $pagecount = $mpdf->SetSourceFile($_SERVER['DOCUMENT_ROOT'].'/wp-content/uploads/2020/04/Creative-Commons-—-Attribution-4.0-International-—-CC-BY-4.0.pdf');
        for ($i=1; $i<=($pagecount); $i++) {
            $mpdf->AddPage();
            $import_page = $mpdf->ImportPage($i);
            $mpdf->UseTemplate($import_page);
        }

        $file = $mpdf->Output();
        $filename = "test.pdf";


        //UPLOAD TO AWS

        /* AMAZON WEB SERVICES CONFIGURATION */
        // Credentials are loaded from wp-config.php constants
        require_once get_stylesheet_directory()."/php/aws/aws-autoloader.php";

        // Establish connection with DreamObjects with an S3 client.
        $client = new Aws\S3\S3Client([
            'version'     => '2006-03-01',
            'region'      => FML_AWS_REGION,
            'endpoint'    => FML_AWS_HOST,
            'credentials' => [
                'key'      => FML_AWS_KEY,
                'secret'   => FML_AWS_SECRET_KEY,
            ]
        ]);

        //CREATE AN OBJECT
        $bucket = 'fml-licenses';
        try{
            $result = $client->putObject([
                'Bucket'     => $bucket,
                'Key'        => $filename,
                'SourceFile' => $file,
                'ACL'        => 'public-read',
            ]);

            $url = $result['ObjectURL'];
            $success = true;
        } catch (S3Exception $e) {
            $error = " ".$e->getMessage();
            $success = false;
        }
    }else{
        $success = false;
        $error="User not authenticated";
    }
    if ($success) {
	$output = array("success" => true, "message" => "Success!", "url"=>$url);
    } else {
         $output = array("success" => false, "error" => "Failure!".$error);
    }
    
    echo json_encode($output);
    
} 


//PDF Licence Generator
add_action( 'rest_api_init', function () {
  register_rest_route( 'FML/v1', '/PDF_license_generator/', array(
    'methods' => 'POST',
    'callback' => 'PDF_license_generator',
    'permission_callback' => '__return_true'
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
// Credentials are loaded from wp-config.php constants
require_once get_stylesheet_directory()."/php/aws/aws-autoloader.php";

// Establish connection with DreamObjects with an S3 client.
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
add_action( 'rest_api_init', function () {
  register_rest_route( 'FML/v1', '/song-upload', array(
    'methods' => 'POST',
    'callback' => 'song_upload',
  ) );
} );
