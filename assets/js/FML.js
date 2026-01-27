(function($) {
//this.randomtip = function(){
//    var length = $("#tips li").length;
//    var ran = Math.floor(Math.random()*length) + 1;
//    $("#tips li:nth-child(" + ran + ")").show();
//};
// 
$(document).ready(function(){
    
  $('.add-to-playlist-button').magnificPopup({
      type: 'ajax',
      mainClass: 'mfp-align-center', // Custom class for additional styling
      alignTop: false, // Center vertically (false means middle, true means top-aligned)
      fixedContentPos: true, // Keeps content fixed relative to viewport
      fixedBgPos: true, // Keeps background fixed
      showCloseBtn: true, // Ensures close button is visible
      closeBtnInside: true, // Place close button outside content (default behavior)
      removalDelay: 300, // Slight delay for smooth exit animation
      callbacks: {
          parseAjax: function(mfpResponse) {
            // mfpResponse.data is a "data" object from ajax "success" callback
            // for simple HTML file, it will be just String
            // You may modify it to change contents of the popup
            // For example, to show just #some-element:
            // mfpResponse.data = $(mfpResponse.data).find('#some-element');

            // mfpResponse.data must be a String or a DOM (jQuery) element

            console.log('Ajax content loaded:', mfpResponse);
          },
          ajaxContentAdded: function() {
            // Ajax content is loaded and appended to DOM
            console.log(this.content);
          }
      }
  });
  // Updated form submission handler
  $(document).on("click", '.save-addsong-to-playlist', function(e) {
      e.preventDefault(); // Prevent default submit behavior

      var $button = $(this);
      var nonce = $button.data("nonce");
      var songID = $button.data("songid");
      var playlistID = $("#playlistForm input:checked").map(function() {
          return $(this).val();
      }).get().join(","); // Simplified playlist ID string creation

      var dataToSend = {
          "_wpnonce": nonce,
          "playlistID": playlistID,
          "songID": songID
      };

      $.ajax({
          url: "/wp-json/FML/v1/playlists/addsong",
          type: "POST",
          data: dataToSend,
          dataType: "json",
          cache: true,
          beforeSend: function() {
              $button.prop('disabled', true).text('Saving...'); // Disable button and show loading state
          },
          success: function(response) {
              console.log(response);
              var $content = $('.mfp-content'); // Target the popup content

              if (response.success) {
                  // Replace content with success message
                  $content.html('<div style="padding: 15px; text-align: center; color: #155724; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;">Song added successfully!</div>');
                  // Auto-close after 2 seconds
                  setTimeout(function() {
                      $.magnificPopup.close();
                  }, 2000);
              } else {
                  // Replace content with failure message
                  $content.html('<div style="padding: 15px; text-align: center; color: #721c24; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">Failed: ' + (response.error || 'Unknown error') + '</div>');
                  // No auto-close; user can close manually
              }
          },
          error: function(xhr, status, error) {
              console.log('AJAX error:', status, error);
              var $content = $('.mfp-content');
              $content.html('<div style="padding: 15px; text-align: center; color: #721c24; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">Request failed. Please try again.</div>');
              $button.prop('disabled', false).text('Save'); // Re-enable button
          }
      });
  });



  //WHEN A USER PLAYS A SONG
  $(".song-play").click(function(){
      
      var audioFileSrc = $(this).data("audiosrc");
      var songTitle = $(this).data("songname");
      var artistName = $(this).data("artistname");
      var albumName = $(this).data("albumname");
      var artistLink = '';
      var category = "ALL CATEGORIES";
      var albumArtSrc = $(this).data("artsrc");
      var buyLink = '';

      var songObj = {
          "name": songTitle,
          "artist": artistName,
          "album": albumName,
          "url": audioFileSrc,
          "cover_art_url": albumArtSrc,
          "made_up_key": "I'm made up completely"
      };
      
  //    var activePlaylist = Amplitude.getActivePlaylist();
  //    Amplitude.addSongToPlaylist( songObj, activePlaylist );
      
  //    var newIndex = Amplitude.addSong( songObj );
  //    appendToSongDisplay( songObj, newIndex );
  //        Amplitude.bindNewElements();
      
      Amplitude.playNow( songObj );
      Amplitude.bindNewElements();

  //    var songHTML = '<ul><li id="'+songTitle+'" class="xtitle">'+songTitle+'</li><li class="xcategory">'+category+'</li><li class="xauthor">'+artistName+'</li><li class="xauthorlink">'+artistLink+'</li><li class="xauthorlinktarget">_blank</li><li class="ximage">'+albumArtSrc+'</li><li class="xbuy">'+buyLink+'</li><li class="xsources_mp3">'+audioFileSrc+'</li></ul>'
      
      //TODO: Enhance by checking by Pods ID
      //if(!$(".xaudioplaylist").find("#"+songTitle)){
  //        $(".xaudioplaylist").append(songHTML);
      //}
  //    $.audio7_html5.changeMp3(audioFileSrc,songTitle,artistName,albumArtSrc);
  });

      //
      //playlist script
      //
      
  //    $('.add-to-playlist').click(function(event) {
  //        event.preventDefault();
  //    //  var dataToSend = { _wpnonce : '<?php echo wp_create_nonce( "wp_rest" ); ?>'};
  //        var userID = jQuery(this).data("userid");
  //        
  //        if (typeof userID === 'undefined' || userID === null || userID === "" ){
  //            alert("Please login or register to use this feature.");
  //            return;
  //        }
  //        var dataToSend = { userID: userID};
  //        jQuery.ajax({
  //            url: "/wp-json/FML/v1/playlists/", 
  //            type: "GET",         
  //            async:false,
  //            data: dataToSend,
  //            dataType: "json",
  //            cache: true,                 
  //            success: function(data) {
  //
  //            }
  //        }).done(function (response) {
  //            console.log(response);
  //            if (response.success) {               
  ////                jQuery(".gif-loader").hide();
  //                  jQuery(response).appendTo('.playlist-modal').modal();    
  ////                jQuery("#api_response").append("<div><p>Your license has been generated. </p><p><button><a href ='"+response.url+"'><i class='fa fa-solid fa-download'></i> Download </a></button><button><a href ='/account/my-licenses'><i class='fa fa-solid fa-files'></i> View License History </a></button></p></div>");
  //            } else {
  //                alert('fail: '+response.error);
  //            }
  //        }).fail(function() {
  //            alert('request failed'); 
  //        });;
  //        return false;
  //    });


});


})( jQuery );

