<?php
class Dommer {

	private $tidy = null;
	private $sxe = null;
	private $ns = null;

	/**
	 * __construct
	 */
	public function __construct($xml = null, $type = 'text', $encode = null) {
		$this->tidy = new tidy();
		if (!is_null($xml)) {
			if ($type == 'text') {
				$this->initByText($xml, $encode);
			} else if ($type == 'sxe') {
				$this->initBySxe($xml, $encode);
			}
		}
		return $this;
	}

	public function asXML($path = null) {
		return $this->sxe->asXML();
	}

	public function xpath($path) {
		$pattern = $path;
		if (!is_null($this->ns)) {
			preg_match_all('|\[.*?\]|', $pattern, $matches);
			$replacements = array_unique($matches[0]);
			foreach ($replacements as $index=>$replacement) {
				$pattern = str_replace($replacement, 'DOMMERDUMMY'.$index, $pattern);
			}
			$pattern = preg_replace('|(/{1,2})|', "$1{$this->ns}", $pattern);
			foreach ($replacements as $index=>$replacement) {
				$pattern = str_replace('DOMMERDUMMY'.$index, $replacement, $pattern);
			}
		}
		return $this->sxe->xpath($pattern);
	}

	public function html($path = null) {
		$sxe = $this->sxe;
		if (!empty($path)) {
			$sxe = $this->xpath($path);
		}
		$ret = '';
		foreach ($sxe as $one) {
			$ret .= $one->asXML();
		}
		return $ret;
	}

	public function innerHtml($path) {
		$sxe = $this->xpath($path.'/*');
		$ret = '';
		foreach ($sxe as $one) {
			$ret .= $one->asXML();
		}
		return $ret;
	}

	private function shape($text, $encode = null) {
		$config = array(
			'indent' => true,
			'input-xml' => true,
			'wrap' => 200,
						);
		if (is_null($encode)) {
			$encode = 'utf8';
		}
		if ($encode != 'utf8') {
			$text = mb_convert_encoding($text, 'utf8', $encode);
		}
		$text = preg_replace('/&?nbsp;?/', ' ', $text);
		$this->tidy->parseString($text, $config, 'utf8');
		$this->tidy->cleanRepair();
		$text = $this->tidy->value;
		return $text;
	}

	private function setNamespace() {
		$nsArray = $this->sxe->getNamespaces();
		$ns = array_shift($nsArray);
		if (!is_null($ns)) {
			$this->ns = 'dommer';
			$this->sxe->registerXPathNamespace($this->ns, $ns);
			$this->ns .= ':';
		}
	}

	private function initByText($xml, $encode = null) {
		$xml = $this->shape($xml, $encode);
		$this->sxe = simplexml_load_string($xml);
		$this->setNamespace();
	}

	private function initBySxe($xml, $encode = null) {
		$xml = $this->shape($xml->asXML(), $encode);
		$this->sxe = simplexml_load_string($xml);
		$this->setNamespace();
	}

}