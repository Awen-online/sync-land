<?php
/**
 * Artist Albums page
 *
 * This template can be overridden by copying it to yourtheme/user-registration/myaccount/my-account.php.
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
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

$current_user = wp_get_current_user();

/*
 * @example Safe usage: $current_user = wp_get_current_user();
 * if ( ! ( $current_user instanceof WP_User ) ) {
 *     return;
 * }
 */
//printf( __( 'Username: %s', 'textdomain' ), esc_html( $current_user->user_login ) ) . '<br />';
//printf( __( 'User email: %s', 'textdomain' ), esc_html( $current_user->user_email ) ) . '<br />';
//printf( __( 'User first name: %s', 'textdomain' ), esc_html( $current_user->user_firstname ) ) . '<br />';
//printf( __( 'User last name: %s', 'textdomain' ), esc_html( $current_user->user_lastname ) ) . '<br />';
//printf( __( 'User display name: %s', 'textdomain' ), esc_html( $current_user->display_name ) ) . '<br />';
//printf( __( 'User ID: %s', 'textdomain' ), esc_html( $current_user->ID ) );
//GET ALL ARTISTS FOR THE LOGGED IN USER
$paramsArtist = array(
    'where' => "t.post_author = '" . $current_user->ID . "' AND t.post_status = 'Publish'",
    "orderby" => "release_date.meta_value ASC"
);

$artists = pods('artist', $paramsArtist);
?>
<style>
    .cover_art{
        float:left;
        text-align:center;
        padding-right: 10px;
    }

    .album_buttons{
        clear: both;
    }
  
    .albums  {
        margin-left:25px;
    }
    .createartistbutton{
        margin-top:30px;
    }
    /* div.albumlist>div.artist:nth-of-type(odd) {
        background: #e0e0e0;
    } */
    .no-margin{
       margin: 0px 0px 0px 0px;
       padding: 0px 0px 0px 0px;
    }
    .disabled:hover{
        background-color: #D3D3D3;
        /*color: #D3D3D3;*/
    }
    .imagetd{
        padding:3px;
    }
</style>
<div class="albumlist no-margin">
<?php
//loop through album results
if (0 < $artists->total()) {
    while ($artists->fetch()) {
        ?>
            <div class="artist">
                <h2 style="margin-bottom:3px;"><?php echo $artists->display("post_title"); ?></h2>
                <img width='100px' src='<?php echo $artists->display("profile_image"); ?>'>
                <a target="_blank" class="elementor-button btn-primary" href="<?php echo esc_url(get_permalink($artists->id())); ?>"><i class="far fa-eye"></i> View</a>
                <a class="elementor-button btn btn-primary" href="/account/artist-edit/?artist_edit_id=<?php echo $artists->id(); ?>"><i class="fas fa-edit"></i> Edit</a>
            
           
            <div class="albums">

        <?php
        $albums = $artists->field('albums');
    //print_r($artists->field('albums'));

        if (isset($albums) && !empty($albums)) {
            echo "<h3>Albums</h3>";
            echo '<table class="table">
            <thead>
              <tr>
                <th scope="col"></th>
                <th scope="col">Title</th>
                <th scope="col">Release Date</th>
                <th scope="col"></th>
              </tr>
            </thead>
            <tbody>';
            
            foreach ($albums as $album) {
                $albumPod = pods("album", $album["ID"]);
//                print_r($albumPod);
                // echo " ".$album["release_date"];
                echo "<tr class='album-div'>"
                . "<td class='imagetd'><img class='album-div__image' src='" . $albumPod->display("cover_art") . "' width='100px'/></td>"
                . "<td><h4>". $album["post_title"]."</h4></td>"
                . "<td>". $albumPod->display("release_date")."</td>";
//                echo "<p>" .  . "</p>";
                //print_r($album);
                
                echo '<td><a target="_blank" class="elementor-button btn-primary" href="'.esc_url(get_permalink($albumPod->id())).'"><i class="far fa-eye"></i> View</a>
                    <!--<abbr title="Coming Soon"><div class="elementor-button btn-primary disabled" ><i class="fas fa-edit"></i> Edit </div></abbr>
                    <a class="elementor-button btn btn-primary" href="/my-account/album-edit/?artist_edit_id='.$artists->id().'"><i class="fas fa-edit"></i> Edit</a>-->
                    </td>
                </tr>';

            }
        } else {
            echo "<div><h3>No music uploaded...</h3></div>";
        }
        ?>
            </tbody>
            </table>
            <div class='album-div'><a class="elementor-button btn btn-primary" href="/my-account/album-upload-add-songs/?artist_id=<?php echo $artists->id(); ?>"><i class="fas fa-upload"></i> Upload Album/Single</a></div>
        </div>
    </div>
            <?php
        }
    } else {
        ?>
        <div>
            <h5>No artists found... </h5>
            <p>To upload your music, first "Create Artist".</p>
        </div>
<?php
}
?>
</div>
<?php
if($artists->total() <= 1){
?>

<p class="aligncenter createartistbutton">
    <a class="elementor-button btn btn-primary" href="/my-account/artist-registration"><i class="fas fa-plus"></i> Create Artist</a>
</p>

<?php
}
?>