//global ajax spinner
jQuery(document).on({
    ajaxStart: function(){
        jQuery("body").addClass("loading"); 
    },
    ajaxStop: function(){ 
        jQuery("body").removeClass("loading"); 
    }    
});

/*
Appends the song to the display.
*/
function appendToSongDisplay( song, index ){
/*
  Grabs the playlist element we will be appending to.
*/
var playlistElement = document.querySelector('.white-player-playlist');

/*
  Creates the playlist song element
*/
var playlistSong = document.createElement('div');
playlistSong.setAttribute('class', 'white-player-playlist-song amplitude-song-container amplitude-play-pause');
playlistSong.setAttribute('data-amplitude-song-index', index);

/*
  Creates the playlist song image element
*/
var playlistSongImg = document.createElement('img');
playlistSongImg.setAttribute('src', song.cover_art_url);

/*
  Creates the playlist song meta element
*/
var playlistSongMeta = document.createElement('div');
playlistSongMeta.setAttribute('class', 'playlist-song-meta');

/*
  Creates the playlist song name element
*/
var playlistSongName = document.createElement('span');
playlistSongName.setAttribute('class', 'playlist-song-name');
playlistSongName.innerHTML = song.name;

/*
  Creates the playlist song artist album element
*/
var playlistSongArtistAlbum = document.createElement('span');
playlistSongArtistAlbum.setAttribute('class', 'playlist-song-artist');
playlistSongArtistAlbum.innerHTML = song.artist+' &bull; '+song.album;

/*
  Appends the name and artist album to the playlist song meta.
*/
playlistSongMeta.appendChild( playlistSongName );
playlistSongMeta.appendChild( playlistSongArtistAlbum );

/*
  Appends the song image and meta to the song element
*/
playlistSong.appendChild( playlistSongImg );
playlistSong.appendChild( playlistSongMeta );

/*
  Appends the song element to the playlist
*/
playlistElement.appendChild( playlistSong );
}

