<?php

namespace App\Http\Controllers;

use App\Http\classic_models\characters_model;
use App\Http\classic_models\gamesession_model;
use App\Http\classic_models\users_model;
use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use DB;
use App\Quotation;
use function App\Helpers\echo_r;

class Controller extends BaseController
{

    private function generateRandomString($length = 10)
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    private function add_new_session($values)
    {
        app('db')->table('tc_gamesession')->insert([$values]);
    }

    private function add_user_to_session($sessionname, $usrid, $sessionid)
    {
        $is_session_exists = DB::table('tc_gamesession')
            ->select('id')
            ->where('id', $sessionid)
            ->get();

        if(count($is_session_exists) == 0) {
            echo 'session does not exist!';
            return null;
        }

        $result = DB::table('tc_gamesession')
            ->select('tc_gamesession.id')
            ->leftJoin('tc_gamesession_players', ['tc_gamesession_players.session' => 'tc_gamesession.id'])
            ->where('tc_gamesession.name', $sessionname)
            ->where('tc_gamesession_players.player_id', $usrid)
            ->get();

        if(count($result) == 0) {
            $id = DB::table('users')->insertGetId(                [
                    'player_id' => $usrid,
                    'session' => 0
                ]
            );
        }


        echo $result;

    }

    public function login(Request $request) {

        if(empty($sessionname)) {
            return view('login');
        }

    }

    public function index_ajax(Request $request) {

        $usrid = $request->input('usrid');
        $name = $request->input('name');

        $responce = ['error' => 'OK'];

        if(empty($name) && empty($usrid)) {
            $responce['error'] = ['User info is not provided'];
            return $responce;
        }

    }

    public function index(Request $request) {

        $usr_table = new users_model();
        $gses_table = new gamesession_model();

        $usrid = $request->input('usrid');
        $usrname = 'none';
        if(empty($usrid)) {
            $usrname = $request->input('name');
            $usrid = $usr_table->add_user($usrname);
        } else {
            $usrname = $usr_table->get_user_by_id($usrid)->name;
        }

        $open_sessions = $gses_table->get_sessions_for_user($usrid);

        $session_data = [];
        if(count($open_sessions) > 0) {
            $session_data = $open_sessions[0];
        }

        return view('index', ['name' => $usrname, 'userid' => $usrid, 'session' => json_encode($session_data)]);

    }

    public function set_ready(Request $request)
    {
        $userid = $request->input('usrid');
        if (empty($userid)) {
            echo json_encode(['error' => 'No userid!']);
            return;
        }
        $session = $request->input('session');
        if (empty($session)) {
            echo json_encode(['error' => 'No $session!']);
            return;
        }
        $ready = $request->input('ready');
        if (empty($ready)) {
            $ready = 0;
        }

        $session_table = new gamesession_model();
        $session_table->set_user_ready($session, $userid, $ready);

    }

    public function lobby(Request $request) {

        $userid = $request->input('usrid');
        if(empty($userid)) {
            echo json_encode(['error' => 'No userid!']); return;
        }
        $session = $request->input('session');
        if(empty($session)) {
            echo json_encode(['error' => 'No $session!']); return;
        }


        $session_table = new gamesession_model();

        $players_in_sess = $session_table->get_players_in_session($session);

        $contains_this = false;
        foreach ($players_in_sess as $player) {
            if($player['player_id'] == $userid) {
                $contains_this = true; break;
            }
        }

        if(!$contains_this) {
            echo json_encode(['error' => 'No this user in session!']);
            return;
        }

        $characters_table = new characters_model();
        $chars = $characters_table->get_characters();

        $all_ready = true;
        foreach ($players_in_sess as $key => $val) {
            $players_in_sess[$key]['character'] = $chars[$val['character_type']];
            if(!$val['is_ready']) {
                $all_ready = false;
            }
        }

        echo json_encode($players_in_sess);

    }


    public function create_session(Request $request) {

        $userid = $request->input('usrid');
        if(empty($userid)) {
            echo 'No userid!'; return;
        }
        $char = $request->input('char');
        if(empty($char)) {
            echo 'No $char!'; return;
        }

        $sessions_table = new gamesession_model();
        $session_name = $this->generateRandomString(4);

        $session_id = $sessions_table->create_session($session_name, 1);
        if($session_id == false) {
            echo '$session_id is false!';
            return;
        }

        $sessions_table->add_user_to_session($session_name, $userid, $char);
        return redirect('/index?usrid='.$userid);
    }

    public function connect_to(Request $request) {

        $userid = $request->input('usrid');
        if(empty($userid)) {
            echo 'No userid!'; return;
        }
        $session = $request->input('session');
        if(empty($session)) {
            echo 'No $session!'; return;
        }
        $char = $request->input('char');
        if(empty($char)) {
            echo 'No $char!'; return;
        }

        $session_table = new gamesession_model();

        $session_table->add_user_to_session($session, $userid, $char);

        return redirect('/index?usrid='.$userid);

    }

