<?php

class CPasBien {

	const REG_RESULT = '#<tr><td><a title="(?<title>[^"]+)" href="(?<page>[^"]+)"[^>]+>[^<]+</a>\s*<div[^>]+>(?<size>\d+\.?\d+)\W?(?<unit>[^<]+)</div>\s*<div class="up"><span class="seed_ok">(?<seed>\d+)</span></div>\s*<div class="down">(?<leech>\d+)</div></td></tr>#i';
	const REG_CATEGORY = '#https://wwvw.cpasbien-site.fr/(?<category>[^/"]+)/#i';
  const REG_INFO_RESULT = '#<a href="(?<magnet>magnet:[^"]+)"#i';

	private $domain = 'https://wwvw.cpasbien-site.fr';
	private $qurl = '/index.php?do=search&subaction=search';
	public $max_results = 0;
	public $verbose = false;

	public function prepare($curl, $query) {
		$url = $this->domain . $this->qurl;

		curl_setopt_array($curl, [
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => "story=" . $query,
			CURLOPT_HTTPHEADER => [
				"content-type: application/x-www-form-urlencoded"
			],
		]);
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
				$url = $row['page'];

				$curl = curl_init();
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
				curl_setopt($curl, CURLOPT_URL, $url);
				$info_response = curl_exec($curl);
				curl_close($curl);

        $nb_cat = preg_match_all(self::REG_CATEGORY, $info_response, $cat, PREG_SET_ORDER);
        $category = '';
        if ($cat > 0) {
          $category = $cat[0]['category'];
        }

				$nb_res = preg_match_all(self::REG_INFO_RESULT, $info_response, $info, PREG_SET_ORDER);
				if ($nb_res > 0) {
					$plugin->addResult(
						$row['title'],	// title
						str_replace(' ', '%20', $info[0]['magnet']),
						$this->sizeInBytes($row['size'], $row['unit']),	// size in bytes
						'',	// date (e.g. "2017-05-03 12:05:02")
						$url,	// url to torrent page referring this torrent
						$count,	// hash
						$row['seed'],	// seeds
						$row['leech'],	// leechs
						$category); // category
			
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
