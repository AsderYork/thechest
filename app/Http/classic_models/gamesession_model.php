<?php

namespace App\Http\classic_models;
use DB;
use function App\Helpers\echo_r;

class gamesession_model {

    public function get_session_by_name($name) {

        return DB::table('tc_gamesession')
            ->select('*')
            ->where('name', $name)
            ->first();

    }

    public function get_session_data($session_id) {

        return (array)DB::table('tc_gamesession')
            ->select([
                'tc_gamesession.*',
                'rulebook_name' => 'tc_rulebooks.name',
                'rulebook_max_rounds' => 'tc_rulebooks.max_rounds',
                'rulebook_max_players' => 'tc_rulebooks.max_players',
                'rulebook_team_size' => 'tc_rulebooks.team_size',
                'rulebook_max_enemies' => 'tc_rulebooks.max_enemies',
                'rulebook_max_level' => 'tc_rulebooks.max_level'
                ])
            ->leftJoin('tc_rulebooks', 'tc_rulebooks.id', 'tc_gamesession.rulebook')
            ->where('tc_gamesession.id', $session_id)
            ->first();
    }

    public function get_players_in_session($session_id) {

        return json_decode(json_encode(DB::table('tc_gamesession_players')
            ->select('*')
            ->leftJoin('tc_users', 'tc_users.id', 'tc_gamesession_players.player_id')
            ->where('session', $session_id)
            ->whereNotNull('tc_users.id')
            ->orderBy('position')
            ->get()), true);


    }

    public function add_user_to_session($session_name, $userid, $character_type) {

        $session = $this->get_session_by_name($session_name);
        if(empty($session)) {
            return null;
        }

        $session_players = $this->get_players_in_session($session->id);

        $allready_in_session = false;
        foreach ($session_players as $session_player) {
            if($session_player['player_id'] == $userid) {
                $allready_in_session = true;
            }
        }

        if($allready_in_session) {
            return false;
        }

        DB::table('tc_gamesession_players')
            ->insert([
                'player_id' => $userid,
                'session' =>$session->id,
                'position' => count($this->get_players_in_session($session->id)) + 1,
                'exp' => 0,
                'character_type' => $character_type,
                'last_action' => DB::raw('NOW()'),
                'active_used' => 0,
                'is_ready' => 0,
                'curr_level' => 1
            ]);

        return true;

    }

    public function add_user_to_session_byid($session_id, $userid, $character_type) {

        $session = DB::table('tc_gamesession')->where('id', $session_id)->first();

        if(empty($session)) {
            return null;
        }

        $session_players = $this->get_players_in_session($session->id);

        $allready_in_session = false;
        foreach ($session_players as $session_player) {
            if($session_player['player_id'] == $userid) {
                $allready_in_session = true;
            }
        }

        if($allready_in_session) {
            return false;
        }

        DB::table('tc_gamesession_players')
            ->insert([
                'player_id' => $userid,
                'session' =>$session->id,
                'position' => count($this->get_players_in_session($session->id)) + 1,
                'exp' => 0,
                'character_type' => $character_type,
                'last_action' => DB::raw('NOW()'),
                'active_used' => 0,
                'is_ready' => 0,
                'curr_level' => 1
            ]);

        return true;

    }

    public function remove_user_from_session($session, $userid) {

        DB::table('tc_gamesession_players')
            ->where('player_id', $userid)
            ->where('session', $session)
            ->delete();

    }

    public function create_session($session_name, $rulebook) {

        $existing_confs = DB::table('tc_gamesession')
            ->select('*')
            ->where('name', $session_name)
            ->count();

        if($existing_confs > 0) {
            return false;
        }

        return DB::table('tc_gamesession')
            ->insertGetId([
                'name' => $session_name,
                'rulebook' => $rulebook,
                'started' => DB::raw('NOW()'),
                'round' => 1,
                'curr_player' => 1,
                'last_action' => DB::raw('NOW()'),
                'curr_dragons' => 0,
                'finished' => 0
            ]);



    }

