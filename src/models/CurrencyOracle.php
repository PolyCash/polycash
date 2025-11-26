<?php
class CurrencyOracle {
	public static $oracleInfo = [
		'coin-desk' => [
			'oracle-identifier' => 'coin-desk',
			'requiresApiKey' => false,
		],
		'fcs-api' => [
			'oracle-identifier' => 'fcs-api',
			'requiresApiKey' => true,
		],
	];
	
	public static function getConfiguredOracles(&$app, $print_debug=true) {
		$oracle_config = AppSettings::getParam("oracles");
		$config_pos = 0;
		$configured_oracles = [];

		if ($oracle_config == null) {
			if ($print_debug) $app->print_debug("No oracles are listed in your config file.");
			return [];
		}
		
		foreach ($oracle_config as $supplied_info) {
			$supplied_info = (array) $supplied_info;

			if (empty($supplied_info['oracle'])) {
				if ($print_debug) $app->print_debug("Oracle #".$config_pos." does not have a value for 'oracle' set.");
				break;
			}

			if (!isset(CurrencyOracle::$oracleInfo[$supplied_info['oracle']])) {
				if ($print_debug) $app->print_debug("Oracle #".$config_pos." was not found in the CurrencyOracle class.");
				break;
			}

			if (empty($supplied_info['selector_type'])) {
				if ($print_debug) $app->print_debug("Oracle #".$config_pos." (".$supplied_info['oracle'].") does not have a 'selector_type' set.");
				break;
			}

			$oracle_info = CurrencyOracle::$oracleInfo[$supplied_info['oracle']];
			
			if (!empty($oracle_info['requiresApiKey']) && empty($supplied_info['api_key'])) {
				if ($print_debug) $app->print_debug("Oracle #".$config_pos." (".$supplied_info['oracle'].") does not have an 'api_key' set.");
				break;
			}
			
			if ($supplied_info['selector_type'] == "single") {
				$currency = $app->fetch_currency_by_abbreviation($supplied_info['currency']);
				
				if ($currency) {
					array_push($configured_oracles, [
						'oracle_pos' => $config_pos,
						'selector_type' => $supplied_info['selector_type'],
						'oracle_info' => $oracle_info,
						'currency' => $currency,
						'api_key' => isset($supplied_info['api_key']) ? $supplied_info['api_key'] : null,
					]);
				}
				else {
					if ($print_debug) $app->print_debug("Failed to identify currency for oracle #".$config_pos);
					break;
				}
			}
			else if ($supplied_info['selector_type'] == "group") {
				$group = $app->fetch_group_by_description($supplied_info['group']);
				
				if ($group) {
					$group_members = $app->fetch_group_members($group['group_id'], true);
					
					if (count($group_members) > 0) {
						array_push($configured_oracles, [
							'oracle_pos' => $config_pos,
							'selector_type' => $supplied_info['selector_type'],
							'oracle_info' => $oracle_info,
							'group' => $group,
							'group_members' => $group_members,
							'api_key' => isset($supplied_info['api_key']) ? $supplied_info['api_key'] : null,
						]);
					}
					else {
						if ($print_debug) $app->print_debug("Group has not been installed yet for oracle #".$config_pos);
						break;
					}
				}
			}
			$config_pos++;
		}
		
		return $configured_oracles;
	}
	
	public static function setCurrencyPricesFromCoinDesk(&$app, &$reference_currency, &$configured_oracle, $print_debug=true) {
		if ($print_debug) $app->print_debug("Fetching prices from coin-desk oracle.");

		$api_response_raw = file_get_contents("https://api.coindesk.com/v1/bpi/currentprice.json");
		if ($api_response_raw) {
			$coin_data = json_decode($api_response_raw);
			if ($coin_data) {
				if (isset($coin_data->bpi->USD->rate_float) && $coin_data->bpi->USD->rate_float > 0) {
					$price_in_ref_currency = $coin_data->bpi->USD->rate_float;
					$app->create_currency_price($configured_oracle['currency']['currency_id'], $reference_currency, $price_in_ref_currency);
					if ($print_debug) $app->print_debug("Successfully set price for ".$configured_oracle['currency']['abbreviation']." to ".$price_in_ref_currency." / ".$reference_currency['abbreviation'].".");
				}
				else if ($print_debug) $app->print_debug("Valid price not found.");
			}
			else if ($print_debug) $app->print_debug("JSON decode failed.");
		}
		else if ($print_debug) $app->print_debug("URL fetch failed.");
	}
	
