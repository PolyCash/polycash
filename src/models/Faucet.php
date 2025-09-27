<?php
class Faucet {
	public static $fieldsInfo = [
		'faucet_enabled' => [],
		'everyone_eligible' => [],
		'approval_method' => [],
		'txo_size' => [],
		'display_from_name' => [
			'stripTags' => true,
		],
		'bonus_claims' => [],
		'sec_per_faucet_claim' => [],
		'max_claims_at_once' => [],
		'min_sec_between_claims' => [],
	];

	public static function fetchFaucetsManagedByUser(&$app, &$thisuser, $only_game_id = null) {
		$fetchQuery = "SELECT f.*, ca.* FROM faucets f JOIN currency_accounts ca ON f.account_id=ca.account_id WHERE f.user_id=:user_id";
		$fetchParams = [
			'user_id' => $thisuser->db_user['user_id'],
		];
		if ($only_game_id) {
			$fetchQuery .= " AND f.game_id=:game_id";
			$fetchParams['game_id'] = $only_game_id;
		}
		return $app->run_query($fetchQuery, $fetchParams)->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public static function fetchById(&$app, $faucet_id) {
		return $app->run_query("SELECT f.*, ca.* FROM faucets f JOIN currency_accounts ca ON f.account_id=ca.account_id WHERE f.faucet_id = :faucet_id;", [
			'faucet_id' => $faucet_id,
		])->fetch(PDO::FETCH_ASSOC);
	}
	
	public static function fetchByAccountId($app, $account_id) {
		return $app->run_query("SELECT f.*, ca.* FROM faucets f JOIN currency_accounts ca ON f.account_id=ca.account_id WHERE f.account_id = :account_id;", [
			'account_id' => $account_id,
		])->fetch(PDO::FETCH_ASSOC);
	}
	
	public static function myFaucetReceivers($app, $user_id, $game_id) {
		return $app->run_query("SELECT r.*, f.*, ca.account_name FROM faucet_receivers r JOIN faucets f ON r.faucet_id=f.faucet_id JOIN currency_accounts ca ON f.account_id=ca.account_id WHERE r.user_id=:user_id AND f.game_id=:game_id;", [
			'user_id' => $user_id,
			'game_id' => $game_id,
		])->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public static function faucetReceiveInfo($my_faucet_receiver, $overrideTime = null) {
		$checkAtTime = $overrideTime ? $overrideTime : time();

		$most_recent_claim_time = $my_faucet_receiver['latest_claim_time'];
		$user_faucet_claims = $my_faucet_receiver['faucet_claims'];
		$eligible_for_faucet = false;
		$time_available = false;
		$num_claims_now = 0;

		$sec_per_faucet_claim = $my_faucet_receiver['sec_per_faucet_claim'];
		$min_sec_between_claims = $my_faucet_receiver['min_sec_between_claims'];

		$sec_since_joined = $checkAtTime - $my_faucet_receiver['join_time'];
		$total_claims_for_user = floor($sec_since_joined/$sec_per_faucet_claim) + $my_faucet_receiver['bonus_claims'];

		$owed_claims = $total_claims_for_user - $user_faucet_claims;

		$seeking_claim = $user_faucet_claims+1;
		$seeking_claim_after_bonus = $seeking_claim - $my_faucet_receiver['bonus_claims'];

		$time_claim_available = $my_faucet_receiver['join_time'] + ($seeking_claim_after_bonus*$sec_per_faucet_claim);

		if ($owed_claims > 0) {
			$sec_since_last_claim = $checkAtTime - $most_recent_claim_time;

			if ($sec_since_last_claim >= $min_sec_between_claims) {
				$max_claims_at_once = (string) $my_faucet_receiver['max_claims_at_once'] === "" ? 1 : max(1, $my_faucet_receiver['max_claims_at_once']);
				$num_claims_now = min($owed_claims, $max_claims_at_once);

				$eligible_for_faucet = true;
				$time_available = $checkAtTime;
			} else {
				$time_available = $most_recent_claim_time + $min_sec_between_claims;
				$eligible_for_faucet = false;
			}
		} else {
			$eligible_for_faucet = false;
			$time_available = $time_claim_available;
		}

		return [
			$eligible_for_faucet,
			$time_available,
			$num_claims_now,
		];
	}
	
	public static function getReceivableTxosFromFaucet(&$app, &$game, $my_faucet_receiver = null, $faucet = null, $quantity = 1) {
		if ($faucet['faucet_enabled'] != 1) return [];

		$eligible_for_faucet = false;

		if ($my_faucet_receiver) {
			list($eligible_for_faucet, $time_available, $num_claims_now) = self::faucetReceiveInfo($my_faucet_receiver);
		}

		if (!empty($my_faucet_receiver) && !$eligible_for_faucet) return [];

		// Only give out coins when the game is fully loaded
		if (empty($my_faucet_receiver) || $game->last_block_id() >= $game->blockchain->last_block_id()-1) {
			return $app->run_limited_query("SELECT *, SUM(gio.colored_amount) AS colored_amount_sum FROM address_keys k JOIN transaction_game_ios gio ON gio.address_id=k.address_id JOIN transaction_ios io ON gio.io_id=io.io_id WHERE gio.game_id=:game_id AND k.account_id=:account_id AND io.spend_status IN ('unspent', 'unconfirmed') GROUP BY k.address_id ORDER BY colored_amount_sum DESC, gio.game_io_index ASC LIMIT :quantity;", [
				'game_id' => $game->db_game['game_id'],
				'account_id' => $faucet['account_id'],
				'quantity' => $quantity,
			])->fetchAll(PDO::FETCH_ASSOC);
		}
		else return [];
	}
	
	public static function claimMaxFromFaucet(&$app, &$game, $to_user_game, $my_faucet_receiver, $faucet) {
		$keep_claiming = true;
		$claim_count = 0;

		do {
			$my_faucet_receiver = self::myReceiverById($app, $my_faucet_receiver['receiver_id']);
			list($eligible_for_faucet, $time_available, $num_claims_now) = self::faucetReceiveInfo($my_faucet_receiver);

			$faucet_ios = self::getReceivableTxosFromFaucet($app, $game, $my_faucet_receiver, $faucet, $num_claims_now);

			if (count($faucet_ios) > 0) {
				foreach ($faucet_ios as $faucet_io) {
					$app->run_query("UPDATE address_keys SET account_id=:account_id WHERE address_key_id=:address_key_id;", [
						'account_id' => $to_user_game['account_id'],
						'address_key_id' => $faucet_io['address_key_id']
					]);
					$app->run_query("UPDATE addresses SET user_id=:user_id WHERE address_id=:address_id;", [
						'user_id' => $to_user_game['user_id'],
						'address_id' => $faucet_io['address_id']
					]);
					$app->run_query("UPDATE faucet_receivers SET faucet_claims=faucet_claims+1, latest_claim_time=:latest_claim_time WHERE receiver_id=:receiver_id;", [
						'receiver_id' => $my_faucet_receiver['receiver_id'],
						'latest_claim_time' => time()
					]);

					$claim_count++;
				}
				if ($claim_count > 2) $keep_claiming = false;
			} else {
				$keep_claiming = false;
			}
		}
		while ($keep_claiming);

		return $claim_count;
	}
	
	public static function myReceiverForFaucet(&$app, $user_id, $faucet) {
		return $app->run_query("SELECT r.*, f.*, ca.account_name FROM faucet_receivers r JOIN faucets f ON r.faucet_id=f.faucet_id JOIN currency_accounts ca ON f.account_id=ca.account_id WHERE r.user_id=:user_id AND f.faucet_id=:faucet_id ORDER BY r.receiver_id ASC LIMIT 1;", [
			'user_id' => $user_id,
			'faucet_id' => $faucet['faucet_id'],
		])->fetch(PDO::FETCH_ASSOC);
	}
	
	public static function myReceiverById($app, $receiver_id) {
		return $app->run_query("SELECT r.*, f.*, ca.account_name FROM faucet_receivers r JOIN faucets f ON r.faucet_id=f.faucet_id JOIN currency_accounts ca ON f.account_id=ca.account_id WHERE r.receiver_id=:receiver_id;", [
			'receiver_id' => $receiver_id,
		])->fetch(PDO::FETCH_ASSOC);
	}
	
	public static function eligibleFaucetReceivers(&$app, &$game, array $exclude_faucet_ids) {
		$query = "SELECT * FROM faucets WHERE everyone_eligible=1 AND faucet_enabled=1 AND game_id=:game_id";
		if (count($exclude_faucet_ids) > 0) $query .= " AND faucet_id NOT IN ('".implode("','", $exclude_faucet_ids)."')";
		$query .= " ORDER BY faucet_id ASC";
		return $app->run_query($query, [
			'game_id' => $game->db_game['game_id'],
		])->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public static function getMaxClaimAmount(&$app, $user_id, &$game) {
		$claim_amount_int = 0;

		if ($user_id) {
			$my_faucet_receivers = Faucet::myFaucetReceivers($app, $user_id, $game->db_game['game_id']);
			$my_faucet_receivers_by_faucet_id = [];
			foreach ($my_faucet_receivers as $my_faucet_receiver) {
				$my_faucet_receivers_by_faucet_id[$my_faucet_receiver['faucet_id']] = $my_faucet_receiver;
			}
		} else {
			$my_faucet_receivers_by_faucet_id = [];
		}
		$exclude_faucet_ids = array_keys($my_faucet_receivers_by_faucet_id);
		$eligible_faucet_receivers = Faucet::eligibleFaucetReceivers($app, $game, $exclude_faucet_ids);

		foreach ($my_faucet_receivers_by_faucet_id as $faucet_id => $my_faucet_receiver) {
			list($eligible_for_faucet, $time_available, $num_claims_now) = Faucet::faucetReceiveInfo($my_faucet_receiver);
			
			if ($num_claims_now > 0) {
				$faucet_ios = self::getReceivableTxosFromFaucet($app, $game, $my_faucet_receiver, $my_faucet_receiver, $num_claims_now);
				foreach ($faucet_ios as $faucet_io) {
					$claim_amount_int += $faucet_io['colored_amount_sum'];
				}
			}
		}

		foreach ($eligible_faucet_receivers as $eligible_faucet) {
			$num_claims_now = min($eligible_faucet['bonus_claims'], $eligible_faucet['max_claims_at_once']);
			
			if ($num_claims_now > 0) {
				$faucet_ios = Faucet::getReceivableTxosFromFaucet($app, $game, null, $eligible_faucet, $num_claims_now);
				foreach ($faucet_ios as $faucet_io) {
					$claim_amount_int += $faucet_io['colored_amount_sum'];
				}
			}
		}

		return $claim_amount_int;
	}
	
	public static function fetchJoinRequestById($app, $join_request_id) {
		return $app->run_query("SELECT * FROM faucet_join_requests WHERE request_id=:request_id;", [
			'request_id' => $join_request_id,
		])->fetch(PDO::FETCH_ASSOC);
	}
	
	public static function joinAndRequestAllEligibleFaucetsInGame(&$app, &$thisuser, &$game) {
		$my_faucet_receivers = Faucet::myFaucetReceivers($app, $thisuser->db_user['user_id'], $game->db_game['game_id']);

		$exclude_faucet_ids = [];
		foreach ($my_faucet_receivers as $my_faucet_receiver) {
			$exclude_faucet_ids[] = $my_faucet_receiver['faucet_id'];
		}

		$eligible_faucets = Faucet::eligibleFaucetReceivers($app, $game, $exclude_faucet_ids);

		$joined_any_faucets = false;
		if (count($eligible_faucets) > 0) {
			foreach ($eligible_faucets as $eligible_faucet) {
				if ($eligible_faucet['approval_method'] == "auto_approve") {
					$app->run_insert_query("faucet_receivers", [
						'faucet_id' => $eligible_faucet['faucet_id'],
						'user_id' => $thisuser->db_user['user_id'],
						'join_time' => time(),
					]);
					$new_receiver = Faucet::myReceiverById($app, $app->last_insert_id());
					
					$join_request = $app->run_insert_query("faucet_join_requests", [
						'faucet_id' => $eligible_faucet['faucet_id'],
						'user_id' => $thisuser->db_user['user_id'],
						'game_id' => $game->db_game['game_id'],
						'request_time' => time(),
						'approve_time' => time(),
						'receiver_id' => $new_receiver['receiver_id'],
					]);

					$joined_any_faucets = true;
				} else if ($eligible_faucet['approval_method'] == "request_to_join") {
					$recent_join_request = $app->run_query("SELECT * FROM faucet_join_requests WHERE user_id=:user_id AND faucet_id=:faucet_id AND request_time >= :since_time ORDER BY request_id ASC LIMIT 1;", [
						'user_id' => $thisuser->db_user['user_id'],
						'faucet_id' => $eligible_faucet['faucet_id'],
						'since_time' => time() - (3600*2),
					])->fetch(PDO::FETCH_ASSOC);

					if (! $recent_join_request) {
						$faucet_creator = $app->fetch_user_by_id($eligible_faucet['user_id']);

						$app->run_insert_query("faucet_join_requests", [
							'faucet_id' => $eligible_faucet['faucet_id'],
							'user_id' => $thisuser->db_user['user_id'],
							'game_id' => $game->db_game['game_id'],
							'request_time' => time(),
						]);
						$join_request = Faucet::fetchJoinRequestById($app, $app->last_insert_id());

						$delivery_key = $app->random_string(16);

						$request_to_join_subject = "User ".$thisuser->db_user['first_name']." ".$thisuser->db_user['last_name']." is eligible for your faucet #".$eligible_faucet['faucet_id'].", ".$eligible_faucet['display_from_name'];

						$request_to_join_message = $app->render_view("request_join_faucet_mail", [
							'game' => $game,
							'user' => $thisuser->db_user,
							'faucet' => $eligible_faucet,
							'join_request' => $join_request,
						]);

						$app->mail_async($faucet_creator['username'], AppSettings::getParam('site_name'), AppSettings::defaultFromEmailAddress(), $request_to_join_subject, $request_to_join_message, "", "", $delivery_key);
					}
				}
			}
		}

		return $joined_any_faucets;
	}
}
