<?php declare(strict_types = 1);

/**
 * Plugin Name: Spotify Top Tracks
 * Description: Creates a custom block to be used with Remote Data Blocks in order to retrieve country information.
 * Author: WPVIP
 * Author URI: https://remotedatablocks.com/
 * Text Domain: spotify-top-tracks
 * Version: 1.0.0
 * Requires Plugins: remote-data-blocks
 */

namespace RemoteDataBlocks\Example\Countries;

use RemoteDataBlocks\Config\DataSource\HttpDataSource;
use RemoteDataBlocks\Config\Query\HttpQuery;
use WP_Error;

function get_spotify_access_token( string $client_id, string $client_secret, bool $no_cache = false ): WP_Error|string {
	// Check for cached token first
	$cache_key = 'spotify_auth_token';
	
	if ( ! $no_cache ) {
		$cached_token = get_transient( $cache_key );
		if ( false !== $cached_token ) {
			return $cached_token;
		}
	}
	
	// Make request to Spotify API for token
	$response = wp_remote_post(
		'https://accounts.spotify.com/api/token',
		[
			'headers' => [
				'Content-Type' => 'application/x-www-form-urlencoded',
			],
			'body' => [
				'grant_type' => 'client_credentials',
				'client_id' => $client_id,
				'client_secret' => $client_secret,
			],
		]
	);

	if ( is_wp_error( $response ) ) {
		return new WP_Error(
			'spotify_auth_error',
			__( 'Failed to retrieve access token', 'remote-data-blocks' )
		);
	}

	$response_body = wp_remote_retrieve_body( $response );
	$response_data = json_decode( $response_body, true );

	if ( ! isset( $response_data['access_token'] ) ) {
		return new WP_Error(
			'spotify_auth_error',
			__( 'Invalid response from Spotify Auth', 'remote-data-blocks' )
		);
	}

	$token = $response_data['access_token'];
	
	// Cache the token for 30 minutes
	set_transient(
		$cache_key,
		$token,
		3000 // 50 minutes
	);

	return $token;
}

function register_spotify_artists_tracks_block(): void {
	$client_id = '81c45aad51d44ec6bda851e4aa6db611';
	$client_secret = '01f78fc45bc0486da9b3c7a506948aff';
	$artist_id = '06HL4z0CvFAxyc27GXpf02';

	$spotify_data_source = HttpDataSource::from_array( [
		'request_headers' => function () use ( $client_id, $client_secret ): array {
			$token = get_spotify_access_token( $client_id, $client_secret );
			if ( is_wp_error( $token ) ) {
				return [];
			}
			return [
				'Authorization' => 'Bearer ' . $token,
			];
		},
		'service_config' => [
			'__version' => 1,
			'display_name' => 'Spotify',
			'endpoint' => 'https://api.spotify.com/v1',
		],
	] );
	
	// Define query to get artist's albums
	$get_artist_albums_query = HttpQuery::from_array( [
		'display_name' => 'Get Artist Top Tracks',
		'data_source' => $spotify_data_source,
		'endpoint' => function( array $input_variables ) use ( $artist_id, $spotify_data_source ): string {
			return $spotify_data_source->get_endpoint() . '/artists/' . $artist_id . '/top-tracks?market=US';
		},
		'output_schema' => [
			'is_collection' => true,
			'path' => '$.tracks[*]',
			'type' => [
				'id' => [
					'name' => 'Track ID',
					'path' => '$.id',
					'type' => 'id',
				],
				'name' => [
					'name' => 'Track Name',
					'path' => '$.name',
					'type' => 'string',
				],
				'duration_ms' => [
					'name' => 'Duration (ms)',
					'path' => '$.duration_ms',
					'type' => 'number',
				],
				'popularity' => [
					'name' => 'Popularity',
					'path' => '$.popularity',
					'type' => 'number',
				],
				'album_name' => [
					'name' => 'Album Name',
					'path' => '$.album.name',
					'type' => 'string',
				],
				'album_image' => [
					'name' => 'Album Image',
					'path' => '$.album.images[0].url',
					'type' => 'image_url',
				],
			],
		],
	] );
	
	// Register the Remote Data Block
	register_remote_data_block( [
		'title' => 'Spotify Artist Top Tracks',
		'render_query' => [
			'query' => $get_artist_albums_query,
		],
	] );
}

// Hook to initialize the block
add_action( 'init', __NAMESPACE__ . '\\register_spotify_artists_tracks_block', 10, 0 );
