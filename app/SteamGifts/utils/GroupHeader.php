<?php
class GroupHeader {
	private $group_id;

	public $data = array(
		'id' => null,
		'steamid' => null,
		'steamid_str' => null,
		'name' => null,
		'first_giveaway' => null,
		'last_giveaway' => null,
		'average_entries' => null,
		'contributors' => null,
		'winners' => null,
		'gifts_sent' => null,
		'gifts_sent_value' => null,
		'users' => null,
		'giveaway_rows' => null
	);

	public function __construct($id) {
		$this->data['id'] = $id;
	}

	public function parse_header($html) {
		$this->data['name'] = $html->find('.featured__heading__medium', 0)->plaintext;

		foreach($html->find(".featured__table__row") as $elem) {
			switch($elem->children(0)->plaintext) {
				case 'First Giveaway':
					if ($elem->children(1)->children(0)->class !== null && $elem->children(1)->children(0)->class == "featured__online-now") {
						$this->data['first_giveaway'] = 0;
					} else {
						$this->data['first_giveaway'] = intval($elem->children(1)->children(0)->getAttribute('data-timestamp'));
					}
					break;
				case 'Last Giveaway':
					if ($elem->children(1)->children(0)->class !== null && $elem->children(1)->children(0)->class == "featured__online-now") {
						$this->data['last_giveaway'] = 0;
					} else {
						$this->data['last_giveaway'] = intval($elem->children(1)->children(0)->getAttribute('data-timestamp'));
					}
					break;
				case 'Average Entries':
					$this->data['average_entries'] = intval(str_replace(",", "", $elem->children(1)->plaintext));
					break;
				case 'Contributors':
					$this->data['contributors'] = intval(str_replace(",", "", $elem->children(1)->plaintext));
					break;
				case 'Winners':
					$this->data['winners'] = intval(str_replace(",", "", $elem->children(1)->plaintext));
					break;
				case 'Gifts Sent':
					$this->data['gifts_sent'] = intval(str_replace(",", "", $elem->children(1)->plaintext));

					$index = strpos($elem->children(1)->plaintext, " ");
					$this->data['gifts_sent_value'] = floatval(str_replace(array(",", ")"), "", substr($elem->children(1)->plaintext, $index + 3)));

					unset($index);
					break;
			}
		}

		// Get SteamID of the group:
		preg_match("/(\d+)/", $html->find(".sidebar__shortcut-inner-wrap a[href*='steamcommunity.com']", 0)->href, $steam_id);
		$this->data['steamid'] = intval($steam_id[1]);
		$this->data['steamid_str'] = $steam_id[1];
		unset($steam_id);


		foreach($html->find(".sidebar__navigation__item__link") as $elem) {
			switch($elem->find(".sidebar__navigation__item__name", 0)->plaintext) {
				case 'Giveaways':
					$this->data['giveaway_rows'] = intval(str_replace(",", "", $elem->find(".sidebar__navigation__item__count", 0)->plaintext));
					break;
				case 'Users':
					$this->data['users'] = intval(str_replace(",", "", $elem->find(".sidebar__navigation__item__count", 0)->plaintext));
					break;
			}
		}

		return $this->data;
	}

