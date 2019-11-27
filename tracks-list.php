<?php

// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;


class WPSpotifyTracks {
	
	
	// Properties
	// ------------------------------
	private $client_id;
	private $client_secret;
	private $defaults = [
		// api settings
		'offset' => 0,
		'limit' => 50,
		'market' => 'MX',
		// internal settings
		'recursive' => true,
		'only_unavailable' => false,
		'output' => 'rows',
	];
	
	
	// Constructor
	// ------------------------------
	public function __construct() {
		
		// Get credentials
		$user_settings = get_option( 'spotify_api' );
		if( empty( $user_settings['client_id'] ) || empty( $user_settings['client_secret'] ) ) {
			return;
		}
		$this->client_id = $user_settings['client_id'];
		$this->client_secret = $user_settings['client_secret'];
		
		// Register hooks
		add_shortcode( 'spotify_tracks', [$this, 'get_shortcode'] );
		add_action( 'admin_post_spotify_tracks', [$this, 'output_tracks_page'] );
	}
	
	
	// Helper functions
	// ------------------------------
	private function get_action_url( $settings=[] ) {
		$url = admin_url( 'admin-post.php' );
		$settings['action'] = 'spotify_tracks';
		return add_query_arg( $settings, $url );
	}
	
	
	// Get session/api
	// ------------------------------
	private function load_api_files() {
		// http://jwilsson.github.io/spotify-web-api-php/method-reference/spotifywebapi.html
		require_once 'spotify-web-api/Session.php';
		require_once 'spotify-web-api/Request.php';
		require_once 'spotify-web-api/SpotifyWebAPI.php';
		require_once 'spotify-web-api/SpotifyWebAPIException.php';
	}
	private function get_session() {
		static $session = false;
		if( !$session ) {
			$this->load_api_files();
			$session = new SpotifyWebAPI\Session( $this->client_id, $this->client_secret, $this->get_action_url() );
		}
		return $session;
	}
	private function get_auth_code() {
		$auth_code = !empty( $_REQUEST['code'] ) ? $_REQUEST['code'] : false;
		if( $auth_code ) {
			return $auth_code;
		} else {
			$this->request_auth_code();
		}
	}
	private function request_auth_code() {
		$settings = shortcode_atts( $this->defaults, $_REQUEST );
		$session = $this->get_session();
		$scope = [
			'playlist-read-private',
			'playlist-read-collaborative',
			'playlist-modify-public',
			'playlist-modify-private',
			'streaming',
			'user-follow-modify',
			'user-follow-read',
			'user-library-read',
			'user-library-modify',
			'user-read-private',
			'user-read-birthdate',
			'user-read-email',
		];
		header( 'Location: '.$session->getAuthorizeUrl( ['scope' => $scope, 'state'=>urlencode(json_encode($settings))] ) );
		die();
	}
	private function get_api() {
		static $api = false;
		if( !$api ) {
			$auth_code = $this->get_auth_code();
			$session = $this->get_session();
			$session->requestAccessToken( $auth_code );
			$api = new SpotifyWebAPI\SpotifyWebAPI();
			$api->setAccessToken( $session->getAccessToken() );
		}
		return $api;
	}
	private function get_state() {
		$state = !empty( $_REQUEST['state'] ) ? json_decode( urldecode( $_REQUEST['state'] ) ) : [];
		return shortcode_atts( $this->defaults, $state );
	}
	
	
	// Shortcode
	// ------------------------------
	public function get_shortcode( $atts=[], $content='' ) {
		$atts = shortcode_atts( $this->defaults, $atts, 'spotify_tracks' );
		$content = $content ? $content : 'Spotify Tracks';
		return sprintf( '<a href="%s">%s</a>', esc_url( $this->get_action_url( $atts ) ), esc_html( $content ) );
	}
	
	
	// Output Tracks
	// ------------------------------
	public function output_tracks_page() {
		$api = $this->get_api();
		$settings = $this->get_state();
		header( 'Content-Type: text/plain' );
		$this->output_tracks( $api, $settings );
		die();
	}
	private function output_tracks( $api, $settings ) {
		$tracks = $api->getMySavedTracks( $settings );
		if( empty( $tracks->items ) ) {
			return;
		}
		array_walk( $tracks->items, [$this, 'output_track'], $settings );
		if( $settings['recursive'] && !empty( $tracks->next ) ) {
			$settings['offset'] += $settings['limit'];
			$this->output_tracks( $api, $settings );
		}
	}
	private function output_track( $track, $i, $settings=[] ) {
		if( $settings['only_unavailable'] && $track->track->is_playable ) {
			return;
		}
		switch( $settings['output'] ) {
			case 'links':
				$this->output_track_link( $track );
				break;
			default:
				$this->output_track_row( $track );
				break;
		}
	}
	private function output_track_link( $track ) {
		$url = $track->track->external_urls->spotify;
		echo "{$url}\n";
	}
	private function output_track_row( $track ) {
		$name = $track->track->name;
		$artists = array_map( function($artist){ return $artist->name; }, $track->track->artists );
		$artists = implode( ', ', $artists );
		$album = $track->track->album->name;
		echo "{$name}\t{$artists}\t{$album}\n";
	}
}
new WPSpotifyTracks();
