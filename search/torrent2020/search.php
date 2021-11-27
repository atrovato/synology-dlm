<?php

class Torrent2020 {

	const REG_RESULT = '#<div class="card mb-2">\s*<div[^>]+>\s*<h4[^>]+>(?<title>[^<]+)</h4>\s*<div[^>]+>\s*<div[^>]+><i[^>]+></i>&nbsp;<a[^>]+>(?<category>[^<]+)</a></div>\s*<div[^>]+>[^<]+</div>\s*</div>\s*<img[^>]+>\s*<p[^>]+>((?!</p>).)+</p>\s*<p[^>]+>\s*<span[^>]+><i[^>]+></i>&nbsp;Seeders:</span>&nbsp;<span[^>]+>(?<seed>[0-9]+)</span> &nbsp;&nbsp; <span[^>]+><i[^>]+></i>&nbsp;Leechers:</span>&nbsp;<span[^>]+>(?<leech>[0-9]+)</span>\s*</p>\s*<p[^>]+><a href="(?<page>[^"]+)"[^>]+><i[^>]+></i>&nbsp;Torrent \((?<size>[^ ]+)( ?)(?<unit>[^)]+)\)</a>\s*<span[^>]+>[^<]+</span></p>\s*</div>\s*</div>#ims';
	const REG_INFO_RESULT = '#href="(?<magnet>magnet:[^"]+)"#i';
	const REG_TOKEN = '#<meta name="csrf-token" content="(?<token>[^"]+)">#i';

	const HEADERS = array(
		"authority: torrent2020.fr",
		"cache-control: max-age=0",
		"sec-ch-ua-mobile: ?0",
		"upgrade-insecure-requests: 1",
		"origin: https://torrent2020.fr",
		"content-type: application/x-www-form-urlencoded",
		"user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Safari/537.36",
		"accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9",
		"sec-fetch-site: same-origin",
		"sec-fetch-mode: navigate",
		"sec-fetch-user: ?1",
		"sec-fetch-dest: document",
		"referer: https://torrent2020.fr/torrent/breaking-bad-integrale-french-hdtv-23go-c29f8e7ea4968d02a49080b0dc90a8909f8161bc-28353.html",
		"accept-language: fr-FR,fr;q=0.9",
		"cookie: _ym_uid=1630599889435625744; _ym_d=1630599889; _ym_isad=1; XSRF-TOKEN=eyJpdiI6IkdDdDJLdzZjZWZvSWJZNS9KOGpFZlE9PSIsInZhbHVlIjoiU2llbVAyWTJ2MzV0eCswaVgzY250OG5HdUx5YlAwR0pkeUs1dVcxTTkwVVRDY0FhUDhnOU83aWZkbmxpc0llYiIsIm1hYyI6IjFkNTZmY2U4OWNmYThhMGUzYmU0ZmYyNGM4ZDIyNmRlMmRmZWRkYzM2OWI4ODg2MzczNjk0ZGRjOGZjMDY0NGMifQ%3D%3D; torrent_francais_2020_session=eyJpdiI6IklCMUVJYzA4NDBJY3FFVDVPWTlnaVE9PSIsInZhbHVlIjoiME5GejhWRzZHcVV6SmxsclI0aUhrdVJLSHFXaVIreWtFSHdsdTBDQ0c2UWYyUkJjUU4yV0JBZk5sekJucHNMUiIsIm1hYyI6IjViZTkxMWM4YzVhMWM0MjI0OGU0ZGMxOWEyYTg3NjRjOTgwM2E5Y2Q5NjZkMDY1YjA2MzViZjVjMmY5NzQzNGYifQ%3D%3D; __atuvc=24%7C35; __atuvs=6131cc9b263268d6001",
	);

	private $domain = 'https://torrent2020.fr';
	private $qurl = '/torrents/recherche';
	private $token;
	public $max_results = 0;
	public $verbose = false;

	public function prepare($curl, $query) {
		$url = $this->domain . $this->qurl;

		$token_curl = curl_init();
		curl_setopt($token_curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($token_curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($token_curl, CURLOPT_URL, $this->domain);
		curl_setopt($token_curl, CURLOPT_HTTPHEADER, self::HEADERS);
		$token_response = curl_exec($token_curl);
		curl_close($token_curl);

		$nb_res = preg_match_all(self::REG_TOKEN, $token_response, $token, PREG_SET_ORDER);
		if ($nb_res > 0) {
			$this->token = $token[0]['token'];
		}

		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, self::HEADERS);

		$data = "_token=" . $this->token . "&request=" . $query;
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
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
		case 'KO':
			return $size * 1024;
		case 'MO':
			return $size * 1024 * 1024;
		case 'GO':
			return $size * 1024 * 1024 * 1024;
		case 'TO':
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
				curl_setopt($curl, CURLOPT_HTTPHEADER, self::HEADERS);
				$info_response = curl_exec($curl);
				curl_close($curl);

				$nb_res = preg_match_all(self::REG_INFO_RESULT, $info_response, $info, PREG_SET_ORDER);
				if ($nb_res > 0) {
					$plugin->addResult(
						$row['title'],  // title
						$info[0]['magnet'],
						$this->sizeInBytes(intval($row['size']), $row['unit']),  // size in bytes
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
