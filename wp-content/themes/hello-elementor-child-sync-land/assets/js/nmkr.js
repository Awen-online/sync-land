jQuery(document).ready(function($) {
    var $content = $('.mfp-content');

    $('#mint-nft-btn').on('click', function(e) {
        e.preventDefault();

        console.log('Button clicked, opening popup...');

        $.magnificPopup.open({
            items: {
                src: '#nft-minting-popup',
                type: 'inline'
            },
            closeBtnInside: true,
            callbacks: {
                open: function() {
                    console.log('Popup opened');
                    $content.find('#minting-status').text('Processing...');
                },
                close: function() {
                    console.log('Popup closed');
                }
            }
        });

        var postId = $('input[name="post_id"]').val() || 
                     $('#mint-nft-btn').data('post-id') || 
                     nftAjax.post_id || 
                     (typeof wp !== 'undefined' && wp.data ? wp.data.select('core/editor')?.getCurrentPostId() : null);

        if (!postId) {
            console.log('Error: No post ID found');
            $content.find('#minting-status').text('Error: No post ID found');
            return;
        }

        console.log('Post ID found: ' + postId + ', starting AJAX request to '+nftAjax.ajax_url+'...');

        var formData = {
            action: 'mint_nft',
            nonce: nftAjax.nonce,
            post_id: postId
        };

        $.ajax({
            url: nftAjax.ajax_url,
            type: 'POST',
            data: formData,
            beforeSend: function() {
                console.log('AJAX request initiated');
            },
            success: function(response) {
                if (response.success) {
                    console.log('AJAX success: NFT uploaded successfully', response.data);
                    $content.find('#minting-status').text('NFT uploaded successfully!');
                    setTimeout(function() {
                        $.magnificPopup.close();
                        openNmkrPayPopup(response.data.data.nftUid);
                    }, 1000);
                } else {
                    console.log('AJAX failed: ' + response.data.message, response.data);
                    $content.find('#minting-status').text('Error: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX error: ' + status + ' - ' + error);
                $content.find('#minting-status').text('Request failed: ' + error);
            },
            complete: function() {
                console.log('AJAX request completed');
            }
        });
    });

    function openNmkrPayPopup(nftUid) {
        console.log('Opening NMKR Pay popup, nftUid: ' + (nftUid || 'not provided'));
        const paymentUrl = nftUid 
            ? "https://pay-preprod.nmkr.io/?p=ba6643e3-b89a-4985-9d83-7366969a524d&c=1&nftUid=" + nftUid 
            : "https://pay-preprod.nmkr.io/?p=ba6643e3-b89a-4985-9d83-7366969a524d&c=1";
        const popupWidth = 500;
        const popupHeight = 700;
        const left = window.top.outerWidth / 2 + window.top.screenX - (popupWidth / 2);
        const top = window.top.outerHeight / 2 + window.top.screenY - (popupHeight / 2);
        const popup = window.open(paymentUrl, "NMKR Preprod Payment", `popup=1, width=${popupWidth}, height=${popupHeight}, left=${left}, top=${top}`);
        document.body.style.background = "rgba(0, 0, 0, 0.5)";
        const checkPopup = setInterval(() => {
            if (popup.closed) {
                clearInterval(checkPopup);
                document.body.style.background = "";
                console.log('NMKR Pay popup closed');
                alert("Test payment completed or cancelled!");
            }
        }, 1000);
    }
});