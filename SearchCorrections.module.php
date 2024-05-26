<?php namespace ProcessWire;

class SearchCorrections extends WireData implements Module {

	/**
	 * Find the stem of a given word
	 * Uses php-stemmer: https://github.com/wamania/php-stemmer
	 *
	 * @param string $word
	 * @return string
	 */
	public function stem($word, $language = 'english') {
		require_once $this->wire()->config->paths->$this . 'vendor/autoload.php';
		$stemmer = \Wamania\Snowball\StemmerFactory::create($language);
		return $stemmer->stem($word);
	}

	/**
	 * Find similar words
	 *
	 * @param string $target The target (input) word to match against
	 * @param string $selector A selector string that defines the
	 * @param array $fields
	 * @param array $options
	 * @return array
	 */
	public function findSimilarWords($target, $selector, $fields, $options = []) {
		$cache = $this->wire()->cache;
		$ns = $this->className;
		$target = mb_strtolower($target);
		
		// Validate fields: only title, text and textarea fields are supported
		foreach($fields as $key => $field_name) {
			$field = $this->wire()->fields->get($field_name);
			if(!wireInstanceOf($field->type, ['FieldtypePageTitle', 'FieldtypeText', 'FieldtypeTextarea'])) unset($fields[$key]);
		}

		// Options
		$default_options = [
			'minWordLength' => 4,
			'lengthRange' => 2,
			'expire' => 3600,
			'maxChangePercent' => 50,
			'insertionCost' => 1,
			'replacementCost' => 1,
			'deletionCost' => 1,
		];
		$options = array_merge($default_options, $options);

		// Get all words, from the cache if available
		$settings = [$selector, $fields, $options];
		$cache_name = json_encode($settings);
		$all_words = $cache->getFor($ns, $cache_name);
		if(is_null($all_words)) {
			$uw_options = [
				'minWordLength' => $options['minWordLength'],
			];
			$all_words = $this->getUniqueWords($selector, $fields, $uw_options);
			$cache->saveFor($ns, $cache_name, $all_words, $options['expire']);
		}

		// Get min and max word length
		$target_length = mb_strlen($target, 'UTF-8');
		$min_length = $target_length - $options['lengthRange'];
		$max_length = $target_length + $options['lengthRange'];
		
		// Filter by min and max word length
		$candidates = [];
		foreach($all_words as $length => $words) {
			if($length < $min_length) continue;
			if($length > $max_length) continue;
			$candidates = array_merge($candidates, $words);
		}

		// Group by Levenshtein distance
		$groups = [];
		foreach($candidates as $candidate) {
			$lev = $this->utf8Levenshtein($target, $candidate, $options['insertionCost'], $options['replacementCost'], $options['deletionCost']);
			// The changed letters as a percentage of the word length
			$percent = 100 * $lev / $target_length;
			// Skip if beyond the max percentage of change
			if($percent > $options['maxChangePercent']) continue;
			$groups[$lev][] = $candidate;
		}
		// Sort groups by the smallest distance
		ksort($groups);

		// Sort group words according to the number of matching letters at the start
		$results = [];
		foreach($groups as $lev => $words) {
			// Sort words alphabetically by default
			asort($words);
			// Sort words by number of matching letters at the start
			$weightings = [];
			foreach($words as $word) {
				$weightings[$word] = $this->countStartingLetterMatches($word, $target);
			}
			arsort($weightings);
			// Add to final results array in order
			foreach(array_keys($weightings) as $word) {
				$results[$word] = $lev;
			}
		}
		
		return $results;
	}

	/**
	 * Clean text
	 *
	 * @param string $text
	 * @return string
	 */
	protected function cleanText($text) {
		// Strip HTML tags
		$text = strip_tags($text);
		// Convert to lowercase
		$text = strtolower($text);
		// Remove invalid UTF-8 that will cause preg_replace to fail
		$text = iconv('UTF-8', 'UTF-8//IGNORE', $text);
		// Or maybe try:
		// $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
		// Remove all punctuation
		// But not apostrophes or hyphens within words
		$text = preg_replace("/(?!\b['’-]\b)[[:punct:]] ?/u", ' ', $text);
		// Replace multiple spaces with a single space
		$text = trim(preg_replace('/\s+/', ' ', $text));
		return $text;
	}

	/**
	 * Get unique words
	 *
	 * @param string $selector
	 * @param array $fields
	 * @param array $options
	 * @return array
	 */
	protected function getUniqueWords($selector, $fields) {
		// Get raw data
		$data = $this->wire()->pages->findRaw($selector, $fields, ['nulls' => true, 'flat' => true]);
		// Concatenate text
		$text = '';
		foreach($data as $flds) {
			foreach($flds as $value) {
				$text .= $value . ' ';
			}
		}
		// Clean text
		$text = $this->cleanText($text);
		// Convert to array
		$words = explode(' ', $text);
		// Unique items only
		$words = array_flip(array_flip($words));

		// Organise words by length
		$lengths = [];
		foreach($words as $word) {
			// Skip anything that is numeric
			if(is_numeric($word)) continue;
			$length = mb_strlen($word, 'UTF-8');
			// Skip anything below the minimum word length
			//if($length < $options['minWordLength']) continue;
			$lengths[$length][] = $word;
		}
		// Sort array by key (length)
		ksort($lengths);
		return $lengths;
	}

	/**
	 * Count how many letters at the start of the word match the target word
	 *
	 * @param string $word
	 * @param string $target
	 * @return int
	 */
	protected function countStartingLetterMatches($word, $target) {
		$count = 0;
		$length = mb_strlen($word, 'UTF-8');
		for($i = 0; $i < $length; $i++) {
			$letter = mb_substr($word, $i, 1, 'UTF-8');
			$target_letter = mb_substr($target, $i, 1, 'UTF-8');
			if($letter !== $target_letter) break;
			++$count;
		}
		return $count;
	}

	/**
	 * Calculate Levenshtein distance between two strings, with UTF-8 support
	 * Adapted from: https://www.php.net/manual/en/function.levenshtein.php#113702
	 *
	 * @param string $str1
	 * @param string $str2
	 * @param int $insertion_cost
	 * @param int $replacement_cost
	 * @param int $deletion_cost
	 * @return int
	 */
	protected function utf8Levenshtein($str1, $str2, $insertion_cost = 1, $replacement_cost = 1, $deletion_cost = 1) {
		$map = [];
		$str1 = $this->utf8ToAscii($str1, $map);
		$str2 = $this->utf8ToAscii($str2, $map);
		return levenshtein($str1, $str2, $insertion_cost, $replacement_cost, $deletion_cost);
	}

	/**
	 * Convert a UTF-8 encoded string to a single-byte string suitable for functions such as levenshtein
	 * Adapted from: https://www.php.net/manual/en/function.levenshtein.php#113702
	 *
	 * @param string $str
	 * @param array $map
	 * @return string
	 */
	protected function utf8ToAscii($str, &$map)	{
		// Find all multibyte characters (cf. utf-8 encoding specs)
		$matches = [];
		if(!preg_match_all('/[\xC0-\xF7][\x80-\xBF]+/', $str, $matches)) {
			// Plain ascii string
			return $str;
		}
		// Update the encoding map with the characters not already met
		foreach($matches[0] as $mbc) {
			if(!isset($map[$mbc])) $map[$mbc] = chr(128 + count($map));
		}
		// Finally remap non-ascii characters
		return strtr($str, $map);
	}

}
