<?php
require_once(__DIR__ . '/SlimProj/app/SteamGifts/utils/dbconn.php');

$stmt = $db->query("SELECT id, giveawaysgeneral_id FROM GiveawaysDescriptions");

while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
	$stmt2 = $db->query("SELECT deleted, not_whitelisted, not_region, not_groups, not_wl_groups, unavailable FROM GiveawaysGeneral WHERE id=" . $row['giveawaysgeneral_id']);

	$general_row = $stmt2->fetch(PDO::FETCH_ASSOC);
	unset($stmt2);

	if ($general_row['deleted'] === 1 || $general_row['not_whitelisted'] === 1 || $general_row['not_region'] === 1 || $general_row['not_groups'] === 1 || $general_row['not_wl_groups'] === 1 || $general_row['unavailable'] === 1) {
		$stmt2 = $db->query("DELETE FROM GiveawaysDescriptions WHERE giveawaysgeneral_id=" . $row['giveawaysgeneral_id']);
	}
}

?>
