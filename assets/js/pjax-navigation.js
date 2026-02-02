/*
 * PJAX Navigation for Sync.Land
 * Intercepts internal link clicks and loads content via AJAX,
 * keeping the music player and Three.js background alive across navigations.
 *
 * Strategy: Only swap the "content zone" between the header and footer.
 * The Elementor header, sticky footer (which contains the music player),
 * Three.js background, and loading screen are never touched.
 *
 * Content nodes = body direct children that are NOT:
 *   - header / footer (Elementor or theme)
 *   - #threejs-background, #loading-screen
 *   - script, style, link, noscript
 */
(function($) {
    'use strict';

    // Selectors for elements that should NEVER be swapped
    var SKIP_SELECTORS = [
        '[data-elementor-type="header"]',
        '[data-elementor-type="footer"]',
        '.elementor-location-header',
        '.elementor-location-footer',
        '#site-header',
        '#site-footer',
        'header',
        'footer',
        '#threejs-background',
        '#loading-screen',
        '.example-container',
        '#white-player'
    ];

    // Tag names to always skip (not content)
    var SKIP_TAGS = ['SCRIPT', 'STYLE', 'LINK', 'NOSCRIPT'];

    function isSkipNode(node) {
        if (node.nodeType !== 1) return true; // skip text/comment nodes
        if (SKIP_TAGS.indexOf(node.tagName) !== -1) return true;
        for (var i = 0; i < SKIP_SELECTORS.length; i++) {
            try {
                if (node.matches(SKIP_SELECTORS[i])) return true;
            } catch(e) {}
        }
        return false;
    }

    function getContentNodes(bodyEl) {
        var nodes = [];
        var children = bodyEl.children;
        for (var i = 0; i < children.length; i++) {
            if (!isSkipNode(children[i])) {
                nodes.push(children[i]);
            }
        }
        return nodes;
    }

    // Find the footer element in the current page (insert point for new content)
    function findFooter() {
        var selectors = [
            '[data-elementor-type="footer"]',
            '.elementor-location-footer',
            '#site-footer',
            'footer'
        ];
        for (var i = 0; i < selectors.length; i++) {
            var el = document.body.querySelector(':scope > ' + selectors[i]);
            if (el) return el;
        }
        // Fallback: find #threejs-background or .example-container as insert anchor
        return document.getElementById('threejs-background')
            || document.querySelector('.example-container')
            || null;
    }

    var PjaxNav = {
        enabled: true,
        isNavigating: false,
        currentUrl: window.location.href,

        init: function() {
            if (document.body.classList.contains('wp-admin')) return;

            this.bindLinks();
            this.bindPopState();

            console.log('PJAX Navigation initialized');
        },

        bindLinks: function() {
            var self = this;

            $(document).on('click', 'a', function(e) {
                if (!self.enabled || self.isNavigating) return;

                var $link = $(this);
                var href = $link.attr('href');

                if (!self.shouldHandle($link, href)) return;

                e.preventDefault();
                self.navigate(href);
            });
        },

        bindPopState: function() {
            var self = this;
            window.addEventListener('popstate', function(e) {
                if (!self.enabled || self.isNavigating) return;
                self.navigate(window.location.href, true);
            });
        },

        shouldHandle: function($link, href) {
            if (!href) return false;
            if (href.charAt(0) === '#') return false;
            if (href.indexOf('javascript:') === 0) return false;
            if (href.indexOf('mailto:') === 0 || href.indexOf('tel:') === 0) return false;

            try {
                var url = new URL(href, window.location.origin);
                if (url.host !== window.location.host) return false;
            } catch(e) {
                return false;
            }

            if (href.indexOf('/wp-admin') !== -1) return false;
            if (href.indexOf('/wp-login') !== -1) return false;
            if (href.indexOf('action=logout') !== -1) return false;

            if ($link.attr('target') === '_blank') return false;
            if ($link.hasClass('no-pjax') || $link.data('pjax') === false) return false;
            if ($link.attr('download') !== undefined) return false;

            var e = window.event;
            if (e && (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey)) return false;

            var ext = href.split('?')[0].split('#')[0].split('.').pop().toLowerCase();
            var skipExts = ['pdf', 'zip', 'rar', 'mp3', 'mp4', 'wav', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'svg'];
            if (skipExts.indexOf(ext) !== -1) return false;

            return true;
        },

        navigate: function(url, isPopState) {
            var self = this;
            if (self.isNavigating) return;
            self.isNavigating = true;

            self.showLoading();
            self.savePlayerState();

            $.ajax({
                url: url,
                type: 'GET',
                dataType: 'html',
                timeout: 15000,
                success: function(html) {
                    try {
                        self.swapContent(html, url, isPopState);
                    } catch(err) {
                        console.log('PJAX swap error, falling back:', err);
                        window.location.href = url;
                    }
                },
                error: function() {
                    console.log('PJAX fetch failed, falling back to normal navigation');
                    window.location.href = url;
                },
                complete: function() {
                    self.isNavigating = false;
                    self.hideLoading();
                }
            });
        },

        swapContent: function(html, url, isPopState) {
            var self = this;

            // Parse the fetched page into a real document
            var parser = new DOMParser();
            var newDoc = parser.parseFromString(html, 'text/html');

            // Get the content nodes from the new page's body
            var newContentNodes = getContentNodes(newDoc.body);
            if (newContentNodes.length === 0) {
                console.log('PJAX: no content nodes found in new page, falling back');
                window.location.href = url;
                return;
            }

            // 1) Remove current content nodes from the live DOM
            var currentContentNodes = getContentNodes(document.body);
            currentContentNodes.forEach(function(node) {
                node.parentNode.removeChild(node);
            });

            // 2) Insert new content nodes before the footer
            var insertAnchor = findFooter();
            newContentNodes.forEach(function(node) {
                var imported = document.adoptNode(node);
                if (insertAnchor) {
                    document.body.insertBefore(imported, insertAnchor);
                } else {
                    document.body.appendChild(imported);
                }
            });

            // 3) Update body classes from the new page
            if (newDoc.body.className) {
                document.body.className = newDoc.body.className;
            }

            // 4) Update page title
            var newTitle = newDoc.title || '';
            if (newTitle) {
                document.title = newTitle;
            }

            // 5) Load any new stylesheets from the new page's <head>
            self.updateHeadStyles(newDoc);

            // 6) Update URL
            if (!isPopState) {
                window.history.pushState({ pjax: true, url: url }, newTitle, url);
            }
            self.currentUrl = url;

            // 7) Scroll to top
            window.scrollTo(0, 0);

            // 8) Execute inline scripts from the new content
            self.executeNewScripts(newDoc);

            // 9) Re-initialize Elementor and other plugins
            self.reinitScripts();
        },

        updateHeadStyles: function(newDoc) {
            var existingHrefs = {};
            $('link[rel="stylesheet"]').each(function() {
                var h = $(this).attr('href');
                if (h) existingHrefs[h] = true;
            });

            $(newDoc).find('link[rel="stylesheet"]').each(function() {
                var href = $(this).attr('href');
                if (href && !existingHrefs[href]) {
                    var link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = href;
                    if ($(this).attr('media')) link.media = $(this).attr('media');
                    document.head.appendChild(link);
                }
            });
        },

        executeNewScripts: function(newDoc) {
            // Execute inline scripts from the new page's body
            var scripts = newDoc.body.querySelectorAll('script:not([src])');
            for (var i = 0; i < scripts.length; i++) {
                var content = scripts[i].textContent;
                if (content && content.trim()) {
                    // Skip Amplitude re-init scripts
                    if (content.indexOf('Amplitude.init') !== -1) continue;
                    try {
                        $.globalEval(content);
                    } catch(e) {
                        console.log('PJAX script exec error:', e);
                    }
                }
            }
        },

        reinitScripts: function() {
            // Re-initialize Elementor frontend
            if (window.elementorFrontend) {
                try {
                    // Re-run element handlers on the new content
                    if (window.elementorFrontend.elementsHandler) {
                        window.elementorFrontend.elementsHandler.runReadyTrigger(document);
                    }

                    // Re-init Elementor's sticky functionality on the footer
                    // Elementor sticky uses an internal handler that watches scroll
                    var stickyEls = document.querySelectorAll('[data-settings*="sticky"]');
                    stickyEls.forEach(function(el) {
                        // Force Elementor to re-detect this element by triggering resize
                        el.style.display = 'none';
                        el.offsetHeight; // force reflow
                        el.style.display = '';
                    });
                } catch(e) {
                    console.log('Elementor reinit:', e);
                }
            }

            // Trigger resize to force sticky recalculations
            window.dispatchEvent(new Event('resize'));

            // Small delay then resize again (some sticky handlers are async)
            setTimeout(function() {
                window.dispatchEvent(new Event('resize'));
                window.dispatchEvent(new Event('scroll'));
            }, 100);

            $(document).trigger('pjax:complete');
            document.dispatchEvent(new Event('pjax:load'));

            // Re-bind AmplitudeJS elements on new content
            if (typeof Amplitude !== 'undefined') {
                try { Amplitude.bindNewElements(); } catch(e) {}
            }

            // Re-initialize DataTables
            if ($.fn.DataTable) {
                $('table.dataTable, .datatable').each(function() {
                    if (!$.fn.DataTable.isDataTable(this)) {
                        try { $(this).DataTable(); } catch(e) {}
                    }
                });
            }

            // Re-initialize Magnific Popup
            if ($.fn.magnificPopup) {
                $('.popup-link, [data-mfp-src]').each(function() {
                    try { $(this).magnificPopup({ type: 'inline' }); } catch(e) {}
                });
            }
        },

        savePlayerState: function() {
            try {
                if (typeof Amplitude === 'undefined') return;

                var songs = Amplitude.getSongs();
                if (songs && songs.length > 0) {
                    localStorage.setItem('fml_queue', JSON.stringify(songs));
                    localStorage.setItem('songList', JSON.stringify(Amplitude.getActiveSongMetadata()));
                    localStorage.setItem('songIndex', Amplitude.getActiveIndex());
                    localStorage.setItem('timeUpdate', Amplitude.getSongPlayedSeconds());
                    localStorage.setItem('percentage', Amplitude.getSongPlayedPercentage());
                    localStorage.setItem('playing', window.FMLAudioData ? window.FMLAudioData.isPlaying : false);
                }
            } catch(e) {
                console.log('Error saving player state:', e);
            }
        },

        showLoading: function() {
            var $loader = $('#loading-screen');
            if ($loader.length) {
                $loader.css('display', 'block');
            }
        },

        hideLoading: function() {
            var $loader = $('#loading-screen');
            if ($loader.length) {
                $loader.css('display', 'none');
            }
        }
    };

    $(function() {
        PjaxNav.init();
    });

    window.PjaxNav = PjaxNav;

})(jQuery);
