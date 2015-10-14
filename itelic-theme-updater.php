<?php

/**
 * Class ITELIC_Theme_Updater
 */
class ITELIC_Theme_Updater {

	/**
	 * Activate the site.
	 */
	const EP_ACTIVATE = 'activate';

	/**
	 * Deactivate the site.
	 */
	const EP_DEACTIVATE = 'deactivate';

	/**
	 * Returns info about the license key.
	 */
	const EP_INFO = 'info';

	/**
	 * Get the latest version.
	 */
	const EP_VERSION = 'version';

	/**
	 * Download the plugin file.
	 */
	const EP_DOWNLOAD = 'download';

	/**
	 * Return info about the product.
	 */
	const EP_PRODUCT = 'product';

	/**
	 * GET method.
	 */
	const METHOD_GET = 'GET';

	/**
	 * POST method.
	 */
	const METHOD_POST = 'POST';

	/**
	 * @var string
	 */
	private $store_url;

	/**
	 * @var int
	 */
	private $product_id;

	/**
	 * @var WP_Theme
	 */
	private $theme;

	/**
	 * @var string
	 */
	private $version;

	/**
	 * @var string
	 */
	private $key = '';

	/**
	 * @var int
	 */
	private $id = 0;

	/**
	 * Constructor.
	 *
	 * @param string $store_url  This is the URL to your store.
	 * @param int    $product_id This is the product ID of your plugin.
	 * @param array  $args       Additional args.
	 *
	 * @throws Exception
	 */
	public function __construct( $store_url, $product_id, $args = array() ) {
		$this->store_url  = trailingslashit( $store_url );
		$this->product_id = $product_id;
		$this->theme      = wp_get_theme();

		if ( empty( $args['version'] ) ) {
			$this->version = $this->theme->get( 'Version' );
		} else {
			$this->version = $args['version'];
		}

		if ( $args['key'] ) {
			$this->key = $args['key'];
		}

		if ( $args['activation_id'] ) {
			$this->id = absint( $args['activation_id'] );
		}

		add_filter( 'pre_set_site_transient_update_themes', array( $this, 'check_for_update' ) );
		add_action( "in_theme_update_message-{$this->theme->get_stylesheet()}", array( $this, 'show_upgrade_notice_on_list' ), 10, 2 );
	}

	/**
	 * Check for a plugin update.
	 *
	 * @since 1.0
	 *
	 * @param object $transient
	 *
	 * @return object
	 */
	public function check_for_update( $transient ) {

		if ( empty( $transient->checked ) || empty( $this->key ) ) {
			return $transient;
		}

		try {
			$info = $this->get_latest_version( $this->key );
		}
		catch ( Exception $e ) {

			return $transient;
		}

		if ( ! is_wp_error( $info ) && version_compare( $info->version, $this->version, '>' ) ) {

			$info->upgrade_notice = 'upgrade';

			$stylesheet = $this->theme->get_stylesheet();

			$transient->response[ $stylesheet ] = array(
				'new_version' => $info->version,
				'package'     => $info->package,
				'slug'        => $stylesheet,
				'theme'       => $stylesheet,
				'url'         => add_query_arg( 'ID', $this->product_id, $this->generate_endpoint_url( 'changelog' ) )
			);

			if ( ! empty( $info->upgrade_notice ) ) {
				$transient->response[$stylesheet]['upgrade_notice'] = $info->upgrade_notice;
			}
		}

		return $transient;
	}

	/**
	 * Show the upgrade notice on the plugin list page.
	 *
	 * @since 1.0
	 *
	 * @param array $theme_data
	 * @param array $r
	 */
	public function show_upgrade_notice_on_list( $theme_data, $r ) {

		if ( ! empty( $theme_data['upgrade_notice'] ) ) {
			echo '&nbsp;' . $theme_data['upgrade_notice'];
		}
	}

	/**
	 * Activate a license key for this site.
	 *
	 * @param string $key   License Key
	 * @param string $track Either 'stable' or 'pre-release'
	 *
	 * @return int|WP_Error Activation Record ID on success, WP_Error object on
	 *                      failure.
	 */
	public function activate( $key, $track = 'stable' ) {

		$params = array(
			'body' => array(
				'location' => site_url(),
				'version'  => $this->version,
				'track'    => $track
			)
		);

		$response = $this->call_api( self::EP_ACTIVATE, self::METHOD_POST, $key, $this->id, $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		} else {
			return $response->id;
		}
	}

