<?php
require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'Dommer.php');

class Crawler {

	private $headerArray = array();
	private $userAgent = '';
	private $cookies = array();
	private $lastDomain = '';
	private $lastProtocol = '';
	private $lastPath = '';

	/**
	 * __construct
	 */
	public function __construct($timeout_second = null, $userAgent = null) {
		if (is_null($timeout_second)) {
			$timeout_second = 600;
		}
		if (is_null($userAgent)) {
			$userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.7; rv:15.0) Gecko/20100101 Firefox/15.0';
		}
		ini_set('default_socket_timeout', $timeout_second);
		$this->initCookies();
		$this->userAgent = $userAgent;
	}

	public function initCookies() {
		$this->cookies = array();
	}

	public function setUserAgent($userAgent) {
		$this->userAgent = $userAgent;
	}

	public function get($url, $encode = 'utf8', $parse = true) {
		$sc = $this->createStreamContext('GET');
		return $this->request($url, $sc, $encode, $parse);
	}

	public function post($url, $encode = 'utf8', $data = null, $parse = true) {
		$sc = $this->createStreamContext('POST', $data);
		return $this->request($url, $sc, $encode, $parse);
	}

	private function getHeaderValue($key) {
		$ret = array();
		if (!empty($this->headerArray)) {
			foreach ($this->headerArray as $header) {
				if (preg_match_all("|^{$key}: ([^;]*);?|", $header, $matches)) {
					$ret[] = $matches[1][0];
				}
			}
		}
		return $ret;
	}

	private function getRedirect($body) {
		$location = false;
		$status = '';
		if (!empty($this->headerArray)) {
			foreach ($this->headerArray as $header) {
				if (preg_match_all('|^HTTP/1.1 (\d+) .*$|', $header, $matches)) {
					$status = $matches[1][0];
				}
			}
		}
		if ($status == '302') {
			$locations = $this->getHeaderValue('Location');
			$location = $locations[0];
		}
		if (preg_match('|<meta +http-equiv="refresh" +[^>]*? *content=".*?url=([^;"> ]+).*?" */?>|is', $body, $match) !== 0) {
			$location = $match[1];
		}
		return $location;
	}

	private function getCookie() {
		$tmp = array();
		$cookies = $this->getHeaderValue('Set-Cookie');
		foreach ($cookies as $cookie) {
			if (preg_match_all('|^(.*)=(.*)$|', $cookie, $matches)) {
				$this->cookies[$matches[1][0]] = $matches[2][0];
			}
		}
		$ret = '';
		foreach ($this->cookies as $key=>$value) {
			if (!empty($ret)) {
				$ret .= '; ';
			}
			$ret .= "{$key}={$value}";
		}
		return $ret;
	}

	private function createStreamContext($method, $data = null) {
		$cookie = $this->getCookie();
		if (empty($data)) {
			$options = array(
				"http" => array(
					"method"=>$method,
					"request_fulluri"=>false,
					"max_redirects"=>0,
					"header"=>"Cookie: {$cookie}\r\n"
					."User-Agent: {$this->userAgent}\r\n"
					."Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n"
					."Accept-Language: ja-JP-mac,ja;q=0.9,ja-JP;q=0.8,en-US;q=0.8,en;q=0.7,zh-CN;q=0.6,zh;q=0.5,zh-TW;q=0.5,pt-PT;q=0.4,pt;q=0.3,de-DE;q=0.2,de;q=0.2,pt-br;q=0.1\r\n"
					."Connection: keep-alive\r\n"
								));
		} else {
			if (is_array($data)) {
				$query = http_build_query($data);
			} else {
				$query = $data;
			}
			$options = array(
				"http" => array(
					"method"=>$method,
					"content"=>$query,
					"request_fulluri"=>false,
					"max_redirects"=>0,
					"header"=>"Cookie: {$cookie}\r\n"
					."User-Agent: {$this->userAgent}\r\n"
					."Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n"
					."Accept-Language: ja-JP-mac,ja;q=0.9,ja-JP;q=0.8,en-US;q=0.8,en;q=0.7,zh-CN;q=0.6,zh;q=0.5,zh-TW;q=0.5,pt-PT;q=0.4,pt;q=0.3,de-DE;q=0.2,de;q=0.2,pt-br;q=0.1\r\n"
					."Connection: keep-alive\r\n"
					."Content-Type: application/x-www-form-urlencoded\r\n"
					."Content-Length: ".strlen($query)
								));
		}

		return stream_context_create($options);
	}

	private function formatUrl($url) {
		if (preg_match('#^(http[s]?)://([^/]+)(.*)/?#', $url, $matches)) {
			$this->lastProtocol = $matches[1];
			$this->lastDomain = $matches[2];
			$this->lastPath = $matches[3];
		} else {
			if (strpos($url, '/') === 0) {
				$url = $this->lastProtocol.'://'.$this->lastDomain.$url;
			} else {
				$url = $this->lastProtocol.'://'.$this->lastDomain.$this->lastPath.'/'.$url;
			}
		}
		return $url;
	}

	private function checkError() {
		$error = error_get_last();
		if (empty($error)) {
			return;
		}
		if ($error['file'] != __FILE__) {
			return;
		}
		if ($error['type'] == E_WARNING && preg_match('#Redirection limit reached#i', $error['message']) !== 0) {
			return;
		}
		error_log(date('Y-m-d H:i:s').' ====================================================================================================');
		error_log($error['type']);
		error_log($error['file']);
		error_log($error['line']);
		error_log($error['message']);
		error_log('');
	}

	private function request($url, $context, $encode, $parse) {
		$url = $this->formatUrl($url);
		$ret = @file_get_contents($url, false, $context);
		$this->checkError();
		$this->headerArray = array();
		if (!empty($http_response_header)) {
			$this->headerArray = $http_response_header;
		}
		if ($location = $this->getRedirect($ret)) {
			$ret = $this->get($location, $encode, $parse);
		} else if ($parse && $ret) {
			$ret = new Dommer($ret, 'text', $encode);
		}
		return $ret;
	}

}
