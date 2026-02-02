/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Other/javascript.js to edit this template
 */

document.addEventListener("DOMContentLoaded", function() {
    var loadingScreen = document.getElementById("loading-screen");
    if (loadingScreen) {
        loadingScreen.style.display = "none";
    }
});

// Attach a submit event handler to the form
var form = document.querySelector(".gform_wrapper form");
if (form) {
    form.addEventListener("submit", function() {
        var loadingScreen = document.getElementById("loading-screen");
        // Show the loading screen when the form is submitted
        loadingScreen.style.display = "block";
    });
}

jQuery(function() {
    
    // You can also use Gravity Forms' AJAX events to hide the loading screen when the submission is complete
//    jQuery(document).on('gform_submit_button', function(event, formId){
//    //    var loadingScreen = document.getElementById("loading-screen");
//        // Hide the loading screen when the confirmation is loaded
//        var spinner = jQuery('.gform_ajax_spinner');
//        spinner.wrap('<div class="spinner-container"></div>');
//
//        // Add text below the spinner.
//        spinner.parent().append('<div class="spinner-text">Please wait while we secure your tickets...</div>');
//
//    });
});


// Display loading screen when the Gravity Forms submission starts
//document.addEventListener("gform_post_render", function(event) {
//    var loadingScreen = document.getElementById("loading-screen");
//    if (loadingScreen) {
//        loadingScreen.style.display = "block"; // Show the loading screen
//    }
//});

// Hide the loading screen when the Gravity Forms submission is complete
//document.addEventListener("gform_confirmation_loaded", function(event) {
//    var loadingScreen = document.getElementById("loading-screen");
//    if (loadingScreen) {
//        loadingScreen.style.display = "none"; // Hide the loading screen
//    }
//});

// This code assumes you have a loading screen with the ID "loading-screen"