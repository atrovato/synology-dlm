<?php

class EmuleIsland {

	const REG_RESULT = '#<a style="[^"]+" href="(?<page>[^"].+)" (title|alt)="(?<title>[^"].+)" class="poster\s+([^"]+)">#i';
	const REG_LINK_CATEGORY = '#/(?<category>\w+)/#i';

	const REG_SERIE_SEASON = '#<button class="dropdown-item season-btn"\s+alt="(?<season>\d+)" type="button">(?<number>[^<]+)</button>#i';
	const REG_SERIE_ID = '#<a href="/player/pre/e/(?<episode>\d+)/[^"]+" class="episode"#i';

	const REG_MOVIE_ID = '#/movie/(?<movie>\d+)/#i';

	const REG_DL_LINK = '#<tr>\s*<td>(?<site>[^<].+)<\/td>\s*<td><\/td>\s*<td><label([^<]+)<\/label>\s*<\/td>\s*<td>\s*<a\s+href="(?<link>[^"]+)"#i';

	const HEADERS = array(
		"accept: */*",
    "accept-language: fr-FR,fr;q=0.9",
    "cache-control: no-cache",
    "pragma: no-cache",
    "sec-ch-ua: \" Not A;Brand\";v=\"99\", \"Chromium\";v=\"96\", \"Google Chrome\";v=\"96\"",
    "sec-ch-ua-mobile: ?0",
    "sec-ch-ua-platform: \"Windows\"",
    "sec-fetch-dest: empty",
    "sec-fetch-mode: cors",
    "sec-fetch-site: same-origin",
    "x-requested-with: XMLHttpRequest"
	);

	private $domain = 'https://emule-island.eu';
	private $qurl = '/search?q=';
	public $max_results = 0;
	public $verbose = false;

	public function prepare($curl, $query) {
		$url = $this->domain . $this->qurl . urlencode($query);
		if ($this->verbose) {
			echo "Search at " . $url . "\n";
		}
		curl_setopt($curl, CURLOPT_URL, $url);
	}

	public function parseSerie($plugin, $row, $count) {
		$serie_page = $this->domain . $row['page'];
		$seasons = $this->loadPage($serie_page);

		// Parse links
		if ($result_count = preg_match_all(self::REG_SERIE_SEASON, $seasons, $lines, PREG_SET_ORDER)) {
			foreach ($lines as $line) {
				$count = $this->parseSeasonSerie($plugin, $row, $count, $line);
			}
		} else if ($this->verbose) {
			echo "No seasons found for serie " . $row['title'] . "\n";
		}

		return $count;
	}
	
	public function parseSeasonSerie($plugin, $row, $count, $line) {
		$season_page = $this->domain . '/ajax/episodes/' . $line['season'] . '.html';
		$response = $this->loadPage($season_page);

		if ($this->verbose) {
			echo "Looking for serie " . $row['title'] . " - " . $season_page . "\n";
		}

		// Parse links
		if ($result_count = preg_match_all(self::REG_SERIE_ID, $response, $lines, PREG_SET_ORDER)) {
			foreach ($lines as $line) {
				$count = $this->extractLinkPage($plugin, $row, $count, $line['episode'], 'serie', 'Series');
			}
		} else if ($this->verbose) {
			echo "No download link found for serie " . $row['title'] . "\n";
		}
		return $count;
	}

	public function parseMovie($plugin, $row, $count) {
		$page = $row['page'];
		$movieId = null;
		if (preg_match_all(self::REG_MOVIE_ID, $page, $movieInfo, PREG_SET_ORDER)) {
			$movieId = $movieInfo[0]['movie'];
		}

		$count = $this->extractLinkPage($plugin, $row, $count, $movieId, 'movie', 'Films');

		return $count;
	}

	public function extractLinkPage($plugin, $row, $count, $videoId, $videoType, $videoLabel) {
		if ($videoId != null) {
			$link_page = $this->domain . "/ajax/" . $videoType . "/downloads/" . $videoId . ".html";
			$response = $this->loadPage($link_page);

			if ($this->verbose) {
				echo "Looking for " . $videoType . " " . $row['title'] . " - " . $videoId . " - " . $link_page . "\n";
			}
			
			// Parse links
			if ($result_count = preg_match_all(self::REG_DL_LINK, $response, $lines, PREG_SET_ORDER)) {
				foreach ($lines as $line) {
					if ($line['site'] != 'Embed') {
						$title = '[' . $line['site'] . '] ' . $row['title'];
						$this->addResult($plugin, $title, $row['page'], $line['link'], $count++, $videoLabel);
					}
				}
			} else if ($this->verbose) {
				echo "No download link found for " . $videoType . " " . $row['title'] . "\n";
			}
		}

		return $count;
	}

	public function loadPage($url) {
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_HTTPHEADER, self::HEADERS);
		$info_response = curl_exec($curl);
		curl_close($curl);

		return $info_response;
	}

	public function addResult($plugin, $title, $page, $link, $count, $category) {
		$plugin->addResult(
			$title,  // title
			$link,
			1000,  // size in bytes
			'',  // date (e.g. "2017-05-03 12:05:02")
			$this->domain . $page,  // url to torrent page referring this torrent
			$count,  // hash
			1000,  // seeds
			0,  // leechs
			$category); // category
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

				$category = '';
				if (preg_match_all(self::REG_LINK_CATEGORY, $url, $categories, PREG_SET_ORDER)) {
					$category = $categories[0]['category'];
				}

				switch ($category) {
					case "serie":
						$count = $this->parseSerie($plugin, $row, $count);
						break;
					case "movie":
						$count = $this->parseMovie($plugin, $row, $count);
						break;
					default: 
					if ($this->verbose) {
						echo "Category " . $category . " not managed, search result is ignored\n";
					}
				}
				if ($this->max_results > 0 && $count == $this->max_results) {
					break;
				}
			}
			return $count;
		}
	}
}
?>
