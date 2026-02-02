<?php
$current_user = wp_get_current_user();
//GET ALL LICENSES FOR THE LOGGED IN USER
$paramsArtist = array(
    'where' => "user.id = '" . $current_user->ID . "' AND t.post_status = 'Publish'",
    "orderby" => "datetime.meta_value ASC"
);
$licenses = pods('license', $paramsArtist);

?>
<script>
    jQuery(document).ready( function () {
        //        jQuery('#license_table').DataTable();
        // var table = jQuery('#license_table').tablesorter({
        //     sortList: [[5,1]] // 5 is the index of the 6th column (0-based), 1 for descending
        //     // Additional configuration for better theme integration:
        // });

        var table = jQuery("#license_table").DataTable({
            "paging": true,
            "lengthChange": false,
            "searching": true,
            "ordering": true,
            "info": true,
            "autoWidth": false,
            "dom": "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                "<'row'<'col-sm-12'tr>>" +
                "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            "language": {
                "paginate": {
                    "previous": "<",
                    "next": ">"
                }
            },
            "order": [[5, "desc"]], // Set initial sort to 6th column (Datetime) in descending order
            "columns": [
                null, // Column 1 (index 0)
                null, // Column 2 (index 1)
                null, // Column 3 (index 2)
                null, // Column 4 (index 3)
                null, // Column 5 (index 4)
                { "type": "date" }, // Column 6 (index 5) - Datetime, ensure it's recognized as a date for proper sorting,
                null
            ]
        });


    } );
</script>

<table id="license_table" class="display dark">
    <thead>
        <tr>
            <th>ID</th>
            <th>Licensor</th>
            <th>Project</th>
            <th>Artist</th> 
            <th>Song</th>
            <th>Datetime</th>
            <th>Downloads</th>
        </tr>
    </thead>
    <tbody>
<?php
if (0 < $licenses->total()) {
    while ($licenses->fetch()) {
        echo "<tr>";
        $songID = $licenses->field("song")["ID"];
        $song_mp3 = pods("song",$songID)->field("audio_url");
        $songPermalink = $licenses->field("song")["guid"];
        $artistName = do_shortcode('[pods name="song" id="'.$songID.'"]{@artist.post_title}[/pods]');
        $artistPermalink = do_shortcode('[pods name="song" id="'.$songID.'"]{@artist.permalink}[/pods]');
        
        echo "<td>".$licenses->display("ID")."</td>";
        echo "<td>". $licenses->display("licensor")."</td>";
        echo "<td>". $licenses->display("project")."</td>";
        echo "<td><a href='$artistPermalink'>". $artistName. "</a></td>";
        echo "<td><a href='$songPermalink'>". $licenses->display("song")."</a></td>";
        echo "<td>". $licenses->display("datetime")."</td>";
        echo "<td><a href ='".$licenses->display('license_url')."'><button class='full-width-button'><i class='fa fa-solid fa-file-pdf'></i> License PDF</button></a>
        <a href ='$song_mp3'><button class='full-width-button'><i class='fa fa-solid fa-music'></i> Song MP3</button></a></td>";
        echo "</tr>";

    }
}else{
    ?>
            <tr>
        <td colspan='7' style='text-align: center;'>
            <p>No licenses found...</p>
            <p>Go out there and get em!</p>
        </td>
    </tr>
    <?php
}
?>
    </tbody>
</table>