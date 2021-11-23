ALTER TABLE `games`
  DROP `pos_reward`,
  DROP `pow_reward`,
  DROP `giveaway_amount`,
  DROP `giveaway_status`,
  DROP `start_condition_players`,
  DROP `exponential_inflation_minershare`;

ALTER TABLE `user_games`
  DROP `payment_required`,
  DROP `paid_invoice_id`,
  DROP `payout_address_id`;
