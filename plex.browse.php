<?php

require('workflows.php');
$w = new Workflows();

// $in = trim( $argv[1] );

$server = exec('defaults read com.alfredapp.plex.plist server');
$port = exec('defaults read com.alfredapp.plex.plist port');
$uuid = exec('defaults read com.alfredapp.plex.plist serverUUID');
$client = exec('defaults read com.alfredapp.plex.plist client');
$context = exec('defaults read com.alfredapp.plex.plist context');

// server has been configured
if ( $server && $port ):


		$url = "http://$server:$port$context";

		//show all sections
		$sections = $w->request( $url );
		$sections = simplexml_load_string( $sections );

		if ( count( $sections->Directory ) > 0 ):

			if ( $context != '/library/sections' ):
				switch( $sections['viewGroup'] ):
					case "season": $back = "/library/sections/".$sections['librarySectionID']."/all"; continue;
					case "secondary": $back = "/library/sections"; continue;
					default: {
						$back = explode( "/", $context );
						array_pop( $back );
						$back = implode( "/", $back );
					}
				endswitch;
				$w->result( null, $back, 'Go Back', 'Go back to the previous folder', 'icon.png' );
			endif;

			foreach( $sections->Directory as $section ):
				if ( $section['type'] == 'movie' ):
					$icon = 'movie.png';
				elseif ( $section['type'] == 'show' ):
					$icon = 'tv.png';
				else:
					$icon = 'icon.png';
				endif;

				$key = $section['key'];
				if ( strpos( $key, '/' ) !== false ):
					$path = "$key";
				else:
					$path = "$context/$key";
				endif;

				if ( $section['secondary'] == 1 || $section['key'] == 'folder' || $section['search'] == '1' ):
					continue;
				else:
					$w->result( null, $path, $section['title'], $section->Location['path'], $icon, 'yes', $section['title'] );
				endif;
			endforeach;

		endif;

		if ( count( $sections->Video ) > 0 ):

			if ( $context != '/library/sections' ):

				$back = explode( '/', $context );

				if ( end($back) == 'unwatched' || 
						end($back) == 'newest' || 
						end($back) == 'recentlyAdded' || 
						end($back) == 'recentlyViewed' ||
						end($back) == 'onDeck' ):
					$back = explode( '/', $context );
					array_pop( $back );
					$back = implode( '/', $back );
				elseif ( $sections['viewGroup'] == 'episode' ): // at season level
					$temp = explode( '/', $sections['art'] );
					array_pop( $temp );
					array_pop( $temp );
					array_push( $temp, 'children' );
					$back = implode( '/', $temp );
				elseif ( $sections['viewGroup'] == 'movie' ):
					$back = explode( '/', $context );
					array_pop( $back );
					$back = implode( '/', $back );
				endif;

				$w->result( null, $back, 'Go Back', 'Go back to the previous folder', 'icon.png' );
			endif;

			foreach( $sections->Video as $vid ):
				if ( $client == 'web' ):
					$file = "web:http://$server:$port/web/index.html#!/servers/$uuid/sections/".$vid['librarySectionID']."/player/".$vid['ratingKey'];
				else:
					$file = 'player:'.$vid->Media->Part['key'];
				endif;

				if ( $vid['type'] == "movie" ):
					$w->result( null, $file, $vid['title']. " (" .$vid['year']. ")" , "Rated: ".$vid['contentRating']. " // Summary: ".$vid['summary'], 'movie.png', 'yes' );
				else:
					$show = ( $sections['grandparentTitle'] ) ? $sections['grandparentTitle'] : $vid['grandparentTitle'];
					$w->result( null, $file, $show. ' - ' .$vid['title']. " (" .$vid['year']. ")", $vid['summary'], 'tv.png', 'yes', $vid['title'] );
				endif;
			endforeach;

		endif;

// server has not yet been configured
else:

	$w->result(null, null, 'three', 'four', 'icon.png');

endif;

echo $w->toxml();