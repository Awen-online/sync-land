<?php

if(isset($_GET['edit'])){
    include_once(get_stylesheet_directory()."/user-registration/myaccount/modules/playlist-edit.php");
}else{


$current_user = wp_get_current_user();
//GET ALL LICENSES FOR THE LOGGED IN USER
$paramsArtist = array(
    'where' => "t.post_author = '" . $current_user->ID . "' AND (t.post_status = 'publish' OR t.post_status='private')",
    "orderby" => "t.post_date DESC"
);
$playlists = pods('playlist', $paramsArtist);
?>
<script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/jquery.dataTables.min.css">
<style>
    td.details-control {
    background: url('../resources/details_open.png') no-repeat center center;
    cursor: pointer;
    }
    tr.shown td.details-control {
        background: url('../resources/details_close.png') no-repeat center center;
    }
    </style>
<script>
    jQuery(document).ready( function () {
//        var table = jQuery('#playist_table').DataTable();

    var table = jQuery('#playist_table').tablesorter({});
        
        
        // Add event listener for opening and closing details
        jQuery('#playist_table tbody').on('click', 'td.details-control', function () {
            var tr = jQuery(this).closest('tr');
            var row = table.row( tr );

            if ( row.child.isShown() ) {
                // This row is already open - close it
                row.child.hide();
                tr.removeClass('shown');
            }
            else {
                // Open this row
                row.child( format(row.data()) ).show();
                tr.addClass('shown');
            }
        } );
        
        
        //DO AJAX REQUEST WHEN ADD PLAYLIST form is sUBMITTED
        jQuery('form[name="add-playlist"]').submit(function( event ) {
            event.preventDefault();
            
            var form = { };
            jQuery.each(jQuery(this).serializeArray(), function() {
                form[this.name] = this.value;
            });

//            console.log(form);
            
            var dataToSend = { _wpnonce : '<?php echo wp_create_nonce( "wp_rest" ); ?>', playlist_name: form["playlistname"] , isprivate: form["isprivate"] ,user_id: <?php echo $current_user->ID; ?>};
            jQuery.ajax({
                url: "/wp-json/FML/v1/playlists/add", 
                type: "POST",             
                data: dataToSend,
                dataType: "json",
                cache: false,                 
                success: function(data) {

                }
            }).done(function (response) {
                console.log(response);
                if (response.success) {          
                    var playlistID = response.playlistID;
                    var isPrivate = form["isprivate"];
                    if(isPrivate === "on"){
                        isPrivate = "Yes";
                    }else{
                        isPrivate = "No";
                    }
//                    jQuery(".gif-loader").hide();
                    var editdelete = "<a href=\"?edit&playlistID="+playlistID+"\"><button class=\"playlist-edit\" data-playlistid=\""+playlistID+"\"><i class=\"fas fa-edit\"></i></button></a><button class=\"playlist-delete\" data-playlistid=\""+playlistID+"\"><i class=\"fas fa-trash-alt\"></i></button>";
                    jQuery("#playist_table tbody").prepend("<tr><td>"+playlistID+"</td><td>"+form["playlistname"]+"</td><td>draft</td><td>"+0+"</td><td>"+editdelete+"</td></tr>").show('slow');
                } else {
                    alert('fail: '+response);
                }
            }).fail(function() {
                alert('request failed'); 
            });;
            return false;
        });
        
//        
//        DELETE PLAYLIST
//
        jQuery(document).on('click', 'button.playlist-delete', function () {
        
            if (confirm('Are you sure you want to delete this playlist? This cannot be undone.')) {
                console.log("delete");
                var thisObj = jQuery(this);
                var playlistID = thisObj.data("playlistid");
                var dataToSend = { _wpnonce : '<?php echo wp_create_nonce( "wp_rest" ); ?>', playlistID: playlistID, user_id: <?php echo $current_user->ID; ?>};

                jQuery.ajax({
                    url: "/wp-json/FML/v1/playlists/delete", 
                    type: "POST",             
                    data: dataToSend,
                    dataType: "json",
                    cache: false,                 
                    success: function(data) {

                    }
                }).done(function (response) {
                    console.log(response);
                    if (response.success) {               
                        jQuery(".gif-loader").hide();
                        thisObj.closest("tr").hide('slow');
                    } else {
                        alert('fail: '+response);
                    }
                }).fail(function() {
                    alert('request failed'); 
                });
                return false;
            } else {
//                alert('Why did you press cancel? You should have confirmed');
            }
        });
        
//        
//        EDIT PLAYLIST
//
        jQuery('button.playlist-edit').on('click', function () {
            console.log("edit");
        });
        
        
        
    } );
    
    
    
</script>

<table id="playist_table" class="display table table-responsive">
    <thead>
        <tr>
            <th>ID</th>
            <th>Playlist Name</th>
            <th>Visibility</th>
            <th># of Songs</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
<?php 
if (0 < $playlists->total()) {
    while ($playlists->fetch()) {
        $playlistID = $playlists->display("ID");
        $playlistName = $playlists->display("post_title");
        $playlistStatus = $playlists->field("post_status");
        $playlistUrl = get_permalink($playlistID);
//        if($playlistStatus == "publish"){
//             $playlistStatus = "Public";
//        }
//        if($playlistStatus == "private"){
//            $playlistStatus = "Private";
//        }
        $songsDisplay = $playlists->display("songs");

        $songArray = explode(",", $playlists->display("songs"));
//        print_r($songArray);
        if(isset($songArray[0]) && !empty($songArray[0])){
            $numSongs = sizeof($songArray);
            foreach($playlists->field("songs") as $song){
    //            print_r($song);
                $id = $song["ID"];
                $url = $song["guid"];
                $songname = $song["post_title"];
    //            echo "<a href='$url'>".$songname."</a>";
            }
        }else{
            $numSongs = 0;
        }
//        echo $playistID." ".$playlistName;
        
        echo "<tr>"
        . "<td>$playlistID</td>"
        . "<td>$playlistName</td>"
        . "<td>$playlistStatus</td>"
        . "<td>$numSongs</td>"
        . "<td>"
        . "<a href=\"?edit&playlistID=$playlistID\"><button class=\"playlist-edit\" data-playlistid=\"$playlistID\"><i class=\"fas fa-edit\"></i></button></a>"
        . "<button class=\"playlist-delete\" data-playlistid=\"$playlistID\"><i class=\"fas fa-trash-alt\"></i></button>"
        . "  <a href=\"".$playlistUrl."\"><button class=\"playlist-edit\" ><i class=\"fas fa-eye\"></i></button></a>"
        . "</td>";
        
        echo "</tr>";
    }
}else{
    ?>
    <tr>
        <td colspan='4' style='text-align: center;'>
            <p>No playlists found...</p>
            
        </td>
    </tr>

<?php
}
?>
    </tbody>
</table>

<h3>Add new playlist</h3>
<form name="add-playlist" method="POST">
    <div class="form-row">
        <span class="col">
          <input required type="text" name="playlistname" class="form-control" placeholder="Playlist name">
        </span>
<!--        <span class="col">
            <input type="checkbox" class="form-check-input" name="isprivate">
            <label class="form-check-label" for="isprivate">Is Private</label>
        </span>-->
    </div>
    <input type="hidden" name="userID" value="<?php echo $current_user->ID; ?>" />
    <button type="submit" class="btn btn-primary">Add Playlist</button>
</form>

<!--//add for loading-->
<div class="overlay"></div>

<?php
}