    public function get_sessions_for_user($userid) {

        return DB::table('tc_gamesession')
            ->select('tc_gamesession.*')
            ->leftJoin('tc_gamesession_players', ['tc_gamesession_players.session' => 'tc_gamesession.id'])
            ->where('tc_gamesession_players.player_id', $userid)
            ->where('finished', 0)
            ->get();

    }

    public function set_user_ready($session, $userid, $ready) {

        if(boolval($ready)) {
            echo 'as true';
            DB::table('tc_gamesession_players')
                ->where('player_id', $userid)
                ->where('session', $session)
                ->update(['is_ready' => 1]);
        } else {
            echo 'as false';
            DB::table('tc_gamesession_players')
                ->where('player_id', $userid)
                ->where('session', $session)
                ->update(['is_ready' => 0]);
        }


    }

    public function is_user_in_session($session, $userid) {

        return DB::table('tc_gamesession_players')
            ->select('*')
            ->where('session', $session)
            ->where('player_id', $userid)
            ->count() > 0;




    }

    public function is_session_ready($session) {

        $players = DB::table('tc_gamesession_players')
            ->select('is_ready')
            ->where('session', $session)
            ->get();

        if(empty($players)) {
            return false;
        }

        foreach ($players as $val) {
            if($val->is_ready == 0) {
                return false;
            }
        }
        return true;
    }

    public function get_current_player($session) {

        $players = DB::table('tc_gamesession')
            ->select('*')
            ->leftJoin('tc_gamesession_players', 'tc_gamesession_players.session', 'tc_gamesession.id')
            ->where('tc_gamesession.id', $session)
            ->orderBy('tc_gamesession_players.position')
            ->get('position');

        if(empty($players)) {
            return false;
        }

        $require_update = false;
        $selected_pos = 0;
        $curr_player = null;
        foreach ($players as $player) {
            if($player->curr_player == $player->position) {
                $selected_pos = $player->position;
                $curr_player = $player;
                break;
            }  else if($player->curr_player < $player->position) {
                $selected_pos = $player->position;
                $curr_player = $player;
                $require_update = true;
                break;
            }

        }

        if($require_update) {
            DB::table('tc_gamesession')
                ->where('id', $session)
                ->update(['curr_player' => $selected_pos]);

            DB::table('tc_gamesession_players')
                ->where('session', $session)
                ->where('player_id', $curr_player->player_id)
                ->update(['curr_level' => 1]);
        }
        $curr_player->curr_player = $selected_pos;
        return (array)$curr_player;

    }


    public function rekey($arr, $key = 'id') {

        $result = [];
        foreach ($arr as $item) {
            $result[$item->$key] = (array)$item;
        }
        return $result;
    }

    public function get_partymember_types() {
        $avail_types = DB::table('tc_partymembers_types')->select('*')->get();

        return $this->rekey($avail_types);
    }