	/**
	 * Deactivate the license key on this site.
	 *
	 * @param string     $key License Key
	 * @param int|string $id  ID returned from activate method.
	 *
	 * @return boolean|WP_Error Boolean True on success, WP_Error object on
	 *                          failure.
	 */
	public function deactivate( $key, $id ) {

		$params = array(
			'body' => array(
				'id' => (int) $id
			)
		);

		$response = $this->call_api( self::EP_DEACTIVATE, self::METHOD_POST, $key, $id, $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		} else {
			return true;
		}
	}

	/**
	 * Get the latest version of the plugin.
	 *
	 * @param string $key
	 *
	 * @return object|WP_Error
	 *
	 * @throws Exception
	 */
	public function get_latest_version( $key ) {

		if ( ! $this->id ) {
			throw new Exception( "License key must be activated before retrieving the latest version." );
		}

		$params = array(
			'installed_version' => $this->version
		);

		$response = $this->call_api( self::EP_VERSION, self::METHOD_GET, $key, $this->id, array(), $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		} elseif ( isset( $response->list->{$this->product_id} ) ) {
			return $response->list->{$this->product_id};
		} else {
			throw new Exception( "Product ID and License Key don't match." );
		}
	}

	/**
	 * Get info about a license key.
	 *
	 * @since 1.0
	 *
	 * @param string $key
	 *
	 * @return object|WP_Error
	 */
	public function get_info( $key ) {
		return $this->call_api( self::EP_INFO, self::METHOD_GET, $key );
	}

	/**
	 * Get info about the product this license key connects to.
	 *
	 * @since 1.0
	 *
	 * @param string $key
	 * @param int    $id
	 *
	 * @return object|WP_Error
	 * @throws Exception
	 */
	public function get_product_info( $key, $id ) {
		return $this->call_api( self::EP_PRODUCT, self::METHOD_GET, $key, $id );
	}

	/**
	 * Make a call to the API.
	 *
	 * This method is suitable for client consumption,
	 * but the convenience methods provided are preferred.
	 *
	 * @param string $endpoint
	 * @param string $method
	 * @param string $key
	 * @param int    $id
	 * @param array  $http_args
	 * @param array  $query_params
	 *
	 * @return object|WP_Error Decoded JSON on success, WP_Error object on
	 *                         error.
	 *
	 * @throws Exception If invalid HTTP method.
	 */
	public function call_api( $endpoint, $method, $key = '', $id = 0, $http_args = array(), $query_params = array() ) {

		$args = array(
			'headers' => array()
		);
		$args = wp_parse_args( $http_args, $args );

		if ( $key ) {
			$args['headers']['Authorization'] = $this->generate_basic_auth( $key, $id );
		}

		$url = $this->generate_endpoint_url( $endpoint, $query_params );

		if ( $method == self::METHOD_GET ) {
			$response = wp_remote_get( $url, $args );
		} elseif ( $method == self::METHOD_POST ) {
			$response = wp_remote_post( $url, $args );
		} else {
			throw new Exception( "Invalid HTTP Method" );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_body = wp_remote_retrieve_body( $response );

		$json = json_decode( $response_body );

		if ( ! $json->success ) {

			if ( ! $json ) {
				$json = (object) array(
					'error' => array(
						'code'    => $response['code'],
						'message' => $response['message']
					)
				);
			}

			return $this->response_to_error( $json );
		} else {
			return $json->body;
		}
	}

	/**
	 * Convert the JSON decoded response to an error object.
	 *
	 * @param stdClass $response
	 *
	 * @return WP_Error
	 *
	 * @throws Exception If response is not an error. To check for an error
	 *                   look at the 'success' property.
	 */
	protected function response_to_error( stdClass $response ) {

		if ( $response->success ) {
			throw new Exception( "Response object is not an error." );
		}

		return new WP_Error( $response->error->code, $response->error->message );
	}

	/**
	 * Generate the endpoint URl.
	 *
	 * @param string $endpoint
	 * @param array  $query_params
	 *
	 * @return string
	 */
	protected function generate_endpoint_url( $endpoint, $query_params = array() ) {

		$base = $this->store_url . 'itelic-api';

		$url = "$base/$endpoint/";

		if ( ! empty( $query_params ) ) {
			$url = add_query_arg( $query_params, $url );
		}

		return $url;
	}

	/**
	 * Generate a basic auth header based on the license key.
	 *
	 * @param string     $key
	 * @param int|string $activation
	 *
	 * @return string
	 */
	protected function generate_basic_auth( $key, $activation = '' ) {
		return 'Basic ' . base64_encode( $key . ':' . $activation );
	}

}