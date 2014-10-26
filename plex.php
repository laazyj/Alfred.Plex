<?php
require('workflows.php');

function startsWith($haystack, $needle)
{
     $length = strlen($needle);
     return (substr($haystack, 0, $length) === $needle);
}

function endsWith($haystack, $needle)
{
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    return (substr($haystack, -$length) === $needle);
}

/**
* Description: 	Class for controlling Plex Media Server.
* Author: 		Jason Duffett (@laazyj)
* Revised: 		26/10/2014
*/
class Plex {

	const PLIST = "com.alfredapp.plex.plist";
	private $server;
	private $port;
	private $host;
	private $uuid;
	private $client;
	/**
	 * @var WorkFlows 
	 */
	private $workflow;
	public $debug = false;

	/**
	 * @param $workflow - optional existing workflow to use.
	 * @return none
	 */
	public function __construct( Workflows $workflow = null )
	{
		if (!$workflow) { $workflow = new Workflows(); }
		$this->workflow = $workflow;
	}

	/**
	 * @param none
	 * @return false if Alfred.Plex has not yet been configured
	 */
	public function configured( $ignoreClient = false ) {
		$this->server = exec("defaults read ".self::PLIST." server");
		$this->port = exec("defaults read ".self::PLIST." port");
		$this->uuid = exec("defaults read ".self::PLIST." serverUUID");
		$this->client = exec("defaults read ".self::PLIST." client");

		$this->host = "http://".$this->server.":".$this->port;

		if (!$this->server || !$this->port || !$this->uuid) {
			$this->workflow->result(null, null, "Not configured", "No server configuration was found. Please configure your server using the 'plex server' keyword.", "icon.png", "no");
			return false;
		} else if (!$this->client && !$ignoreClient) {
			$this->workflow->result(null, null, "Not configured", "No client selected. Please select a client using the 'plex clients' keyword.", "icon.png", "no");
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Set the Plex Server host and port.
	 * @return Confirmation or error message.
	 */
	public function server( $server, $port = null ) {
		// Default port number
		if (!$port) { $port = 32400; }

		$xml = $this->workflow->request("http://$server:$port/servers");
		
		$use_errors = libxml_use_internal_errors(true);
		$xml = simplexml_load_string( $xml );
		libxml_clear_errors();
		libxml_use_internal_errors($use_errors);
		if (!$xml) {
			return "ERROR: Invalid response from http://$server:$port";
		}

		foreach( $xml->Server as $srv ) {
			$address = $srv['address'];
			$machineId = $srv['machineIdentifier'];
			// Default to the first UUID returned from the server in case the server name is not found (i.e. localhost)
			if ( $address == $server || $uuid === NULL ):
				$uuid = $machineId;
			endif;
		}

		if ($uuid === NULL) {
			return "ERROR: No Plex server was found at http://$server:$port";
		} else {
			exec("defaults write ".self::PLIST." server '$server'");
			exec("defaults write ".self::PLIST." port '$port'");
			exec("defaults write ".self::PLIST." serverUUID '$uuid'");
			return "Plex server configuration saved.";
		}
	}

	/**
	 * Return list of available Plex clients from configured server.
	 * @return Xml list of available Plex clients.
	 */
	public function clients() {
		if (!$this->configured(true)) { return $this->workflow->toxml(); }

		$url = $this->host."/clients";
		$clients = $this->workflow->request( $url );
		$clients = simplexml_load_string( $clients );
		foreach( $clients->Server as $server ) {
			$this->workflow->result( $server['name'], $server['address'], $server['name'], $server['address'], 'icon.png' );
		}
		$this->workflow->result( 'plex:web', 'web', 'Web', 'Plex server web client', 'icon.png' );
		
		return $this->workflow->toxml();
	}

	/**
	 * Play the specified file in the configured client
	 * @param $file - file to be played
	 * @return Success or error message.
	 */
	public function play($file) {
		if (!$this->configured()) { return $this->workflow->toxml(); }

		$arg = explode(":", $file, 2);
		if ($arg[0] == "web") {
			exec('open "'.$arg[1].'"');
		} else {
			$url = $this->host."/system/players/".$this->client."/application/playFile?path=".$this->host.$arg[1];
			echo "$url";
			$this->workflow->request($url);
		}
		return "Playing '$file'";
	}

 	/**
 	 * Pause the current playing media in Plex
 	 * @return Confirmation or error message.
 	 */
	public function pause() {
		if (!$this->configured()) { return $this->workflow->toxml(); }
		if ( $this->client == 'web' ) { return "Pausing the web player is unsupported."; }

		$url = $this->host."/system/players/".$this->client."/playback/pause";
		$this->workflow->request( $url );
		return "Plex paused.";
	}

	/**
	 * Resume playing the current media in Plex
	 * @return Confirmation or error message.
	 */
	public function resume() {
		if (!$this->configured()) { return $this->workflow->toxml(); }
		if ( $this->client == 'web' ) { return "Resuming the web player is unsupported."; }

		$url = $this->host."/system/players/".$this->client."/playback/play";
		$this->workflow->request( $url );
		return "Resuming playback...";
	}

	/**
	 * Stop playing the current media in Plex
	 * @return Confirmation or error message.
	 */
	public function stop() {
		if (!$this->configured()) { return $this->workflow->toxml(); }
		if ( $this->client == 'web' ) { return "Stopping the web player is unsupported."; }

		$url = $this->host."/system/players/".$his->client."/playback/stop";
		$this->workflow->request( $url );
		return "Plex stopped.";
	}

	/**
	 * Search Plex Server for the query
	 * @param $query - search phrase
	 * @return Xml results of search
	 */
	public function search( $query=null ) {
		if (!$this->configured()) { return $this->workflow->toxml(); }

		$query = urlencode( $query );
	    $url = $this->host."/search?type=1&query=".$query;
		$results = $this->workflow->request($url);
		$xml = simplexml_load_string( $results );
		$videoCount = count( $xml->Video );
		$dirCount = count( $xml->Directory );

		if ( $dirCount > 0 ):
			foreach( $xml->Directory as $section ):
				if ( $section['secondary'] == 1 || $section['key'] == 'folder' || $section['search'] == '1' ):
					continue;
				endif;

				if ( $section['type'] == 'movie' ):
					$icon = 'movie.png';
				elseif ( $section['type'] == 'show' ):
					$icon = 'tv.png';
				else:
					$icon = 'icon.png';
				endif;

				$file = $this->getDirectoryLink($section);
				$this->workflow->result( 'plex:'.$section['title'], $file, $section['title'], $section->Location['path'], $icon, 'yes', $section['title'] );
			endforeach;
		endif;

		if ( $videoCount > 0):
			foreach( $xml->Video as $vid ):
				$file = $this->getVideoFile($vid);

				if ( $vid['type'] == "movie" ):
					$this->workflow->result( 'plex:'.$vid['title'], $file, $vid['title']. " (" .$vid['year']. ")" , "Rated: ".$vid['contentRating']. " // Summary: ".$vid['summary'], 'movie.png', 'yes' );
				else:
					$this->workflow->result( 'plex:'.$vid['title'], $file, $vid['grandparentTitle']." - ".$vid['title']. " (" .$vid['year']. ")" , "Rated: ".$vid['contentRating']. " // Summary: ".$vid['summary'], 'tv.png', 'yes', $vid['title'] );
				endif;
			endforeach;
		else:
			$this->workflow->result(null, null, "No match found", "No matching items found on Plex server ".$this->host, "icon.png", "no" );
		endif;

		return $this->workflow->toxml();
	}

	/**
	 * Browse the media in Plex
	 * @param $context - Starting context to browse from, defaults to '/library/sections'
     * @return Xml results of media found
     */
	public function browse( $context = null ) {
		if (!$this->configured()) { return $this->workflow->toxml(); }
	
		$BASE_CONTEXT = "/library/sections";
		if (!$context || strpos( $context, '/') !== 0) { $context = $BASE_CONTEXT; }
		
		if ($this->debug) { echo "Browsing at context '$context'\n"; }
		$url = $this->host."$context";

		//show all sections
		$sections = $this->workflow->request( $url );
		$sections = simplexml_load_string( $sections );
		$directoryCount = count($sections->Directory);
		$videoCount = count($sections->Video);
		if ($this->debug) { echo "Found $directoryCount directories, $videoCount videos.\n"; }

		if ( $directoryCount > 0 ) {
			if ( $context != $BASE_CONTEXT ) {
				switch( $sections['viewGroup'] ):
					case "season": 
						$back = $BASE_CONTEXT."/".$sections['librarySectionID']."/all"; 
						continue;
					case "secondary": 
						$back = $BASE_CONTEXT; 
						continue;
					default: {
						$back = explode( "/", $context );
						array_pop( $back );
						$back = implode( "/", $back );
					}
				endswitch;
				$this->workflow->result( null, $back, 'Go Back', 'Go back to the previous folder', 'icon.png' );
			}

			foreach( $sections->Directory as $section ):
				if ( $section['type'] == 'movie' ) {
					$icon = 'movie.png';
				} elseif ( $section['type'] == 'show' ) {
					$icon = 'tv.png';
				} else {
					$icon = 'icon.png';
				}

				$key = $section['key'];
				if ( strpos( $key, '/' ) !== false ) {
					$path = "$key";
				} else {
					$path = "$context/$key";
				}

				if ( $section['secondary'] == 1 || $section['key'] == 'folder' || $section['search'] == '1' ) { continue; }

				if ($this->debug) { echo "Found section: $path\n  Title: ".$section['title']."\n  Location: ".$section->Location['path']."\n"; }
				$this->workflow->result( null, $path, $section['title'], $section->Location['path'], $icon, 'yes', $section['title'] );
			endforeach;
		}

		if ( $videoCount > 0 ) {
			if ( $context != $BASE_CONTEXT ) {
				$back = explode( '/', $context );

				if ( end($back) == 'unwatched' || 
						end($back) == 'newest' || 
						end($back) == 'recentlyAdded' || 
						end($back) == 'recentlyViewed' ||
						end($back) == 'onDeck' ) {
					$back = explode( '/', $context );
					array_pop( $back );
					$back = implode( '/', $back );
				} elseif ( $sections['viewGroup'] == 'episode' ) { // at season level
					$temp = explode( '/', $sections['art'] );
					array_pop( $temp );
					array_pop( $temp );
					array_push( $temp, 'children' );
					$back = implode( '/', $temp );
				} elseif ( $sections['viewGroup'] == 'movie' ) {
					$back = explode( '/', $context );
					array_pop( $back );
					$back = implode( '/', $back );
				}

				$this->workflow->result( null, $back, 'Go Back', 'Go back to the previous folder', 'icon.png' );
			}

			foreach( $sections->Video as $vid ):
				$file = $this->getVideoFile($vid);

				if ( $vid['type'] == "movie" ) {
					$this->workflow->result( null, $file, $vid['title']. " (" .$vid['year']. ")" , "Rated: ".$vid['contentRating']. " // Summary: ".$vid['summary'], 'movie.png', 'yes' );
				} else {
					$show = ( $sections['grandparentTitle'] ) ? $sections['grandparentTitle'] : $vid['grandparentTitle'];
					$this->workflow->result( null, $file, $show. ' - ' .$vid['title']. " (" .$vid['year']. ")", $vid['summary'], 'tv.png', 'yes', $vid['title'] );
				}
			endforeach;
		}

		return $this->workflow->toxml();
	}

	private function getDirectoryLink($directory) {
		$key = $directory['key'];
		if (endsWith($key, "/children")) {
			$key = substr($key, 0, strlen($key)-9);
		}
		return "web:".$this->host."/web/index.html#!/server/".$this->uuid."/details/".urlencode( $key );
	}

	private function getVideoFile($video) {
		if ($this->client == 'web') {
			return "web:".$this->host."/web/index.html#!/server/".$this->uuid."/details/".urlencode( $video['key'] );
		} else {
			return 'player:'.$video->Media->Part['key'];
		}
	}

}