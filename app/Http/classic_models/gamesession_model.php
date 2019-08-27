<?php

namespace App\Http\classic_models;
use DB;

class gamesession_model {

    public function get_session_by_name($name) {

        return DB::table('tc_gamesession')
            ->select('*')
            ->where('name', $name)
            ->first();

    }

    public function get_players_in_session($session_id) {

        return json_decode(json_encode(DB::table('tc_gamesession_players')
            ->select('*')
            ->leftJoin('tc_users', 'tc_users.id', 'tc_gamesession_players.player_id')
            ->where('session', $session_id)
            ->whereNotNull('tc_users.id')
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
                'is_ready' => 0
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
                'round' => 0,
                'curr_player' => 0,
                'last_action' => DB::raw('NOW()'),
                'curr_dragons' => 0
            ]);



    }

    public function get_sessions_for_user($userid) {

        return DB::table('tc_gamesession')
            ->select('tc_gamesession.*')
            ->leftJoin('tc_gamesession_players', ['tc_gamesession_players.session' => 'tc_gamesession.id'])
            ->where('tc_gamesession_players.player_id', $userid)
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
        echo 'asfw';
        return true;
    }

}
