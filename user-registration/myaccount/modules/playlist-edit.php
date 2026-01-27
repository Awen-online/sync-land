<?php
$current_user = wp_get_current_user();

if(is_user_logged_in() && isset($_GET['edit']) && isset($_GET['playlistID'])){

$playlistID = $_GET['playlistID'];
    
$playlist = pods('playlist', $playlistID);
$playlistObj = $playlist->export();
$songs = $playlistObj["songs"];
$isPrivate = $playlist->display("is_private");
?>
<script>
 function onListMap(idx, el) {
   return el.id; // Use '.html()' if needed
};   
    
jQuery(document).ready( function () {
    jQuery( ".sortable" ).sortable({
        placeholder: "highlight"
    });
    
    
    //
    //ADD THE SELECTED SONG 
    //
    jQuery(".plussong").click( function (e) {
        e.preventDefault();
        var songid =  jQuery(this).data("songid");
        var playlistid = "";
        var name = jQuery(this).data("value");
        jQuery("ol.sortable").append('<li id="'+songid+
            '" class="ui-state-default ui-sortable-handle"><span class="ui-icon ui-icon-arrowthick-2-n-s"></span>'
            +name+'<button class="mr-0 ml-auto songdelete" data-playlistid="'+playlistid+'" data-songid="'
            +songid+'"><i class="fas fa-trash-alt"></i></button></li>');
        
        jQuery(".songsadd").val('');
        jQuery(".plussong").prop('disabled', true);
        jQuery(".plussong").attr("data-label","");
        jQuery(".plussong").attr("data-value","");
        jQuery(".plussong").attr("data-songid","");
        //jQuery('<input name="song[]" class="songsadd">').insertBefore(this);
    });
    
    //
    // WHEN THE DELETE SONG BUTTON IS PRESSED
    //
    jQuery(document).on('click', ".songdelete", function (e){
        e.preventDefault();
        var playlistID = jQuery(this).data("playlistid");
        var songID = jQuery(this).data("songid");
        jQuery(this).parent("li").remove();
    });
    
    //
    // WHEN THE EDIT FORM IS SUBMITTED
    //
    jQuery("#editPlaylistForm").submit(function (e){
        e.preventDefault();
        
        var songList = jQuery('#editPlaylistForm li').map(onListMap).get();
        var separator = ',';
        var implodedArray = songList.join(separator);
        
        var postString = jQuery(this).serialize();
        var nonce = '<?php echo wp_create_nonce( "wp_rest" ); ?>';
        postString = "_wpnonce="+nonce+"&edit=true&" + postString;
        postString += "&songs="+implodedArray;
        
        jQuery.ajax({
            url: "/wp-json/FML/v1/playlists/edit", 
            type: "POST",             
            data: postString,
            dataType: "json",
            cache: false,                 
            success: function(data) {

            }
        }).done(function (response) {
            console.log(response);
            if (response.success) {               
                jQuery(".gif-loader").hide();
                window.location.replace("/account/playlists/");
            } else {
                alert('fail: '+response);
            }
        }).fail(function() {
            alert('request failed'); 
        });

        
    });
    
    
    //
    //INITIALIZE THE AUTOCOMPLETE
    //
    jQuery( ".songsadd" ).autocomplete({
        delay: 700,
        source: function( request, response ) {
        jQuery.ajax({
          url: "/wp-json/FML/v1/song-search",
          type: "GET",           
          dataType: "json",
          data: {
            q: request.term,
            _wpnonce: '<?php echo wp_create_nonce( "wp_rest" ); ?>'
          },
          success: function( data ) {
            response(jQuery.map( data.songs, function( song, i ) {
                song = data.songs[i];
                if(Array.isArray(song.album) && song.album.length){
                    var album = song.album[0];
                    var artistName = album.artist.post_title;
                    return {
                        value: artistName +" - "+song.post_title,
                        label: artistName +" - "+song.post_title,
                        songid: song.id
                    };
                }
                return;
            }));
          }
        }).done(function (response) {
            if (response.success) {               
            } else {
                alert('fail: '+response);
            }
        }).fail(function() {
            alert('request failed'); 
        });
    },
    minLength: 3,
    select: function( event, ui ) {
    
        if(jQuery('.plussong').hasAttr('data-label')) {jQuery(".plussong").data("label",ui.item.label); }else{ jQuery(".plussong").attr("data-label",ui.item.label); }
        if(jQuery('.plussong').hasAttr('data-value')) {jQuery(".plussong").data("value",ui.item.value); }else{ jQuery(".plussong").attr("data-value",ui.item.value); }
        if(jQuery('.plussong').hasAttr('data-songid')) {jQuery(".plussong").data("songid",ui.item.songid); }else{ jQuery(".plussong").attr("data-songid",ui.item.songid); }
        jQuery(".plussong").prop('disabled', false);
        console.log( ui.item ?
          "Selected: " + ui.item.label :
          "Nothing selected, input was " + this.value);
    },
    open: function() {
        jQuery( this ).removeClass( "ui-corner-all" ).addClass( "ui-corner-top" );
    },
    close: function() {
        jQuery( this ).removeClass( "ui-corner-top" ).addClass( "ui-corner-all" );
    }
    });
    
    jQuery.fn.hasAttr = function(name) {  
        return this.attr(name) !== undefined;
    };
    
} );
</script>
<style> 
    ol {
        list-style:none;
        text-align:left;
        margin: 0 auto;
    }
    .mr-0 {
      margin-right: 0;
    }
    .ml-auto {
      margin-left:auto;
    }

</style>
<!--<script src="<?php //echo get_stylesheet_directory_uri()."/js/touchpunch.js"; ?>"></script>-->

<link rel="stylesheet" href="//code.jquery.com/ui/1.13.0/themes/base/jquery-ui.css">

<a href="/account/playlists"><i class="fas fa-long-arrow-alt-left"></i> Back to My Playlists</a>
<h1>Edit Playlist</h1>

<form id="editPlaylistForm">
    
    <input type="hidden" name="playlistID" value="<?php echo $playlist->display("id"); ?>" />
    
    <div class="form-group">
        <label for="post_title">Playlist Name</label>
        <input type="text" name="post_title" value="<?php echo $playlist->display("post_title"); ?>" required />
    </div>
    
    <div class="form-group">
        
         <input type="checkbox" class="form-check-input" name="isprivate" <?php echo $isPrivate == "Yes" ? "checked" : ""; ?>>
        <label class="form-check-label" for="isprivate">Is Private</label>
    </div>

    
    
    <div class="form-group">
        <label for="songs">Songs</label>
        <div class="ui-widget">
            <label for="songsadd">Add Song: </label>
            <input placeholder="Search song..." name="song" class="songsadd">
            <button disabled="true" class="plussong"><i class="fas fa-plus-circle"></i></button>
        </div>
        <ol class="sortable">
            
        <?php
        if(empty($songs)){
            echo "<p>No songs found...</p>";
        }else{
        
        ?>
        
        <?php foreach($songs as $id => $song){
            $song = pods("song", $id);
            $artist_name = $song->display("artist");
            $name = $song->display("post_title");

            echo '<li id="'.$id.'" class="ui-state-default"><span class="ui-icon ui-icon-arrowthick-2-n-s"></span>'.$artist_name." - ".$name
//               .' <input type="hidden" name="song[]" value="'.$id.'">'
               . '<button class="mr-0 ml-auto songdelete" data-playlistID="'.$playlistID.'" data-songID="'.$id.'"><i class="fas fa-trash-alt"></i></button>'
               . '</li>';
        }
        ?>
        
        
        <?php }?>
        </ol>
    </div>
       
        <div class="ui-widget">
            <button class="btn button" type="submit">Save Playlist</button>
        </div>

    
    
</form>
<!--//add for loading-->
<div class="overlay"></div>
<?php
}else{
//    do_shortcode();
    echo "pls login tho.";
}
    