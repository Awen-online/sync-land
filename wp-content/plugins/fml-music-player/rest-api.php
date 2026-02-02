<?php

//playlist
add_action( 'rest_api_init', function () {
  register_rest_route( 'fml-music-player/v1', '/a/(?P<id>\d+)', array(
    'methods' => 'GET',
    'callback' => 'my_awesome_func',
  ) );

} );