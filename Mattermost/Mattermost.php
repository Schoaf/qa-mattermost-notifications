<?php

// Version v1.2: http://git.io/13fxXw

namespace Mattermost;

/**
 * Library for interacting with the Mattermost.
 *
 * @see ...
 */
class Mattermost {

  /**
   * HTTP response codes from API
   *
   * @see ...
   */
  const STATUS_BAD_RESPONSE = -1; // Not an HTTP response code
  const STATUS_OK = 200;
  const STATUS_BAD_REQUEST = 400;
  const STATUS_UNAUTHORIZED = 401;
  const STATUS_FORBIDDEN = 403;
  const STATUS_NOT_FOUND = 404;
  const STATUS_NOT_ACCEPTABLE = 406;
  const STATUS_INTERNAL_SERVER_ERROR = 500;
  const STATUS_SERVICE_UNAVAILABLE = 503;

  /**
   * Colors for rooms/message
   */
  const COLOR_GREEN = '#289E00';

  /**
   * API versions
   */
  const VERSION_1 = 'v1';

  private $api_target;
  private $auth_token;
  private $verify_ssl = true;
  private $proxy;
  private $webhook_url;
  
    /**
   * Creates a new API interaction object.
   *
   * @param $webhook_url string Your Webhook URL including the secret token.
   */
  function __construct( $webhook_url ) {
    $this->webhook_url = $webhook_url;
  }


  /////////////////////////////////////////////////////////////////////////////
  // Room functions
  /////////////////////////////////////////////////////////////////////////////
  
    /**
   * Send a message to a Mattermost room
   * @author: andreas.scharf
   */
	public function message_room( $channel_id, $bot_name = 'AskAgfa', $author_name, $author_link, $title, $title_link, $message, $tags, 
								$color = self::COLOR_GREEN,
								$icon_url = 'http://ask.agfahealthcare.com/qa-theme/q2a_logo_3_v12_small.gif',
								$pretext = 'A new question has arrived:' ) 
	{
		$args = array(
			'username' => utf8_encode($bot_name),
			'icon_url' => utf8_encode($icon_url),
			'channel' => utf8_encode($channel_id),
			'attachments' => array( array( 
				'fallback' 		=> "A new questions was posted on AskAgfa",
				'color' 		=> utf8_encode($color),
				'pretext' 		=> utf8_encode($pretext),
				'text' 			=> utf8_encode($message),
				'author_name' 	=> utf8_encode($author_name),
				'author_icon' 	=> utf8_encode("http://ask.agfahealthcare.com/qa-theme/Snow/images/claim-icon.png"),
				'author_link' 	=> utf8_encode($author_link),
				'title' 		=> utf8_encode($title),
				'title_link' 	=> utf8_encode($title_link),
				'fields'		=> array( 
										array( 'short' => true, 'title' => 'Tags:', 'value' => $this->create_tags_with_links( $tags ) )
										//array( 'short' => true, 'title' => 'Category:', 'value' => $this->create_category_link( $category ) )  //currently not working
									)
			))
    );
    $response = $this->make_request("rooms/message", $args, 'POST');
    return ($response->status == 'sent');
  }
  
	public function create_category_link( $category )
	{
		$trimmed_category = trim($category);
	return '['.$trimmed_category.'](http://ask.agfahealthcare.com/category/'.$trimmed_category.')';
	}
	
	public function create_tags_with_links($tags) {
		$tags_without_links = explode( ",", $tags );
		$tags_with_links = array();
		foreach( $tags_without_links as $tag )
		{
			$trimmed_tag = trim( $tag );
			if( !empty( $trimmed_tag ) )
			{
				$tags_with_links[] = "[" . $trimmed_tag . '](http://ask.agfahealthcare.com/tag/' . $trimmed_tag .')';
			}
		}
	
		$formatted_tags = implode( ", ", $tags_with_links );
		return $formatted_tags;
	}

