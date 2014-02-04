<?php

require('workflows.php');
$w = new Workflows();

$in = urlencode($argv[1]);

$results = $w->request('http://10.0.1.6:32400/search?type=1&query='.$in );

$xml = simplexml_load_string( $results );

foreach( $xml->Video as $vid ):

	// $ch = curl_init('http://10.0.1.6:32400'.$thumb);
	// $fname = 'thumbs/'.$vid->Media['id'].'.jpg';
	// curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	// $ret = curl_exec($ch);

	// file_put_contents($fname, $ret);

	if ( $vid['type'] == "movie" ):
		$w->result( 'plex:'.$vid['title'], $vid->Media->Part['file'], $vid['title']. " (" .$vid['year']. ")" , "Rated: ".$vid['contentRating']. " // Summary: ".$vid['summary'], 'movie.png', 'yes' );
	else:
		$w->result( 'plex:'.$vid['title'], $vid->Media->Part['file'], $vid['grandparentTitle']." - ".$vid['title']. " (" .$vid['year']. ")" , "Rated: ".$vid['contentRating']. " // Summary: ".$vid['summary'], 'tv.png', 'yes', $vid['title'] );
	endif;
endforeach;

echo $w->toxml();