	public function save_data($db) {
		$stmt = $db->prepare("SELECT COUNT(*) AS count, id, group_sg_id, name, first_giveaway, last_giveaway, average_entries, contributors, winners, gifts_sent, gifts_sent_value, giveaway_rows, users, steamid64, unavailable, UNIX_TIMESTAMP(last_checked) AS last_checked FROM GroupsGeneral WHERE group_sg_id=:group_sg_id");
		$stmt->execute(array(
			':group_sg_id' => $this->data['id']
		));

		$group_row = $stmt->fetch(PDO::FETCH_ASSOC);
		unset($stmt);

		if ($group_row['count'] > 1) {
			$id_to_update = $group_row['id'];

			$stmt = $db->prepare("SELECT id FROM GroupsGeneral WHERE group_sg_id=:group_sg_id ORDER BY id");
			$stmt->execute(array(
				':group_sg_id' => $this->data['id']
			));

			$count = 0;

			while($duplicate_row = $stmt->fetch(PDO::FETCH_ASSOC)) {
				if ($count !== 0) {
					$stmt2 = $db->prepare("DELETE FROM GroupsGeneral WHERE id=:id");
					$stmt2->execute(array(
						':id' => $duplicate_row['id']
					));

					unset($stmt2);

					$count++;
				} else {
					if ($group_row['id'] !== $duplicate_row['id']) {
						$id_to_update = $duplicate_row['id'];
					}

					$count++;
				}
			}

			unset($stmt);
			unset($count);

			$stmt = $db->prepare("UPDATE GroupsGeneral SET group_sg_id=:group_sg_id, name=:name, first_giveaway=:first_giveaway, last_giveaway=:last_giveaway, average_entries=:average_entries, contributors=:contributors, winners=:winners, gifts_sent=:gifts_sent, gifts_sent_value=:gifts_sent_value, giveaway_rows=:giveaway_rows, users=:users, steamid64=:steamid64, unavailable=0, last_checked=NULL WHERE id=:id");
			$stmt->execute(array(
				':group_sg_id' => $this->data['id'],
				':name' => $this->data['name'],
				':first_giveaway' => $this->data['first_giveaway'],
				':last_giveaway' => $this->data['last_giveaway'],
				':average_entries' => $this->data['average_entries'],
				':contributors' => $this->data['contributors'],
				':winners' => $this->data['winners'],
				':gifts_sent' => $this->data['gifts_sent'],
				':gifts_sent_value' => $this->data['gifts_sent_value'],
				':giveaway_rows' => $this->data['giveaway_rows'],
				':users' => $this->data['users'],
				':steamid64' => $this->data['steamid'],
				':id' => $id_to_update
			));

		} else {
			$stmt = $db->prepare("INSERT INTO GroupsGeneral (group_sg_id, name, first_giveaway, last_giveaway, average_entries, contributors, winners, gifts_sent, gifts_sent_value, giveaway_rows, users, steamid64) VALUES (:group_sg_id, :name, :first_giveaway, :last_giveaway, :average_entries, :contributors, :winners, :gifts_sent, :gifts_sent_value, :giveaway_rows, :users, :steamid64) ON DUPLICATE KEY UPDATE group_sg_id2=:group_sg_id2, name2=:name2, first_giveaway2=:first_giveaway2, last_giveaway2=:last_giveaway2, average_entries2=:average_entries2, contributors2=:contributors2, winners2=:winners2, gifts_sent2=:gifts_sent2, gifts_sent_value2=:gifts_sent_value2, giveaway_rows2=:giveaway_rows2, users2=:users2, steamid642=:steamid642, unavailable=0, last_checked=NULL");

			$stmt->execute(array(
				':group_sg_id' => $this->data['id'],
				':name' => $this->data['name'],
				':first_giveaway' => $this->data['first_giveaway'],
				':last_giveaway' => $this->data['last_giveaway'],
				':average_entries' => $this->data['average_entries'],
				':contributors' => $this->data['contributors'],
				':winners' => $this->data['winners'],
				':gifts_sent' => $this->data['gifts_sent'],
				':gifts_sent_value' => $this->data['gifts_sent_value'],
				':giveaway_rows' => $this->data['giveaway_rows'],
				':users' => $this->data['users'],
				':steamid64' => $this->data['steamid'],
				':group_sg_id2' => $this->data['id'],
				':name2' => $this->data['name'],
				':first_giveaway2' => $this->data['first_giveaway'],
				':last_giveaway2' => $this->data['last_giveaway'],
				':average_entries2' => $this->data['average_entries'],
				':contributors2' => $this->data['contributors'],
				':winners2' => $this->data['winners'],
				':gifts_sent2' => $this->data['gifts_sent'],
				':gifts_sent_value2' => $this->data['gifts_sent_value'],
				':giveaway_rows2' => $this->data['giveaway_rows'],
				':users2' => $this->data['users'],
				':steamid642' => $this->data['steamid']
			));

			unset($stmt);
		}
	}
}
?>
