<?php

class CF_Favicon_Fetch {
	
	/**
	 * Location to save the fetched files
	 *
	 * @var string
	 */
	protected $upload_dir;
	
	/**
	 * Seconds that we'll wait for a response
	 * when fetching favicon data
	 * 
	 * @var int
	 */
	protected $timeout = 15;
	
	/**
	 * Response codes that we'll accept as valid when fetching a favicon
	 *
	 * @var array
	 */
	protected $valid_response_codes = array(200, 301, 302);
	
	/**
	 * Enable logging to the PHP error log
	 *
	 * @var string
	 */
	public $error_log_enabled = false;
	
	/**
	 * Hold on to the last error we encountered for informational messages
	 *
	 * @var string
	 */
	protected $last_error = false;
	
	/**
	 * Construct
	 *
	 * @todo throw exeption if upload dir is not usable?
	 * @param string $upload_dir 
	 */
	public function __construct($upload_dir, $options = array()) {
		$this->upload_dir = trailingslashit($upload_dir);
		if (!empty($options['timeout'])) {
			$this->timeout = intval($options['timeout']);
		}
		if (!empty($options['valid_response_codes']) && is_array($options['valid_response_codes'])) {
			$this->valid_response_codes = array_map('intval', $options['valid_response_codes']);
		}
	}
	
	private function error_log($str) {
		if ($this->error_log_enabled) {
			error_log($str);
		}
	}

// Main query functions 
	
	public function get_site_favicon_url($siteurl) {
		$this->reset_error();
		
		$siteurl = $siteurl;
		$favicon = false;
		
		if ($f_url = $this->query_head($siteurl)) {
			$favicon = $f_url;
		}
		else if ($f_data = $this->query_server($siteurl)) {
			$favicon = $this->get_baseurl($siteurl).'/favicon.ico';
		}

		return $favicon;
	}
	
	/**
	 * Search for the favicon for a site & then import the favicon to the uploads/favicons folder
	 *
	 * @param string $url 
	 * @return string
	 */
	public function get_favicon($siteurl) {
		$this->reset_error();
		
		$favicon = 'default';
		
		if ($f_url = $this->query_head($siteurl)) {
			$f_data = $this->fetch_favicon($f_url);
			$qs = strpos($f_data['ext'], '?');
			if ($qs !== false) {
				$f_data['ext'] = substr($f_data['ext'], 0, $qs);
			}
			$filename = $this->make_filename($siteurl, $f_data['ext']);			
		}
		else if ($f_data = $this->query_server($siteurl)) {
			$filename = $this->make_filename($siteurl, 'ico');
		}

		if (!empty($f_data) && !empty($filename)) {
			$r = $this->save_file($filename, $f_data);
			if ($r !== false) {
				$favicon = $r;
			}
		}
		
		return $favicon;
	}
	
	/**
	 * check to see wether we have a favicon for this site or not
	 *
	 * @param string $siteurl 
	 * @return mixed bool/string - bool on false, filename on true
	 */
	public function have_site_favicon($siteurl) {
		$filename = $this->make_filename($siteurl);
		$files = glob(trailingslashit(cfcp_about_get_favicon_dir()).$filename.'\.*');
		
		$favicon = false;
		if (!empty($files[0])) {
			$favicon = basename($files[0]);
		}
		
		return $favicon;
	}

// Utility
	
	/**
	 * Use Yahoo YQL service to grab the icon from the html source
	 * Only looks at the links defined in the head and searches for
	 * a few popular variants on the head link format
	 *
	 * @param string $siteurl - url of the site to inspect
	 * @return mixed - string $url on success, false on failure
	 */
	public function query_head($siteurl) {
		$favicon = false;
		
		$y = "http://query.yahooapis.com/v1/public/yql?q=";
		$y .= urlencode('select * from html where url="'.$siteurl.'" '.
				"and xpath=\"/html/head/link[@rel='icon'] ".
				"| /html/head/link[@rel='ICON'] | /html/head/link[@rel='shortcut icon'] ".
				"| /html/head/link[@rel='SHORTCUT ICON']\"");
		$y .= "&format=json";

		$r = $this->remote_get($y);

		if (!is_wp_error($r) && $this->is_valid_response_code($r['response']['code']) && !empty($r['body'])) {
			$data = json_decode($r['body']);

			if (!empty($data) && $data->query->count > 0) {
				// well, now, isn't this fun!
				if (!empty($data->query->results->link->href)) {
					// 1 result
					$favicon = trim($data->query->results->link->href);
				}
				elseif (!empty($data->query->results->link[0]->href)) {
					// many results
					$favicon = trim($data->query->results->link[0]->href);
				}
				else {
					$this->handle_error(new WP_Error('Unknown data return format: '.print_r($data, true)));
				}

				if (!empty($favicon)) {
					$favicon = $this->fix_relative_url($favicon, $siteurl);
				}
			}
		}
		else {
			$this->handle_error(@$r->get_error_message(), __METHOD__);
		}
	
		unset($r);
		return $favicon;
	}