    public function leave(Request $request) {

        $userid = $request->input('usrid');
        if(empty($userid)) {
            echo 'No userid!'; return;
        }
        $session = $request->input('session');
        if(empty($session)) {
            echo 'No $session!'; return;
        }

        $session_table = new gamesession_model();

        $session_table->remove_user_from_session($session, $userid);

        return redirect('/index?usrid='.$userid);

    }

    public function show(Request $request) {

        echo 'sqwe';
        exit;

        $sessionid = $request->input('sessionid');
        $usrid = $request->input('usrid');

        $this->add_user_to_session($usrid, $sessionid);

        $results = app('db')->select('select * from tc_gamesession where name = :name', ['name' => $sessionid]);

        if(count($results) == 0) {
            $sessionid = $this->generateRandomString(8);
            $this->add_new_session([
                'name' => $sessionid,
                'started' => date('Y-m-d H:i:s'),
                'rulebook' => 1,
                'round' => 0,
                'curr_player' => 0,
                'last_action' => date('Y-m-d H:i:s'),
                'curr_dragons' => 0]);


        }

        print_r($results);


    }


    private function multikill_of_type($type, $encounter) {
        foreach ($encounter as $key => $emeny) {
            if($encounter[$key]['is_alive'] == 1) {
                $encounter[$key]['is_alive'] = 0;
                if ($emeny['name'] != $type) {
                    return $encounter;
                }
            }
        }
        return $encounter;
    }

    private function resolve_partymember_battle($partymember, $encounter) {
        switch ($partymember['name']) {
            case 'MAGE':
                return $this->multikill_of_type('SLIME', $encounter);
            case 'CLERIC':
                return $this->multikill_of_type('SKELETON', $encounter);
            case 'WARRIOR':
                return $this->multikill_of_type('GOBLIN', $encounter);
            case 'THEIF':
                return $this->multikill_of_type('CHEST', $encounter);
            case 'PALADIN':
                return $this->multikill_of_type($encounter[0]['name'], $encounter);
            case 'SCROLL':
                return $encounter;//TODO:REROLL

        }
    }
    private function can_level_end($all_encounter) {
        foreach ($all_encounter as $item) {
            if (!$item['avoidable'] && $item['is_alive']) {
                return false;
            }
        }
        return true;
    }
    public function perfom_action($session, $userid, $party, $enemies, $end_turn, $next_level, $speical) {

        $session_table = new gamesession_model();

        $curr_player = $session_table->get_current_player($session);
        if($curr_player->player_id != $userid) {
            echo 'Not your turn!';
            return;
        }

        $all_party = $session_table->get_current_party($session);
        $all_encounter = $session_table->get_current_encounter($session);

        if(!empty($party)) {
            foreach ($party as $key => $val) {
                $party[$key] = $all_party[$val];
            }
        }
        if(!empty($enemies)) {
            foreach ($enemies as $key => $val) {
                $enemies[$key] = $all_encounter[$val];
            }
        }

        if($next_level && $this->can_level_end($all_encounter)) {
                $session_table->next_level($session);
                $session_table->roll_enemies($session);
                return;
        }

        if($end_turn) {
            $session_table->end_dungeon($session);
            return;
        }

        if($end_turn || $next_level) {
            $session_table->roll_enemies($session);
            return;
        }

        if(!empty($party) && !empty($enemies)) {
            foreach ($party as $key => $member) {
                if($party[$key]['is_alive'] == 1) {
                    $enemies = $this->resolve_partymember_battle($member, $enemies);
                    $party[$key]['is_alive'] = 0;
                }
            }
            $session_table->save_encounter_deaths($enemies);
            $session_table->save_party_deaths($party);
        }

        echo_r($enemies);

    }


    public function game(Request $request) {

        $userid = $request->input('usrid');
        if(empty($userid)) {
            echo 'No userid!'; return;
        }
        $session = $request->input('session');
        if(empty($session)) {
            echo 'No $session!'; return;
        }

        $party = $request->input('party');
        $enemies = $request->input('enemies');
        $end_turn = $request->input('end_turn');
        $special = $request->input('special');
        $next_level = $request->input('next_level');

        $this->perfom_action($session, $userid, $party, $enemies, $end_turn, $next_level, $special);

        $session_table = new gamesession_model();

        if(!$session_table->is_user_in_session($session, $userid)) {
            echo 'unavaliable session';
        }

        if(!$session_table->is_session_ready($session)) {
            echo 'Session is not ready';
        }

        $session_players = $session_table->get_players_in_session($session);

        //$session_table->get_current_player($session);

        $characters_table = new characters_model();

        $chars = $characters_table->get_characters();
        foreach ($session_players as $key => $val) {
            foreach ($chars[$val['character_type']] as $character) {
                if($character['level_requirement'] > $val['exp']) {
                    break;
                }
                $session_players[$key]['character'] = $character;
            }
        }

        $session_table->roll_team($session);
        $session_table->roll_enemies($session);

        return view('game', [
            'params' => ['usrid' => $userid, 'session' => $session],
            'players' => $session_players,
            'curr_player' => $session_table->get_current_player($session),
            'curr_party' => $session_table->get_current_party($session),
            'curr_encounter' => $session_table->get_current_encounter($session),
        ]);
        //echo 'the game!';

    }

}
