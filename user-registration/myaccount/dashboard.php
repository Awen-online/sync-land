<?php
/**
 * My Account Dashboard
 *
 * Shows the first intro screen on the account dashboard.
 *
 * This template can be overridden by copying it to yourtheme/user-registration/myaccount/dashboard.php.
 *
 * HOWEVER, on occasion UserRegistration will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.wpeverest.com/user-registration/template-structure/
 * @author  WPEverest
 * @package UserRegistration/Templates
 * @version 1.0.0
 */

$current_user = get_user_by( 'id', get_current_user_id() );

// ur_print_notices();

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>



<div class="user-registration-profile-header">
	<div class="user-registration-img-container">
		<?php
			$gravatar_image      = "https://www.sync.land/wp-content/uploads/2020/06/sd2e6kJy_400x400.jpg";
			$profile_picture_url = get_user_meta( get_current_user_id(), 'user_registration_profile_pic_url', true );
			$image               = ( ! empty( $profile_picture_url ) ) ? $profile_picture_url : $gravatar_image;
		?>
		<img class="profile-preview" alt="profile-picture" src="<?php echo $image; ?>">
	</div>
	<header>
		<?php
		$first_name = ucfirst( get_user_meta( get_current_user_id(), 'first_name', true ) );
		$last_name  = ucfirst( get_user_meta( get_current_user_id(), 'last_name', true ) );
		$full_name  = $first_name . ' ' . $last_name;
		if ( empty( $first_name ) && empty( $last_name ) ) {
			$full_name = $current_user->display_name;
		}
		?>
		<h3>
                    <?php
                    printf(
                            __( 'Welcome, %1$s', 'user-registration' ),
                            esc_html( $current_user->display_name )
                    );
                    
                    ?>
                </h3>
            <!-- <p>Thank you for being part of Sync.Land.</p> -->
            
                        
	</header>
</div>


<?php
	/**
	 * My Account dashboard.
	 *
	 * @since 2.6.0
	 */
	// do_action( 'user_registration_account_dashboard' );
        
//        echo do_shortcode('[gravityform id="9" title="false" description="false" ajax="true"]');
        ?>
<!-- <div class="row">
    <h2>Community Tools</h2>
    <p>If you encounter any issues with the site, please consider join the community Discord or Report a Bug.</p>
    <div class="button btn btn-lg"><a href='https://discord.gg/mmxB2xruxZ' style="color:white;"><i class="bi bi-discord"></i> Discord</a></div>
    <div class="button btn btn-lg" ><a href='/contact-us/submit-a-bug/' style="color:white;">Report a Bug</a></div>
    <div style="margin-top:10px;">
        <p>If you have any ideas, features, or other feedback, submit feedback.</p>
        <div class="button btn btn-lg" ><a href='/contact-us/submit-feedback/' style="color:white;">Submit Feedback</a></div>
    </div>
    <div class="button btn btn-lg"><a href='/contact-fml'>Contact Us</a></div>-->
<!-- </div> --> 



<!-- <hr style="margin-top:10px; margin-bottom:10px;"> -->


<?php
        
        //do_shortcode("[account_artists]");

/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */
