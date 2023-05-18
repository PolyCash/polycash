<p>User <?php echo $user->db_user['username']; ?> just had a confirmed buyin to <?php echo $game->db_game['name']; ?>. The user paid <?php echo $amount_paid_float." ".$pay_currency['short_name_plural']; ?> and received <?php echo $game->display_coins($buyin_amount_int); ?></p>

<?php if ($fulfilled_buyin) { ?>
<p>This buyin was automatically fulfilled and you don't need to convert any currency to balance out this buyin.</p>
<?php } else { ?>
<p>This buyin was not automatically fulfilled. Please sell <?php echo $amount_paid_float." ".$pay_currency['short_name_plural']; ?> to resolve your exposure to this buyin.</p>
<?php } ?>
