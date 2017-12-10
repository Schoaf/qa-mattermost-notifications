<?php
/*
  Mattermost Notifications

  File: qa-plugin/mattermost-notifications/Mattermost/Mattermost.php
  Version: 0.5
  Date: 2017-12-10
  Description: Mattermost API to access channels
*/

namespace Mattermost;

/**
 * Library for interacting with the Mattermost.
 * Inspired by https://github.com/jhubert/qa-hipchat-notifications
 */
class Mattermost {

  /**
   * HTTP response codes from API
   *
   * @see ...
   */
  const STATUS_BAD_RESPONSE = -1; // Not an HTTP response code
  const STATUS_OK = 200;

  /**
   * Colors for rooms/message
   */
  const COLOR_GREEN = '#289E00';

  /**
   * API versions
   */
  const VERSION_1 = 'v1';

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
	public function message_room( $channel_id, $bot_name = 'AskAgfa', $author_name, $author_icon, $author_link, $title, $title_link, $message, $tags, $category, 
								$color = self::COLOR_GREEN,
								$icon_url = 'http://ask.agfahealthcare.com/qa-theme/q2a_logo_3_v12_small.gif',
								$pretext = 'A new question has arrived:',
								$question_id, $views, $answers, $action_callback_url ) 
	{
		$args = self::createMattermostMessageAttachment( $channel_id, $bot_name, $author_name, $author_icon, $author_link, $title, $title_link, $message, $tags, $category, 
								$color,	$icon_url, $pretext, $question_id, $views, $answers, $action_callback_url );
		
    $response = $this->make_request("rooms/message", $args, 'POST');
    return ($response->status == 'sent');
  }

  /////////////////////////////////////////////////////////////////////////////
  // Helper functions
  /////////////////////////////////////////////////////////////////////////////
  
	public static function createMattermostMessageAttachment( $channel_id, $bot_name, $author_name, $author_icon, $author_link, $title, $title_link, $message, $tags, $category, 
								$color,	$icon_url, $pretext, $question_id, $views, $answers, $action_callback_url )
	{
		$status = 'Answers: '.$answers.'  |  Views: '.$views.'    ('.date('Y-m-d h:m').')';
		if( $answers == 0 )
		{
			$status = ':o:  '.$status;
		}
		else
		{
			$status = ':white_check_mark: '.$status;
		}
		return array(
			'username' => utf8_encode($bot_name),
			'icon_url' => utf8_encode($icon_url),
			'channel' => utf8_encode($channel_id),
			'attachments' => array( array( 
				'fallback' 		=> "A new questions was posted on AskAgfa",
				'color' 		=> utf8_encode($color),
				'pretext' 		=> utf8_encode($pretext),
				'text' 			=> utf8_encode($message),
				'author_name' 	=> utf8_encode($author_name),
				'author_icon' 	=> utf8_encode($author_icon),
				'author_link' 	=> utf8_encode($author_link),
				'title' 		=> utf8_encode($title),
				'title_link' 	=> utf8_encode($title_link),
				'fields'		=> array( 
										array( 'short' => true, 'title' => 'Tags:', 'value' => $tags ),
										array( 'short' => true, 'title' => 'Category:', 'value' => $category ),
										array( 'short' => true, 'title' => 'Status:', 'value' => $status )
									),
				'actions'		=> array( array( 
											'name' => 'Refresh Status', 
											'integration' => array( 
														'url' => $action_callback_url, 
														'context' => array(
																		'channel_id' 	=> $channel_id,
																		'bot_name'		=> $bot_name,
																		'author_name'	=> $author_name, 
																		'author_icon'	=> $author_icon, 
																		'author_link'	=> $author_link, 
																		'title'			=> $title,
																		'title_link'	=> $title_link, 
																		'message'		=> $message, 
																		'tags'			=> $tags, 
																		'category'		=> $category, 
																		'color'			=> $color,
																		'icon_url'		=> $icon_url,
																		'pretext'		=> $pretext,
																		'question_id'	=> $question_id,
																		'action_callback_url' => $action_callback_url
																		)
														) 
											)
									)
				))
			);
	}

  /**
   * Performs a curl request
   *
   * @param $url        URL to hit.
   * @param $post_data  Data to send via POST. Leave null for GET request.
   *
   * @throws Mattermost_Exception
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
      throw new Mattermost_Exception(self::STATUS_BAD_RESPONSE,
        "CURL error: $errno - $error", $url);
    }

    // make sure we got a 200
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($code != self::STATUS_OK) {
      throw new Mattermost_Exception($code,
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
   * @return mixed
   */
  public function make_request($api_method, $args = array(),
                               $http_method = 'GET') {
	$url = $this->webhook_url;
    $post_data = $args;
    $response = $this->curl_request($url, $post_data);

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

class Mattermost_Exception extends \Exception {
  public $code;
  public function __construct($code, $info, $url) {
    $message = "Mattermost API error: code=$code, info=$info, url=$url";
    parent::__construct($message, (int)$code);
  }
}
