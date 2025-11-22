UPDATE events ev JOIN games g ON ev.game_id=g.game_id SET ev.option_max_width=100, g.default_option_max_width=100 WHERE g.module != 'MonsterDuels';
