<p>User <?php echo $user->db_user['username']; ?> just had a confirmed sellout in <?php echo $game->db_game['name']; ?>. The user converted <?php echo $amount_paid_float." ".$game->db_game['coin_name_plural']; ?> to <?php echo $fulfill_buy_amount." ".$sellout_currency['short_name_plural']; ?>.</p>

<?php if ($fulfilled_sellout) { ?>
<p>This sellout was automatically fulfilled and you don't need to convert any currency to balance out this sellout.</p>
<?php } else { ?>
<p>This sellout was not automatically fulfilled. Please buy <?php echo $fulfill_buy_amount." ".$sellout_currency['short_name_plural']; ?> to resolve your exposure to this sellout.</p>
<?php } ?>
