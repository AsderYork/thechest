<?php

namespace App\Http\Controllers;

use App\Http\classic_models\characters_model;
use App\Http\classic_models\gamesession_model;
use App\Http\classic_models\users_model;
use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use DB;
use App\Quotation;

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

    public function test(Request $request) {

        $table = new gamesession_model();

        $sessionname = $request->input('sessionname');
        print_r($table->get_session_by_name($sessionname));


    }

    public function login(Request $request) {

        if(empty($sessionname)) {
            return view('login');
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

        foreach ($players_in_sess as $key => $val) {
            $players_in_sess[$key]['character'] = $chars[$val['character_type']];
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

}