	/**
	 * Query for the favicon at the Server's site root
	 *
	 * @param string $siteurl 
	 * @return mixed - string $url on success, false on failure
	 */
	public function query_server($siteurl) {
		$favicon = false;
		$this->server_query_result = false;

		// reduce the siteurl down to the base server name
		$query_url = $this->get_baseurl($siteurl);
		
		$favicon_url = trailingslashit($query_url).'favicon.ico';
		
		// to check the file availability WP's functions will still pull
		// the file so we may as well do a full on fetch
		return $this->fetch_favicon($favicon_url);
	}

	/**
	 * import a remote favicon
	 * Perform a remote get to grab the file itself and pass off to save method
	 *
	 * @param string $favicon_url 
	 * @return mixed string/bool - false on failure
	 */
	public function fetch_favicon($favicon_url) {
		$file = $this->remote_get($favicon_url);
		$favicon = false;

		if (!is_wp_error($file) && $this->is_valid_response_code($file['response']['code']) && !empty($file['body']) && $this->is_image($file)) {
			$filename = $this->make_filename($favicon_url);
			$favicon = array(
				'ext' => pathinfo(basename($favicon_url), PATHINFO_EXTENSION),
				'source' => $file['body']
			);
		}
		else if (!is_wp_error($file) && empty($file['body'])) {
			$this->handle_error(new WP_Error('Request returned no image content'), __METHOD__);
		}
		else if (is_wp_error($file)) {
			$this->handle_error($file->get_error_message(), __METHOD__);
		}

		return $favicon;
	}
	
	/**
	 * Save the favicon to the filesystem
	 * $icon_data = array(
	 * 	'ext' => '.ext',
	 *	'source' => '... raw image data ...'
	 * );
	 *
	 * @param Array $icon_data 
	 * @return mixed string/bool - false on failure
	 */
	public function save_file($filename, $icon_data) {
		$result = false;
		if ($this->check_upload_dir()) {
			$upload_filepath = $this->upload_dir.$filename;
			$bytes = file_put_contents($upload_filepath, $icon_data['source']);
			$result = ($bytes > 0 ? $upload_filepath : false);
		}
		return $result;
	}

	public function fix_relative_url($favicon_url, $siteurl) {
		$p_favicon_url = parse_url($favicon_url);
		if (empty($p_favicon_url['host'])) {
			$p_siteurl = parse_url($siteurl);
			$favicon_url = $p_siteurl['scheme'].'://'.$p_siteurl['host'].'/'.ltrim($favicon_url, ' /');
		}
		return $favicon_url;
	}
	
// Internal

	public function get_baseurl($siteurl) {
		$parts = parse_url(esc_url($siteurl));
		return $parts['scheme'].'://'.$parts['host'];
	}

	/**
	 * Perform remote get function
	 * Must supress notice output from the wp_remote_get function 
	 * so that it doesn't bork ajax operations.
	 *
	 * @param string $url 
	 * @return mixed array/object will be WP_Error object on failure
	 */
	public function remote_get($url) {
		return @wp_remote_get($url, array(
			'timeout' => $this->timeout,
			'sslverify' => false
		));
	}

	public function check_upload_dir() {
		if (!is_dir($this->upload_dir)) {
			mkdir($this->upload_dir);
		}
		$upload_dir = is_dir($this->upload_dir) && is_writable($this->upload_dir);
		if (!$upload_dir) {
			$this->error_log('There is a problem with the upload dir "'.$this->upload_dir.'" - it is either missing or not writable');
		}
		return $upload_dir;
	}

	/**
	 * Create a save-filename based on the url of the source file
	 *
	 * @param string $favicon_url 
	 * @return string
	 */
	public function make_filename($url, $ext = '') {
		return md5($this->get_baseurl($url)).(!empty($ext) ? '.'.$ext : '');
	}
	
	/**
	 * Check a response code against a predefined list of 
	 * valid OK response codes. 
	 *
	 * @todo flush out list against known good http codes
	 * @param string $code 
	 * @return void
	 */
	public function is_valid_response_code($code) {
		return in_array(intval($code), $this->valid_response_codes);
	}
	
	public function reset_error() {
		return $this->last_error = null;
	}
	
	public function get_last_error() {
		return $this->last_error;
	}
	
	public function is_image($file) {
		return true;
// wordpress.org returns application/octet-stream
// need to decide how to handle this in the future
//		return strpos($file['headers']['content-type'], 'image') !== false;
	}
	
	/**
	 * Stupid error handler
	 * No, error handling isn't stupid, this handler is
	 *
	 * @param mixed $response 
	 * @return void
	 */
	public function handle_error($response, $method = '') {
		if (defined('WP_DEBUG') && WP_DEBUG) {
			if (is_wp_error($response)) {
				$msg = $response->get_error_message();
			}
			else {
				$msg = strval($response);
			}
			$this->last_error = $msg;
			$this->error_log('Error in remote request '.$msg.' :: '.$method);
		}
	}
}

?>