	public static function setCurrencyPricesFromFcsApi(&$app, &$reference_currency, &$configured_oracle, $print_debug=true) {
		if ($print_debug) $app->print_debug("Loading prices from fcs-api oracle");

		$pairs_csv = "";
		if (isset($configured_oracle['group_members'])) {
			foreach ($configured_oracle['group_members'] as $currency) {
				$pairs_csv .= "USD/".$currency['abbreviation'].",";
			}
			$pairs_csv .= "USD/LTC,";
		} else if (isset($configured_oracle['currency'])) {
			$pairs_csv .= "USD/".$configured_oracle['currency']['abbreviation'].",";
		}
		
		$pairs_csv = substr($pairs_csv, 0, strlen($pairs_csv)-1);
		
		$fcs_url = "https://fcsapi.com/api-v3/forex/latest?symbol=".$pairs_csv."&access_key=".$configured_oracle['api_key'];
		
		$fcsdata = file_get_contents($fcs_url);
		
		if ($fcsdata && $quotes = json_decode($fcsdata, true)) {
			if (!empty($quotes['response'])) {
				$usd_currency = $app->fetch_currency_by_id(1);
				
				if ($print_debug) $app->print_debug("Processing ".count($quotes['response'])." prices");
				
				$update_count = 0;
				
				foreach ($quotes['response'] as $quote) {
					$pair_parts = explode("/", $quote['s']);
					if (count($pair_parts) == 2 && ($pair_parts[0] == "USD" || $pair_parts[1] == "USD")) {
						if ($pair_parts[0] == "USD") $curr1 = $usd_currency;
						else {
							$curr1 = $app->fetch_currency_by_abbreviation($pair_parts[0]);
							$price_currency = $curr1;
						}
						
						if ($pair_parts[1] == "USD") $curr2 = $usd_currency;
						else {
							$curr2 = $app->fetch_currency_by_abbreviation($pair_parts[1]);
							$price_currency = $curr2;
						}
						
						if ($price_currency) {
							$ref_currency_info = $app->exchange_rate_between_currencies($usd_currency['currency_id'], $reference_currency['currency_id'], time(), $reference_currency['currency_id']);
							
							if ($curr1['currency_id'] == $price_currency['currency_id']) $price_in_ref_currency = $quote['c']/$ref_currency_info['exchange_rate'];
							else $price_in_ref_currency = $ref_currency_info['exchange_rate']/$quote['c'];
							
							if (isset($price_in_ref_currency)) {
								$price_in_ref_currency = $app->to_significant_digits($price_in_ref_currency, 12);
								
								if ($price_in_ref_currency > 0) {
									$app->create_currency_price($price_currency['currency_id'], $reference_currency, $price_in_ref_currency);
									//if ($print_debug) $app->print_debug($price_currency['abbreviation']."/".$reference_currency['abbreviation']." = ".$price_in_ref_currency);
									$update_count++;
								}
								else if ($print_debug) $app->print_debug("price_in_ref_currency was rounded to zero");
							}
							else if ($print_debug) $app->print_debug("Invalid price_in_ref_currency");
						}
						else if ($print_debug) $app->print_debug("Failed to fetch price currency.");
					}
				}
				
				if ($print_debug) $app->print_debug("Set prices for ".$update_count." currencies");
			}
			else if ($print_debug) $app->print_debug("FCS API did not include a valid response, returned: ".json_encode($quotes ?? null));
			
			if (!empty($quotes['info']['credit_count']) && $print_debug) $app->print_debug( "Used ".$quotes['info']['credit_count']." api credits.");
		}
		else if ($print_debug) $app->print_debug("FCS API response could not be parsed.");
	}
}
