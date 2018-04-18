<?php
//TODO: Change titles and paths
require_once('utilities2.php');
require_once('dbconn.php');

//TODO: Change route
$app->get('/SteamGifts/Interactions/GetGameTitle45/', function($request, $response) {
	// Define the constant for max difference of time for cached data
	define('MAX_TIME_TITLE_CACHE', 86400); //24h

	global $db;

	$params = $request->getQueryParams();

	// Check if there's id argument and is valid
	if (isset($params['id']) && preg_match("/^[0-9]+$/", $params['id']) === 1) {
		$str_id = $params['id'];
		$id = intval($params['id']);
	} else {
		return $response->withHeader('Access-Control-Allow-Origin', '*')
		->withHeader('Content-type', 'application/json')->withJson(array(
		"errors" => array(
			"code" => 0,
			"description" => "Missing or invalid id argument"
		)), 400);
	}

	// Lists of valid type values
	$valid_values_type = array(
		'0' => null,
		'1' => null
	);
	// Check if there's type argument and is valid
	if (isset($params['type']) && array_key_exists($params['type'], $valid_values_type)) {
		$type = intval($params['type']);
	} else {
		return $response->withHeader('Access-Control-Allow-Origin', '*')
		->withHeader('Content-type', 'application/json')->withJson(array(
		"errors" => array(
			"code" => 1,
			"description" => "Missing or invalid type argument"
		)), 400);
	}

	// Retrieve the local data of the game title if it exists
	$sql_string = "SELECT COUNT(*) AS count, id, game_title, unavailable, UNIX_TIMESTAMP(last_checked) AS last_checked FROM GameTitles WHERE game_type=:game_type AND game_id=:game_id";
	$stmt = $db->prepare($sql_string);
	$stmt->execute(array(
		':game_type' => $type,
		':game_id' => $id
	));
	$gametitles_row = $stmt->fetch(PDO::FETCH_ASSOC);

	unset($sql_string);
	unset($stmt);


	// Template of the successful JSON response
	$data = array(
		'id' => null,
		'game_id' => $id,
		'game_type' => $type,
		'game_title' => null
	);


	if ($gametitles_row['count'] === 0 || (time() - $gametitles_row['last_checked']) >= MAX_TIME_TITLE_CACHE) {
		// There's either no local data or is outdated
		$json;

		if ($type == 0) {
			$json = APIRequests::generic_get_request("http://store.steampowered.com/api/appdetails?appids=" . $id . "&filters=basic");

		} else {
			$json = APIRequests::generic_get_request("http://store.steampowered.com/api/packagedetails/?packageids=" . $id);
		}

		if ($json->status_code !== 200) {
			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')->withJson(array(
			"errors" => array(
				"code" => 0,
				"description" => "The request to Steam was unsuccessful"
			)), 500);
		}

		$json = json_decode($json->body, true);

		// If the Steam API response is false we return a 500 error and store
		//that it's unavailable
		if ($json[$str_id]['success'] === false) {
			if ($gametitles_row['count'] === 0) {
				$stmt = $db->prepare("INSERT INTO GameTitles (game_type, game_id, unavailable) VALUES (:game_type, :game_id, :unavailable)");
				$stmt->execute(array(
					':game_type' => $type,
					':game_id' => $id,
					':unavailable' => 1
				));
				unset($stmt);
			} else {
				$stmt = $db->prepare("UPDATE GameTitles SET unavailable=:unavailable, last_checked=NULL WHERE game_id=:game_id AND game_type=:game_type");
				$stmt->execute(array(
					':unavailable' => 1,
					':game_type' => $type,
					':game_id' => $id
				));
				unset($stmt);
			}

			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')->withJson(array(
			"errors" => array(
				"code" => 1,
				"description" => "Game inexistant or not available in the server's region",
				"game_title" => $gametitles_row['game_title']
			)), 500);
		}

		// At this point the Steam API response was successful
		$data['game_title'] = $json[$str_id]['data']['name'];
		$title = $data['game_title'];


		if ($gametitles_row['count'] === 1 && ($gametitles_row['game_title'] == $title || $gametitles_row['unavailable'] === 1)) {
			// There was that info but the data was outdated. Title is still the
			//same or unavailable was 1
			$db->query("UPDATE GameTitles SET unavailable=0, last_checked=NULL WHERE id=" . $gametitles_row['id']);

			$data['id'] = $gametitles_row['id'];
		} elseif ($gametitles_row['count'] === 1 && ($gametitles_row['game_title'] != $title || $gametitles_row['unavailable'] === 1)) {
			// There was that info but the data was outdated. Title isn't the
			//same or unavailable was 1
			$sql_string = "UPDATE GameTitles SET game_title=:game_title, last_checked=NULL, unavailable=:unavailable WHERE id=:id";
			$stmt = $db->prepare($sql_string);
			$stmt->execute(array(
				':game_title' => $title,
				':id' => $gametitles_row['id'],
				':unavailable' => 0
			));

			$data['id'] = $gametitles_row['id'];

			unset($sql_string);
			unset($stmt);
		} else {
			// There was no data, INSERT.
			$sql_string = "INSERT INTO GameTitles (game_type, game_id, game_title, unavailable) VALUES (:game_type, :game_id, :game_title, :unavailable)";
			$stmt = $db->prepare($sql_string);
			$stmt->execute(array(
				':game_type' => $type,
				':game_id' => $id,
				':game_title' => $title,
				':unavailable' => 0
			));

			$inserted_id = $db->query("SELECT LAST_INSERT_ID() AS inserted_id");
			$inserted_id = $inserted_id->fetch(PDO::FETCH_ASSOC);
			$data['id'] = $inserted_id['inserted_id'];

			unset($sql_string);
			unset($stmt);
		}

		unset($json);
	} else {
		// There was data and wasn't outdated, if unavailable is 1 we return 500
		//else we store the stored title
		if ((bool)$gametitles_row['unavailable']) {
			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')->withJson(array(
			"errors" => array(
				"code" => 1,
				"description" => "Game inexistant or not available in the server's region",
				"game_title" => $gametitles_row['game_title']
			)), 500);
		}

		$data['id'] = $gametitles_row['id'];
		$data['game_title'] = $gametitles_row['game_title'];
	}

	return $response->withHeader('Access-Control-Allow-Origin', '*')
	->withHeader('Content-type', 'application/json')
	->withJson($data, 200);
});
?>
