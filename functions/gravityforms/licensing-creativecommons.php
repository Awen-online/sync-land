<?php


add_action('gform_after_submission_14', 'handle_pdf_license_generation', 10, 2);

function handle_pdf_license_generation($entry, $form) {
    // Check if this is the correct form
    if ($form['id'] != 14) {
        return;
    }

    // Retrieve field values from the Gravity Form entry using the correct field IDs
    $date = rgar($entry, '6'); // Date
    $songID = rgar($entry, '8'); // Song ID
    $licensor = rgar($entry, '5'); // Licensor
    $projectname = rgar($entry, '9'); // Project Name
    $descriptionOfUsage = rgar($entry, '10'); // Description of Usage
    $legalName = rgar($entry, '14'); // Legal Name

    // Debug: Log the data being sent to PDF_license_generator
    GFCommon::log_debug("Debug Gravity Forms Submission: Song ID: " . $songID . ", Licensor: " . $licensor . ", Project Name: " . $projectname . ", Date: " . $date . ", Description: " . $descriptionOfUsage . ", Legal Name: " . $legalName);

    // Call the PDF_license_generator function directly with parameters
    $result = PDF_license_generator($songID, $licensor, $projectname, $date, $descriptionOfUsage, $legalName);

    // Debug: Log the result of the PDF generation
    //GFCommon::log_debug("Debug PDF License Generator Result: " . print_r($result, true));

    // Handle the output from PDF_license_generator
    if (is_array($result) && isset($result['success']) && $result['success']) {
        // Success, you can add logic here to show success message or redirect
        echo "<script>
            jQuery('#api_response').append('<div><p>Your license has been generated.</p><p><button><a href=\''+'" . $result['url'] . "'+'\'><i class=\'fa fa-solid fa-download\'></i> Download </a></button><button><a href=\'/account/my-licenses\'><i class=\'fa fa-solid fa-files\'></i> View License History </a></button></p></div>');
        </script>";
    } else {
        // Debug: Log any errors from the PDF license generator
        if (isset($result['error'])) {
            GFCommon::log_debug("Debug PDF License Generator Error: " . $result['error']);
        }
        echo "<script>alert('License generation failed: " . (isset($result['error']) ? $result['error'] : 'Unknown error') . "');</script>";
    }
}

//PDF Licence Generator ROUTE
// add_action( 'rest_api_init', function () {
//   register_rest_route( 'FML/v1', '/PDF_license_generator/', array(
//     'methods' => 'POST',
//     'callback' => 'PDF_license_generator',
//     'permission_callback' => '__return_true'
//   ) );
// } );



