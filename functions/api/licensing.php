<?php



//PDF Licence Generator ROUTE
add_action( 'rest_api_init', function () {
  register_rest_route( 'FML/v1', '/PDF_license_generator/', array(
    'methods' => 'POST',
    'callback' => 'PDF_license_generator',
    'permission_callback' => '__return_true'
  ) );
} );



//
//function to generate a pdf, upload it to AWS, update pods with info
//
function PDF_license_generator($songID){

//    header("Content-type: application/pdf"); 
//    header("Content-Disposition: inline; filename=cc-license.pdf");
    header("Content-Type: application/json; charset=utf-8");
//    echo "test";
    
//    $_POST = json_decode(file_get_contents("php://input"), true);
//    print_r($_POST);
//    print_r($_REQUEST);
    
    $nonce = check_ajax_referer( 'wp_rest', '_wpnonce' );
    
    
    
    //authenticate (check if user is logged in)
    //echo "nonce: ".$nonce;
    
    if($nonce){
    
        require_once $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';

        //DEFINE VARIABLES
        $songID = $_POST['songID'];
        if(isset($_POST['licensor'])){
            $licensor = sanitize_text_field($_POST['licensor']);
        }else{
            wp_die("missing fields");
        }
        if(isset($_POST['projectname'])){
            $projectname = sanitize_text_field($_POST['projectname']);
        }else{
            wp_die("missing fields");
        }

        $user_id = apply_filters( 'determine_current_user', false );
        wp_set_current_user( $user_id );
        $userID = get_current_user_id();
        $songName = do_shortcode('[pods name="song" id="'.$songID.'"]{@post_title}[/pods]');
        $artistName = do_shortcode('[pods name="song" id="'.$songID.'"]{@artist.post_title}[/pods]');
        
        //current datetime
        $currentDateTime = gmdate("Y-m-d\TH:i:s\Z");
        
        //
//        $userFirstName = get_userdata( $userID )->first_name;
//        $userLastName = get_userdata( $userID )->last_name;
//        $userEmail = get_userdata( $userID )->user_email;

    //    $custom_logo_id = get_theme_mod( 'custom_logo' );
    //    $image = wp_get_attachment_image_src( $custom_logo_id , 'full' );
        // $sitelogo = "/wp-content/uploads/2020/06/FML-Title.png";
        $custom_logo_id = get_theme_mod('custom_logo');
        $sitelogo = $custom_logo_id ? wp_get_attachment_image_url($custom_logo_id, 'full') : '';



        $mpdf = new \Mpdf\Mpdf();
        $mpdf->AddPage();
        $mpdf->WriteHTML(''
                . '<style> a {color: #277acc; text-decoration: none;}</style>'
                . '<body style="font-family: sans-serif"><div style="width: 75%; text-align: center; margin: auto;"><img src="'.$sitelogo.'" /></div>'
                . '<div style="width: 100%; text-align: center;"><h2>Statement of Licensure</h2></div>'
                . '<div>I, '
                . '<strong><em>'.$licensor.'</em></strong> '
                . 'hereby agree to the license terms herein, as of '.$currentDateTime.' UTC, '
                . 'for the song <strong><em>'. $songName.'</em></strong> '
                . 'created by <strong><em>'. $artistName.'</em></strong> for use in the project <strong><em>'.$projectname.'</em></strong>.'
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

        $filename = $artistName."_".$songName."_CCBY40_".$currentDateTime.".pdf";
        $tmpname = get_temp_dir();
        $temp = tmpfile();
        $path = stream_get_meta_data($temp)['uri'];
//        echo "filexists: ".file_exists($path)." size: ".filesize($path)."<br />";
        //echo "path: $path";
        $file = $mpdf->Output($path, "F");
//        echo get_temp_dir()."tmp.pdf";
//        echo "filexists: ".file_exists($path)." size: ".filesize($path);


        //UPLOAD TO AWS

        /* AMAZON WEB SERVICES CONFIGURATION */
        require get_stylesheet_directory()."/php/aws/aws-autoloader.php";

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

        //CREATE AN OBJECT
        $bucket = 'fml-licenses';
        try{
            $result = $client->putObject([
                'Bucket'     => $bucket,
                'Key'        => $filename,
                'SourceFile' => $path,
                'ACL'        => 'public-read',
            ]);

            $url = $result['ObjectURL'];
            $success = true;
        } catch (S3Exception $e) {
            $error = " ".$e->getMessage();
            $success = false;
        }
        
        //insert new license data into pods
        if($success){
            // Get the book pod object
            $pod = pods( 'license' );

            // To add a new item, let's set the data first
            $data = array(
                'user' => $userID,
                'song' => $songID,
                'datetime' => $currentDateTime, 
                'license_url' => $url, 
                'licensor' => $licensor, 
                'project' => $projectname
            );

            // Add the new item now and get the new ID
            $new_license_id = $pod->add( $data );
            if(empty($new_license_id)){
               $success = false;
               $error = "Issue inserting record";
            }else{
                 //IF SUCCESSFULL, CHANGE LICENSE TO PUBLISHED
                wp_update_post(array(
                    'ID' => $new_license_id,
                    'post_status' => 'publish'
                ));
            }
        }
    }else{
        $success = false;
        $error="Nonce issue.";
    }
    
    
    if ($success) {
	$output = array("success" => true, "message" => "Success!", "url"=>$url);
    } else {
        $output = array("success" => false, "error" => "Failure! ".$error);
    }
    
    echo json_encode($output);
//    wp_die(); // required. to end AJAX request.

} 
