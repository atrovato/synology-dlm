<?php

class Torrent9 {

	const REG_RESULT = '#<tr>\s*<td><i class="(?<category>[^"]+)"[^>]*></i>\s*<a[^>]* href="(?<page>[^"]+)[^>]*>(?<title>.+)</a></td>[^>]*<td[^>]*>(?<size>[^ <]+)( ?)(?<unit>[^<]+)</td>\s*<td[^>]*><span class="seed_n?ok">(?<seed>[0-9]+).*</span></td>\s*<td[^>]*>(?<leech>[0-9]+).*</td>\s*</tr>#i';
	const REG_INFO_RESULT = "#<div class='download-btn'><a class='btn btn-danger download' href='(?<magnet>magnet:[^']+)'#i";

	private $domain = 'https://www.torrent9.pw';
	private $qurl = '/recherche/';
	public $max_results = 0;
	public $verbose = false;

	public function prepare($curl, $query) {
		$url = $this->domain . $this->qurl . urlencode($query);
		curl_setopt($curl, CURLOPT_URL, $url);
	}

	/**
	 * Returns a size in bytes
	 * 
	 * @param size 		unmodified size (e.g. 1)
	 * @param modifier	modifier (e.g. 'KB', 'MB', 'GB', 'TB')
	 * @return bytesize	size in bytes (e.g. 1,048,576)
	 */
	private function sizeInBytes($size, $modifier) {
		switch (strtoupper($modifier)) {
		case 'KB':
			return $size * 1024;
		case 'MB':
			return $size * 1024 * 1024;
		case 'GB':
			return $size * 1024 * 1024 * 1024;
		case 'TB':
			return $size * 1024 * 1024 * 1024 * 1024;
		default:
			return $size;
		}
	}

	public function parse($plugin, $response) {
		if (!($result_count = preg_match_all(self::REG_RESULT, $response, $rows, PREG_SET_ORDER))) {
			if ($this->verbose) {
				echo "Parsing: no matches found using regx '" . self::REG_RESULT . "'\n";
				return 0;
			}
		} else {
			if ($this->verbose) {
				echo "Parsing: found $result_count matches.\n";
			}

			/* Get all the row data -- up to max_results */
			$count = 0;
			foreach ($rows as $row) {
				$url = $this->domain . $row['page'];

				$curl = curl_init();
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
				curl_setopt($curl, CURLOPT_URL, $url);
				$info_response = curl_exec($curl);
				curl_close($curl);

				$nb_res = preg_match_all(self::REG_INFO_RESULT, $info_response, $info, PREG_SET_ORDER);
				if ($nb_res > 0) {
					$plugin->addResult(
						$row['title'],  // title
						$info[0]['magnet'],
						$this->sizeInBytes($row['size'], $row['unit']),  // size in bytes
						'',  // date (e.g. "2017-05-03 12:05:02")
						$url,  // url to torrent page referring this torrent
						$count,  // hash
						$row['seed'],  // seeds
						$row['leech'],  // leechs
						$row['category']); // category
			
					$count++;
					if ($this->max_results > 0 && $count == $this->max_results) {
						break;
					}
				} else if ($this->verbose) {
					echo "Parsing: no detailled info matches found using regx '" . self::REG_INFO_RESULT . "'\n";
				}
			}
			return $count;
		}
	}
}
?>