//
//function to generate a pdf, upload it to AWS, update pods with info
//
function PDF_license_generator($songID, $licensor, $projectname, $date, $descriptionOfUsage = '', $legalName = '', $signatureImage = '') {
    // Validate required fields
    if (empty($songID) || empty($licensor) || empty($projectname)) {
        return array('success' => false, 'error' => 'Required fields (songID, licensor, projectname) are missing');
    }

    // Load dependencies
    require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

    // Get user and song data
    $userID = get_current_user_id();
    if (!$userID) {
        return array('success' => false, 'error' => 'User not logged in');
    }

    $songName = do_shortcode('[pods name="song" id="' . $songID . '"]{@post_title}[/pods]');
    $artistName = do_shortcode('[pods name="song" id="' . $songID . '"]{@artist.post_title}[/pods]');
    if (empty($songName) || empty($artistName)) {
        return array('success' => false, 'error' => 'Invalid song ID or song data missing');
    }

    // Parse and validate date
    if (!empty($date)) {
        $dateTime = DateTime::createFromFormat('d/m/Y', $date);
        if ($dateTime === false) {
            $dateTime = DateTime::createFromFormat('Y-m-d', $date);
        }
        if ($dateTime === false) {
            return array('success' => false, 'error' => 'Datetime was not provided in a recognizable format: "' . esc_html($date) . '"');
        }
        $currentDateTime = $dateTime->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    } else {
        $currentDateTime = gmdate('Y-m-d\TH:i:s\Z');
    }

    // Get site logo
    // $custom_logo_id = get_theme_mod('custom_logo');
    // $sitelogo = $custom_logo_id ? wp_get_attachment_image_url($custom_logo_id, 'full') : '';
    $sitelogo = "https://www.sync.land/wp-content/uploads/2024/06/SYNC.LAND_.jpg";

    // Initialize mPDF
    $mpdf = new \Mpdf\Mpdf();

    // Generate PDF content
    $html = '
        <style>
            a { color: #277acc; text-decoration: none; }
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .center { text-align: center; }
            .container { width: 80%; margin: 0 auto; }
            ul { margin: 10px 0; padding-left: 20px; }
            .attribution-example { margin-top: 20px; font-style: italic; color: #555; }
            .details-list { margin-top: 10px; }
        </style>
        <body>
            <div class="center container"><img src="' . esc_url($sitelogo) . '" alt="Site Logo" style="max-width: 70%;" /></div>
            <div class="center" style="margin-top:100px;"><h2>Statement of Licensure</h2></div>
            <div class="container">
                I, <strong><em>' . esc_html($licensor) . '</em></strong> (legal name: <strong><em>' . esc_html($legalName) . '</em></strong>), 
                hereby agree to the license terms herein, as of ' . esc_html($currentDateTime) . ' UTC.
                <ul class="details-list">
                    <li><strong>Song:</strong> ' . esc_html($songName) . '</li>
                    <li><strong>Artist:</strong> ' . esc_html($artistName) . '</li>
                    <li><strong>Project:</strong> ' . esc_html($projectname) . '</li>
                    <li><strong>Description of Usage:</strong> ' . esc_html($descriptionOfUsage) . '</li>
                </ul>
                <div class="attribution-example">
                    <strong>Attribution Example:</strong><br>
                    Music: ' . esc_html($artistName) . ' - "' . esc_html($songName) . '" courtesy of <a href="https://awen.online">Awen</a><br>
                    Under CC BY license at <a href="https://sync.land">https://sync.land</a>
                </div>
            </div>';

    // Add signature image if provided
    if (!empty($signatureImage)) {
        $html .= '<div class="container" style="margin-top: 20px;">
                    <strong>Signature:</strong><br>
                    <img src="' . esc_url($signatureImage) . '" alt="Signature" style="max-width: 150px; max-height: 50px;" />
                  </div>';
    }

    // Add page break before Attribution 4.0 section
    $html .= '<pagebreak>';

    $html .= '
            <div class="center" style="margin-top: 20px;">
                <img src="/wp-content/uploads/2020/05/cc.svg" alt="CC" style="vertical-align: middle;">
                <img src="/wp-content/uploads/2020/04/by.svg" alt="BY" style="vertical-align: middle;">
            </div>
            <div class="center"><h1>Attribution 4.0 International</h1></div>
            <div class="center"><em>This is a human-readable summary of the license that follows:</em></div>
            <div class="center"><h3>You are free to:</h3></div>
            <div class="container">
                <ul>
                    <li><strong>Share</strong> - Copy and redistribute the material in any medium or format</li>
                    <li><strong>Adapt</strong> - Remix, transform, and build upon the material for any purpose, even commercially.</li>
                </ul>
                <p>The licensor cannot revoke these freedoms as long as you follow the license terms.</p>
            </div>
            <div class="center"><h3>When doing so, you must comply with these terms:</h3></div>
            <div class="container">
                <ul>
                    <li><strong>Attribution</strong> - You must give <a href="https://wiki.creativecommons.org/wiki/License_Versions#Detailed_attribution_comparison_chart">appropriate credit</a>, provide a link to the license, and <a href="https://wiki.creativecommons.org/wiki/License_Versions#Modifications_and_adaptations_must_be_marked_as_such">indicate if changes were made</a>. You may do so in any reasonable manner, but not in any way that suggests the licensor endorses you or your use.</li>
                    <li><strong>No Additional Restrictions</strong> - You may not apply legal terms or <a href="https://wiki.creativecommons.org/wiki/License_Versions#Application_of_effective_technological_measures_by_users_of_CC-licensed_works_prohibited">technological_measures</a> that legally restrict others from doing anything the license permits.</li>
                </ul>
            </div>
            <div class="center"><h3>Notices:</h3></div>
            <div class="container">
                <ul>
                    <li>You do not have to comply with the license for works in the public domain or where your use is permitted by an applicable <a href="https://wiki.creativecommons.org/Frequently_Asked_Questions#Do_Creative_Commons_licenses_affect_exceptions_and_limitations_to_copyright.2C_such_as_fair_dealing_and_fair_use.3F">exception or limitation</a>.</li>
                    <li>No warranties are given. The license may not give you all of the permissions necessary for your intended use. For example, other rights such as <a href="https://wiki.creativecommons.org/Considerations_for_licensors_and_licensees">publicity, privacy, or moral rights</a> may limit how you use the material.</li>
                </ul>
            </div>
        </body>';

    $mpdf->WriteHTML($html);

    // Append Creative Commons license PDF
    $ccFile = $_SERVER['DOCUMENT_ROOT'] . '/wp-content/uploads/2020/04/Creative-Commons-—-Attribution-4.0-International-—-CC-BY-4.0.pdf';
    if (file_exists($ccFile)) {
        $pagecount = $mpdf->SetSourceFile($ccFile);
        for ($i = 1; $i <= $pagecount; $i++) {
            $mpdf->AddPage();
            $import_page = $mpdf->ImportPage($i);
            $mpdf->UseTemplate($import_page);
        }
    }

    // Generate filename and save to temp file
    $filename = sanitize_file_name($artistName . "_" . $songName . "_CCBY40_" . str_replace(':', '-', $currentDateTime) . ".pdf");
    $tmpPath = tempnam(sys_get_temp_dir(), 'pdf_');
    $mpdf->Output($tmpPath, 'F');

    // Upload to AWS
    // Credentials are loaded from wp-config.php constants
    require get_stylesheet_directory() . "/php/aws/aws-autoloader.php";
    $client = new Aws\S3\S3Client([
        'version'     => '2006-03-01',
        'region'      => FML_AWS_REGION,
        'endpoint'    => FML_AWS_HOST,
        'credentials' => [
            'key'    => FML_AWS_KEY,
            'secret' => FML_AWS_SECRET_KEY,
        ]
    ]);

    $bucket = 'fml-licenses';
    try {
        $result = $client->putObject([
            'Bucket'     => $bucket,
            'Key'        => $filename,
            'SourceFile' => $tmpPath,
            'ACL'        => 'public-read',
        ]);
        $url = $result['ObjectURL'];
        $success = true;
    } catch (Exception $e) {
        unlink($tmpPath);
        return array('success' => false, 'error' => 'AWS upload failed: ' . $e->getMessage());
    }

    // Insert license data into Pods
    if ($success) {
        $pod = pods('license');
        $data = array(
            'user'               => $userID,
            'song'               => $songID,
            'datetime'           => $currentDateTime,
            'license_url'        => $url,
            'licensor'           => $licensor,
            'project'            => $projectname,
            'description_of_usage' => $descriptionOfUsage,
            'legal_name'         => $legalName,
            'signature'          => $signatureImage
        );

        $new_license_id = $pod->add($data);
        if ($new_license_id) {
            wp_update_post(array(
                'ID'          => $new_license_id,
                'post_status' => 'publish'
            ));
        } else {
            $success = false;
            $error = 'Failed to insert license record';
        }
    }

    // Clean up temp file
    unlink($tmpPath);

    // Return result
    return $success ? array('success' => true, 'message' => 'License generated successfully', 'url' => $url)
                    : array('success' => false, 'error' => $error);
}
    
    // return $output;
    // echo json_encode($output);
//    wp_die(); // required. to end AJAX request.

 
