<?php

	namespace vk4php;

	/**
	 * Class VK4DS
	 * VK Api Wrapper
	 * PHP Library for use API of social network vkontakte (vk.com) Library from VK4* series <Easy2use>
	 * Useful for server-side
	 *
	 * @package vk4php
	 * @version 1.0
	 * @author Mechislav Lipskiy <id110099122@gmail.com>
	 * @license MIT
	 *
	 * @todo implement vk.sql #idea
	 * @todo implement antigate/anti-captcha built-in service
	 *
	 *
	 * @tags vk, vk.com, api, easy2use, easy to use, login and password, user credentials, server, offline, android, OOP, vk4php, android app based, standalone, server-side, no captcha, antigate, anti-captcha
	 * @git https://github.com/d3n4/vk4php
	 * @php_version >= 5.4.0
	 * https://anti-captcha.com/code/curl.txt
	 */
	class VK4DS {

		# [ Core constants ] #
		const API_VERSION = '5.29';

		# [ Reserved android application constants ] #
		const ANDROID_APPID = 2274003;
		const ANDROID_SECRET = 'hHbZxrka2uZ6jB1inYsH';

		# [ VK URL`s constants ] #
		const OAUTH_TOKEN_URL = 'https://oauth.vk.com/token';
		const API_CALL_URL = 'https://api.vk.com/method';

		# [ Session ] #

		/**
		 * Last JSON response from VK API
		 * @var VKSuccessResponse|VKErrorResponse|string|null
		 */
		protected $last_response;

		/**
		 * Using VK4DS session for store token and restore it after next script execution
		 * @var bool $use_session boolean state
		 */
		protected $use_session = false;

		/**
		 * Serializable array of settings
		 * @var array $session VK4DS session storage
		 */
		protected $session;

		# [ User credentials ] #
		/**
		 * User login/name for authentication
		 * @var string $username username
		 */
		protected $username;

		/**
		 * User password for authentication
		 * @var string $password password
		 */
		protected $password;

		# [ Application configuration ] #
		/**
		 * VK application id (look in app settings)
		 * @var int $client_id application id (android app by default)
		 */
		protected $client_id = self::ANDROID_APPID;

		/**
		 * VK application secret (look in app settings)
		 * @var string $client_id application secret key (android app by default)
		 */
		protected $client_secret = self::ANDROID_SECRET;

		# [ API user information ] #
		/**
		 * Current authenticated user id
		 * @var int user id
		 */
		protected $user_id;

		/**
		 * API access token
		 * @var string $access_token OAuth access token
		 */
		protected $access_token;

		/**
		 * @var int $expires_in lifetime of access token (in seconds)
		 */
		protected $expires_in;

		# [ Debug ] #
		/**
		 * @var callable(int, string) $log_handler
		 */
		protected $log_handler;

		/**
		 * Write log messages into log_handler
		 * @var bool verbose mode
		 */
		protected $verbose = true;

		public function __construct() {
			$this->log_handler = [$this, '_log'];
		}

		/**
		 * Default log handler
		 * @param int $time
		 * @param string $message
		 */
		protected function _log($time, $message) {
			echo '['.( date('d.m.Y H:i:s.', $time) . substr($time, -2) ).']: '.$message.chr(10).chr(13);
		}

		/**
		 * Call current log handler
		 * @param string $message
		 */
		public function log($message) {
			if($this->verbose && is_callable($this->log_handler))
				call_user_func_array($this->log_handler, [microtime(1), $message]);
		}

		/**
		 * Modify outgoing keyword
		 * @param string $keyword raw keyword
		 * @param bool $method_aliases use aliases for method separator `.`
		 * @param bool $param_aliases use aliases for params separator `_`
		 * @return string modified keyword
		 */
		protected static function keyword($keyword, $method_aliases = false, $param_aliases = false) {
			if($method_aliases)
				$keyword = preg_replace(['/(::|:|->|,)/', '/\s/'], ['.', ''], $keyword);
			if($param_aliases)
				$keyword = preg_replace(['/(::|:|->|,)/', '/\s/'], ['_', ''], $keyword);
			if(!$method_aliases && !$param_aliases)
				$keyword = trim($keyword);
			return strtolower($keyword);
		}

		/**
		 * Send basic API request
		 * @param string $method method name
		 * @param array|null $args list of arguments can be NULL if no arguments at all
		 * @param bool $no_prepare do not use keyword processor
		 * @return null|\stdClass|array returns response if success else returns NULL
		 */
		public function api($method, $args = null, $no_prepare = false) {
			if(!$no_prepare)
				$method = self::keyword($method, true);

			if(!$no_prepare && $args && is_array($args) || is_object($args) && $args = (array)$args)
				foreach($args as $key => $value)
					$args[self::keyword($key, false, true)] = $value;

			$args['v'] = self::API_VERSION;
			$args['access_token'] = $this->access_token;

			/**
			 * @var VKSuccessResponse|VKErrorResponse|null $response
			 */
			$response = $this->getJSON(self::API_CALL_URL . '/' . $method, $args);
			if(isset($response->response))
				return $response->response;
			return null;
		}

		/**
		 * Authenticate using user credentials
		 * @param string $username username/phone number/email
		 * @param string $password user password
		 * @return bool authentication result
		 */
		public function auth($username, $password) {
			$this->username = $username;
			$this->password = $password;
			return $this->getTokenByCredentials();
		}

		/**
		 * Trying to authenticate using user credentials
		 * @return bool is user authenticated
		 */
		protected function getTokenByCredentials() {
			$authData = $this->getJSON(self::OAUTH_TOKEN_URL, [
				'grant_type'		=> 'password',
				'client_id'			=> $this->client_id,
				'client_secret'	=> $this->client_secret,
				'username'			=> $this->username,
				'password'			=> $this->password
			]);

			if(!isset($authData->user_id, $authData->access_token, $authData->expires_in))
				return false;

			$this->user_id = intval($authData->user_id);
			$this->access_token = $authData->access_token;
			$this->expires_in = intval($authData->expires_in);

			return true;
		}

		/**
		 * HTTP Request Get json object or string content
		 * @param string $url Url
		 * @param null|array|object $fields Query fields
		 * @param boolean $raw = false by default return raw content without parsing
		 * @return string|\stdClass|VKSuccessResponse|VKErrorResponse Result object or string if RAW
		 */
		protected function getJSON($url, $fields = null, $raw = false) {
			# Fields generation #
			if($fields !== null && (is_array($fields) || is_object($fields))) {
				if(is_object($fields))
					$fields = (array)$fields;
				$_url = explode('?', $url);
				$url = $_url[0].'?'.http_build_query($fields);
			}

			# Initialize curl for future request #
			$curl = curl_init($url);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); // Ignore SSL Verification
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // Ignore SSL Verification
			$response = curl_exec($curl);
			curl_close($curl);
			return $this->last_response = $raw ? $response : @json_decode($response);
		}
	}

	# [ VK Response Interfaces ] #

	class KeyValuePair {
		/**
		 * @var mixed $key
		 */
		public $key;

		/**
		 * @var mixed $value
		 */
		public $value;
	}

	/**
	 * Class VKSuccessResponse
	 * @package vk4php
	 */
	class VKSuccessResponse {
		/**
		 * @var \stdClass|array
		 */
		public $response;
	}

	/**
	 * Class VKErrorResponse
	 * @package vk4php
	 */
	class VKErrorResponse {
		/**
		 * @var VKErrorResponseDetails
		 */
		public $error;
	}

	/**
	 * Class VKErrorResponseDetails
	 * @package vk4php
	 */
	class VKErrorResponseDetails {

		/**
		 * @var int
		 */
		public $error_code;

		/**
		 * @var string
		 */
		public $error_msg;

		/**
		 * @var KeyValuePair[]
		 */
		public $request_params;

	}