    public function roll_team($session) {

        $session_data = (array)DB::table('tc_gamesession')
            ->select('team_size', 'tc_gamesession_players.player_id', 'round')
            ->leftJoin('tc_rulebooks', 'tc_rulebooks.id', 'tc_gamesession.rulebook')
            ->leftJoin('tc_gamesession_players', [
                'tc_gamesession_players.session' => 'tc_gamesession.id',
                'tc_gamesession.curr_player' => 'tc_gamesession_players.position'
            ])
            ->where('tc_gamesession.id',$session)
            ->first();


        $curr_team_count = DB::table('tc_playerparty')
            ->where('session', $session)
            ->where('gamesessionplayer', $session_data['player_id'])
            ->where('round', $session_data['round'])
            ->count();

        $partymember_types = $this->get_partymember_types();

        for($i = $curr_team_count; $i < $session_data['team_size']; $i++) {
            DB::table('tc_playerparty')
                ->insert([
                    'gamesessionplayer' => $session_data['player_id'],
                    'round' => $session_data['round'],
                    'partymember_type' => $partymember_types[rand(1, count($partymember_types))]['id'],
                    'is_alive' => 1,
                    'session' => $session
                ]);
        }

    }
    public function reroll_team($session, $ids) {

        $curr_party_count = DB::table('tc_playerparty')
            ->where('session', $session)
            ->whereIn('id', $ids)
            ->count();
        if($curr_party_count != count($ids)) {
            return json_encode(['err' => 'WRONG_SESSION']);
        }

        $partymember_types = $this->get_partymember_types();

        foreach ($ids as $id) {
            DB::table('tc_playerparty')
                ->where('id', $id)
                ->update(['partymember_type' => $partymember_types[rand(1, count($partymember_types))]['id']]);
        }

    }
    public function get_current_party($session) {

        $result = DB::table('tc_gamesession')
            ->select('tc_partymembers_types.*', 'tc_playerparty.*')
            ->leftJoin('tc_gamesession_players', [
            'tc_gamesession_players.session' => 'tc_gamesession.id',
            'tc_gamesession.curr_player' => 'tc_gamesession_players.position'
            ])
            ->leftJoin('tc_playerparty', [
                'tc_playerparty.session' => 'tc_gamesession.id',
                'tc_playerparty.round' => 'tc_gamesession.round',
                'tc_playerparty.gamesessionplayer' => 'tc_gamesession_players.player_id',

            ])
            ->leftJoin('tc_partymembers_types', 'tc_partymembers_types.id', 'tc_playerparty.partymember_type')
            ->where('tc_gamesession.id', $session)
            ->orderBy('tc_playerparty.is_alive')
            ->orderBy('tc_playerparty.partymember_type', 'desc')
            ->get();

        return $this->rekey($result);

    }

    public function get_enemies_types() {
        $avail_types = DB::table('tc_enemies_types')->select('*')->get();
        return $this->rekey($avail_types);
    }
    private function check_for_dragons($session_id, $session_data) {

        $dragons = DB::table('tc_encounter')
            ->select('tc_encounter.id')
            ->leftJoin('tc_enemies_types', 'tc_enemies_types.id', 'tc_encounter.enemy_id')
            ->where('session', $session_id)
            ->where('gamesessionplayer', $session_data['player_id'])
            ->where('round', $session_data['round'])
            ->where('level', $session_data['curr_level'])
            ->where('is_alive', 1)
            ->where('tc_enemies_types.name', 'DRAGON')
            ->pluck('id')->all();

        if(count($dragons) > 0) {

            DB::table('tc_encounter')
                ->whereIn('id', $dragons)
                ->update(['is_alive' => 0]);

            DB::table('tc_gamesession')
                ->where('id', $session_id)
                ->update(['curr_dragons' => $session_data['curr_dragons'] + count($dragons)]);

        }


    }
    private function get_enemy_session_data($session) {
        return (array)DB::table('tc_gamesession')
            ->select('tc_gamesession.curr_dragons',
                'max_enemies',
                'tc_gamesession_players.player_id',
                'round',
                'tc_gamesession_players.curr_level',
                'tc_gamesession_players.id')
            ->leftJoin('tc_rulebooks', 'tc_rulebooks.id', 'tc_gamesession.rulebook')
            ->leftJoin('tc_gamesession_players', [
                'tc_gamesession_players.session' => 'tc_gamesession.id',
                'tc_gamesession.curr_player' => 'tc_gamesession_players.position'
            ])
            ->where('tc_gamesession.id',$session)
            ->first();
    }
    public function roll_enemies($session) {


        $session_data = $this->get_enemy_session_data($session);

        $curr_enemies_count = DB::table('tc_encounter')
            ->where('session', $session)
            ->where('gamesessionplayer', $session_data['player_id'])
            ->where('round', $session_data['round'])
            ->where('level', $session_data['curr_level'])
            ->count();

        $enemies_types = $this->get_enemies_types();

        if($session_data['curr_level'] == 0) {
            $session_data['curr_level'] = 1;
            DB::table('tc_gamesession_players')->where('id', $session_data['id'])->update(['curr_level' => $session_data['curr_level']]);
        }

        for($i = $curr_enemies_count; $i < $session_data['curr_level']; $i++) {
            DB::table('tc_encounter')
                ->insert([
                    'gamesessionplayer' => $session_data['player_id'],
                    'round' => $session_data['round'],
                    'level' => $session_data['curr_level'],
                    'enemy_id' => $enemies_types[rand(1, count($enemies_types))]['id'],
                    'is_alive' => 1,
                    'session' => $session
                ]);
        }

        $this->check_for_dragons($session, $session_data);

    }
    public function reroll_enemies($session, $ids) {

        $curr_enemies_count = DB::table('tc_encounter')
            ->where('session', $session)
            ->whereIn('id', $ids)
            ->count();
        if($curr_enemies_count != count($ids)) {
            return json_encode(['err' => 'WRONG_SESSION']);
        }

        $enemies_types = $this->get_enemies_types();

        foreach ($ids as $id) {
            DB::table('tc_encounter')
                ->where('id', $id)
                ->update(['enemy_id' => $enemies_types[rand(1, count($enemies_types))]['id']]);
        }

        $this->check_for_dragons($session, $this->get_enemy_session_data($session));

    }
    public function get_current_encounter($session) {

        $result = DB::table('tc_gamesession')
            ->select('tc_enemies_types.*', 'tc_encounter.*')
            ->leftJoin('tc_gamesession_players', [
                'tc_gamesession_players.session' => 'tc_gamesession.id',
                'tc_gamesession.curr_player' => 'tc_gamesession_players.position'
            ])
            ->leftJoin('tc_encounter', [
                'tc_encounter.session' => 'tc_gamesession.id',
                'tc_encounter.round' => 'tc_gamesession.round',
                'tc_encounter.level' => 'tc_gamesession_players.curr_level',
                'tc_encounter.gamesessionplayer' => 'tc_gamesession_players.player_id',

            ])
            ->leftJoin('tc_enemies_types', 'tc_enemies_types.id', 'tc_encounter.enemy_id')
            ->where('tc_gamesession.id', $session)
            ->get();

        return $this->rekey($result);

    }