  /////////////////////////////////////////////////////////////////////////////
  // User functions
  /////////////////////////////////////////////////////////////////////////////

  /**
   * Get information about a user
   *
   * @see http://api.hipchat.com/docs/api/method/users/show
   */
  public function get_user($user_id) {
    $response = $this->make_request("users/show", array(
      'user_id' => $user_id
    ));
    return $response->user;
  }

  /**
   * Get list of users
   *
   * @see http://api.hipchat.com/docs/api/method/users/list
   */
  public function get_users() {
    $response = $this->make_request('users/list');
    return $response->users;
  }


  /////////////////////////////////////////////////////////////////////////////
  // Helper functions
  /////////////////////////////////////////////////////////////////////////////

  /**
   * Performs a curl request
   *
   * @param $url        URL to hit.
   * @param $post_data  Data to send via POST. Leave null for GET request.
   *
   * @throws HipChat_Exception
   * @return string
   */
  public function curl_request($url, $post_data = null) {

	$data_string = "";
    if (is_array($post_data)) {
	  $data_string = json_encode($post_data);
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Content-Type: application/json',
		'Content-Length: ' . strlen($data_string))
		);
    $response = curl_exec($ch);

    // make sure we got a real response
    if (strlen($response) == 0) {
      $errno = curl_errno($ch);
      $error = curl_error($ch);
      throw new HipChat_Exception(self::STATUS_BAD_RESPONSE,
        "CURL error: $errno - $error", $url);
    }

    // make sure we got a 200
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($code != self::STATUS_OK) {
      throw new HipChat_Exception($code,
        "HTTP status code: $code, response=$response", $url);
    }

    curl_close($ch);

    return $response;
  }

  /**
   * Make an API request using curl
   *
   * @param string $api_method  Which API method to hit, like 'rooms/show'.
   * @param array  $args        Data to send.
   * @param string $http_method HTTP method (GET or POST).
   *
   * @throws HipChat_Exception
   * @return mixed
   */
  public function make_request($api_method, $args = array(),
                               $http_method = 'GET') {
    //$args['format'] = 'json';
    //$args['auth_token'] = $this->auth_token;
    $url = "$this->api_target/$this->api_version/$api_method";
	$url = $this->webhook_url;
	//$url = 'http://viesuhv12.agfahealthcare.com/hooks/45jc4ayefjycpx66s77wbckbfa';
    $post_data = $args;
/*
    // add args to url for GET
    if ($http_method == 'GET') {
      $url .= '?'.http_build_query($args);
    } else {
      $post_data = $args;
    }
*/
    $response = $this->curl_request($url, $post_data);

    // make sure response is valid json
	// it seems that Mattermost does not send a response. Thus the response must be some html OK ?
	/*
    $response = json_decode($response);
    if (!$response) {
      throw new HipChat_Exception(self::STATUS_BAD_RESPONSE,
        "Invalid JSON received: $response", $url);
    }
*/
    return $response;
  }

  /**
   * Enable/disable verify_ssl.
   * This is useful when curl spits back ssl verification errors, most likely
   * due to outdated SSL CA bundle file on server. If you are able to, update
   * that CA bundle. If not, call this method with false for $bool param before
   * interacting with the API.
   *
   * @param bool $bool
   * @return bool
   * @link http://davidwalsh.name/php-ssl-curl-error
   */
  public function set_verify_ssl($bool = true) {
    $this->verify_ssl = (bool)$bool;
    return $this->verify_ssl;
  }

  /**
   * Set an outbound proxy to use as a curl option
   * To disable proxy usage, set $proxy to null
   *
   * @param string $proxy
   */
  public function set_proxy($proxy) {
    $this->proxy = $proxy;
  }

}


class HipChat_Exception extends \Exception {
  public $code;
  public function __construct($code, $info, $url) {
    $message = "HipChat API error: code=$code, info=$info, url=$url";
    parent::__construct($message, (int)$code);
  }
}