    public function save_encounter_deaths($encounter)
    {
        foreach ($encounter as $item) {
            DB::table('tc_encounter')
                ->where('id', $item['id'])
                ->update(['is_alive' => $item['is_alive']]);

        }
    }
    public function save_party_deaths($party) {

        foreach ($party as $item) {
            DB::table('tc_playerparty')
                ->where('id', $item['id'])
                ->update(['is_alive' => $item['is_alive']]);

        }
    }

    public function save_party_change_resurrection($party) {

        foreach ($party as $item) {
            DB::table('tc_playerparty')
                ->where('id', $item['id'])
                ->update(['is_alive' => $item['is_alive'], 'partymember_type' => $item['partymember_type'] ]);

        }
    }

    public function save_enemy_change_resurrection($enemy) {

        foreach ($enemy as $item) {
            DB::table('tc_encounter')
                ->where('id', $item['id'])
                ->update(['is_alive' => $item['is_alive'], 'enemy_type' => $item['enemy_type'] ]);

        }
    }


    public function next_level($session) {

        $session_data = (array)DB::table('tc_gamesession')
            ->select([
                'max_enemies',
                'tc_gamesession_players.player_id',
                'round',
                'tc_gamesession_players.curr_level',
                'max_level',
                'tc_gamesession_players.id'
            ])
            ->leftJoin('tc_rulebooks', 'tc_rulebooks.id', 'tc_gamesession.rulebook')
            ->leftJoin('tc_gamesession_players', [
                'tc_gamesession_players.session' => 'tc_gamesession.id',
                'tc_gamesession.curr_player' => 'tc_gamesession_players.position'
            ])
            ->where('tc_gamesession.id',$session)
            ->first();

        if($session_data['curr_level'] < $session_data['max_level']) {
            DB::table('tc_gamesession_players')
                ->where('id', $session_data['id'])
                ->update(['curr_level' => $session_data['curr_level'] + 1]);
        } else {
            $this->end_dungeon($session);
        }

        $this->roll_enemies($session);


    }
    public function end_dungeon($session) {

        $data = (array)DB::table('tc_gamesession')
            ->select('tc_gamesession_players.*', 'tc_gamesession.round', 'tc_rulebooks.max_rounds')
            ->leftJoin('tc_rulebooks', 'tc_rulebooks.id', 'tc_gamesession.rulebook')
            ->leftJoin('tc_gamesession_players', [
                'tc_gamesession_players.session' => 'tc_gamesession.id',
                'tc_gamesession_players.position' => 'tc_gamesession.curr_player'
            ])
            ->where('tc_gamesession.id', $session)
            ->orderBy('tc_gamesession_players.position')
            ->first();

        $all_monseters_dead = DB::table('tc_encounter')
            ->leftJoin('tc_enemies_types', 'tc_enemies_types.id', 'tc_encounter.enemy_id')
            ->where('session', $session)
            ->where('gamesessionplayer', $data['player_id'])
            ->where('round', $data['round'])
            ->where('is_alive', 1)
            ->Where('avoidable', 0)
            ->Count() == 0;

        $last_player_pos = DB::table('tc_gamesession')
            ->select('position')
            ->leftJoin('tc_gamesession_players', [
                'tc_gamesession_players.session' => 'tc_gamesession.id',
            ])
            ->max('position');

        $next_round = $data['round'];
        $next_player = $data['position'] + 1;
        $is_game_finished = false;
        if($data['position'] >= $last_player_pos) {
            $next_round++;
            $next_player = 1;
            if($next_round > $data['max_rounds']) {
                $is_game_finished = true;
            }
        }
        $new_exp = $data['exp'];
        if($all_monseters_dead) {
            $new_exp = $new_exp + $data['curr_level'];
        }

        DB::table('tc_gamesession')
            ->where('id', $session)
            ->update(['curr_player' => $next_player, 'round' => $next_round, 'finished' => $is_game_finished]);


        DB::table('tc_gamesession_players')
            ->where('id', $data['id'])
            ->update([
                'exp' => $new_exp,
                'curr_level' => 1
                ]);

    }
    public function discard_loot($session) {

        $result = DB::table('tc_gamesession')
            ->select('tc_enemies_types.*', 'tc_encounter.*')
            ->leftJoin('tc_gamesession_players', [
                'tc_gamesession_players.session' => 'tc_gamesession.id',
                'tc_gamesession.curr_player' => 'tc_gamesession_players.position'
            ])
            ->leftJoin('tc_encounter', [
                'tc_encounter.session' => 'tc_gamesession.id',
                'tc_encounter.round' => 'tc_gamesession.round',
                'tc_encounter.level' => 'tc_gamesession_players.curr_level',
                'tc_encounter.gamesessionplayer' => 'tc_gamesession_players.player_id',

            ])
            ->leftJoin('tc_enemies_types', 'tc_enemies_types.id', 'tc_encounter.enemy_id')
            ->where('tc_gamesession.id', $session)
            ->where('tc_encounter.is_alive', 1)
            ->where('tc_enemies_types.avoidable', 1)
            ->update(['is_alive' => 0]);

    }

    public function beat_dragon($session) {
        DB::table('tc_gamesession')
            ->where('tc_gamesession.id', $session)
            ->update(['tc_gamesession.curr_dragons' => 0]);

        DB::table('tc_gamesession')
            ->select('team_size', 'tc_gamesession_players.player_id', 'round')
            ->leftJoin('tc_gamesession_players', [
                'tc_gamesession_players.session' => 'tc_gamesession.id',
                'tc_gamesession.curr_player' => 'tc_gamesession_players.position'
            ])
            ->where('tc_gamesession.id',$session)
            ->increment('tc_gamesession_players.exp');

    }

    public function is_game_ended($session) {

        return DB::table('tc_gamesession')
            ->where('tc_gamesession.id', $session)
            ->value('finished');

    }

}
