<?php
/**
 * Created by PhpStorm.
 * User: d.mosin
 * Date: 29.08.2019
 * Time: 9:28
 */

namespace App\Http\Controllers;
use App\Http\classic_models\characters_model;
use App\Http\classic_models\gamesession_model;
use App\Http\classic_models\users_model;
use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use DB;
use App\Quotation;
use function App\Helpers\echo_r;

class Ajax_interface_controller
{

    public function login(Request $request) {

        $usr_table = new users_model();
        echo  json_encode(['usrid' => $usr_table->add_user($request->input('name'))]);

    }

    public function lobby(Request $request) {

        $usrid = $request->input('usrid');

        $responce = ['error' => 'OK', 'err' => 'OK'];

        if(empty($usrid)) {
            $responce['error'] = 'User info is not provided';
            $responce['err'] = 'NO_AUTH';
            $responce['suggested_action'] = '/ajax/login';
            return json_encode($responce);
        }

        $session_table = new gamesession_model();
        $responce['sessions'] = $session_table->get_sessions_for_user($usrid);

        $usrs_table = new users_model();
        $responce['user'] = $usrs_table->get_user_by_id($usrid);

        return json_encode($responce);

    }

    public function index() {
        return view('ajax_view');
    }

    private function generateRandomString($length = 10)
    {
        $characters = '123456789ABCDEFGHJKLMNOPQRSTVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
    public function new_session(Request $request) {

        $usrid = $request->input('usrid');
        $rulebook = $request->input('rulebook');
        $character = $request->input('character');

        $responce = ['err' => 'OK'];

        if((int)$usrid == 0) {
            $responce['err'] = 'NO_USRID';
            return json_encode($responce);
        }
        if((int)$rulebook == 0) {
            $rulebook = 1;
        }
        if((int)$character == 0) {
            $character = 1;
        }

        $session_table = new gamesession_model();
        $session_id = $session_table->create_session($this->generateRandomString(4), $rulebook);

        if((int)$session_id == 0) {
            $responce = ['err' => 'CANT_CREATE_SESSION'];
            return json_encode($responce);
        }

        if($session_table->add_user_to_session_byid($session_id, $usrid, $character)) {
            $responce['session_id'] =  $session_id;
            return json_encode($responce);
        }

        $responce = ['err' => 'CANT_ADD_USER'];
        return json_encode($responce);

    }

    public function connect_to(Request $request) {

        $userid = $request->input('usrid');
        $session_name = $request->input('session_name');
        $char = $request->input('char');

        if((int)$userid == 0) {
            echo json_encode(['err' => 'NO_USRID']); return;
        }
        if(empty($session_name)) {
            echo json_encode(['err' => 'NO_SESSION_NAME']); return;
        }
        if((int)$char == 0) {
            $char = 1;
        }

        $session_table = new gamesession_model();

        $session_table->add_user_to_session($session_name, $userid, $char);

        echo json_encode(['err' => 'OK']);

    }

    public function ready_table(Request $request) {

        $usrid = $request->input('usrid');
        $session_id = $request->input('session_id');

        if(empty($usrid)) {
            return json_encode(['err' => 'NO_USRID']);
        }
        if(empty($session_id)) {
            return json_encode(['err' => 'NO_SESSION']);

        }

        $session_table = new gamesession_model();

        $players = $session_table->get_players_in_session($session_id);

        $session_table = new gamesession_model();

        $characters_table = new characters_model();
        $chars = $characters_table->get_characters();

        $this_one_is_present = false;
        foreach ($players as $player) {
            if($player['player_id'] == $usrid) {
                $this_one_is_present = true;
                break;
            }
        }

        if(!$this_one_is_present) {
            return json_encode(['err' => 'SESSION_UNAVALIABLE']);
        }


        $all_ready = true;
        foreach ($players as $key => $val) {
            $players[$key]['character'] = $chars[$val['character_type']];
            if(!$val['is_ready']) {
                $all_ready = false;
            }
        }

        return json_encode(['err' => 'OK', 'players' => $players, 'all_ready' => $all_ready]);


    }


    public function set_ready(Request $request)
    {
        $userid = $request->input('usrid');
        if (empty($userid)) {
            echo json_encode(['error' => 'No userid!']);
            return;
        }
        $session_id = $request->input('session_id');
        if (empty($session_id)) {
            echo json_encode(['error' => 'No $session_id!']);
            return;
        }
        $ready = $request->input('ready');
        if (empty($ready)) {
            $ready = 0;
        }

        $session_table = new gamesession_model();
        $session_table->set_user_ready($session_id, $userid, $ready);
        echo json_encode(['where_ready' => $ready]);

    }





    private function multikill_of_type($type, $thismember, $encounter) {

        foreach ($encounter['encounter'] as $key => $emeny) {
            if($encounter['encounter'][$key]['is_alive'] == 1) {
                $encounter['encounter'][$key]['is_alive'] = 0;
                $encounter['party'][$thismember['id']]['is_alive'] = 0;
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
                return $this->multikill_of_type('SLIME',$partymember, $encounter);
            case 'CLERIC':
                return $this->multikill_of_type('SKELETON', $partymember, $encounter);
            case 'WARRIOR':
                return $this->multikill_of_type('GOBLIN', $partymember, $encounter);
            case 'THEIF':
                return $this->multikill_of_type('CHEST', $partymember, $encounter);
            case 'PALADIN':
                return $this->multikill_of_type($encounter['encounter'][0]['name'], $partymember, $encounter);
            case 'SCROLL':
                if($partymember['id'] == $encounter['party'][0]['id']) {
                    $encounter['party'][$partymember['id']]['is_alive'] = 0;
                    $encounter['reroll'] = 1;
                }
                return $encounter;

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
    public function next_level(Request $request) {

        $post = $request->all();

        $sanitize_result = $this->enshure_exists($post, ['session_id', 'usrid']);

        if(!empty($sanitize_result)) {
            return json_encode($sanitize_result);
        }


        $session_table = new gamesession_model();

        $curr_player = $session_table->get_current_player($post['session_id']);
        if($curr_player->player_id != $post['usrid']) {
            return ['err' => 'NOT_YOUR_TURN'];
        }

        $all_encounter = $session_table->get_current_encounter($post['session_id']);

        if($this->can_level_end($all_encounter)) {
            $session_table->next_level($post['session_id']);
            return json_encode(['err' => 'OK']);
        }
        return json_encode(['err' => 'ENCOUNTER_NOT_BEATEN']);


    }

    public function end_turn(Request $request) {


        $post = $request->all();

        $sanitize_result = $this->enshure_exists($post, ['session_id', 'usrid']);

        if(!empty($sanitize_result)) {
            return json_encode($sanitize_result);
        }


        $session_table = new gamesession_model();

        $curr_player = $session_table->get_current_player($post['session_id']);
        if($curr_player->player_id != $post['usrid']) {
            return ['err' => 'NOT_YOUR_TURN'];
        }

        $session_table->end_dungeon($post['session_id']);
        return json_encode(['err' => 'OK']);

    }

    private function prepare_teams_data($all_party, $all_encounter, $curr_battle) {

        $filled_party = [];
        if(isset($curr_battle['party']) && !empty($curr_battle['party'])) {
            foreach ($curr_battle['party'] as $key => $val) {
                $filled_party[$val] = $all_party[$val];
            }
        }

        $filled_encounter = [];
        if(isset($curr_battle['enemies']) && !empty($curr_battle['enemies'])) {
            foreach ($curr_battle['enemies'] as $key => $val) {
                $filled_encounter[$key] = $all_encounter[$val];
            }
        }

        return ['party' => $filled_party, 'encounter' => $filled_encounter];

    }
    private function enshure_exists($post, $vals) {

        $keys = array_keys($post);

        foreach ($vals as $val) {
            if(!in_array($val, $keys)) {
                return ['err' => strtoupper($val) . '_NOT_PROVIDED'];
            }
        }
        return null;
    }
    public function action(Request $request) {

        $post = $request->all();

        $sanitize_result = $this->enshure_exists($post, ['session_id', 'usrid']);

        if(!empty($sanitize_result)) {
            return json_encode($sanitize_result);
        }


        $session_table = new gamesession_model();

        $curr_player = $session_table->get_current_player($post['session_id']);
        if($curr_player->player_id != $post['usrid']) {
            return ['err' => 'NOT_YOUR_TURN'];
        }

        $all_party = $session_table->get_current_party($post['session_id']);
        $all_encounter = $session_table->get_current_encounter($post['session_id']);

        $selection = $this->prepare_teams_data($all_party, $all_encounter, $post['action']);

        if(!empty($selection['party']) && !empty($selection['encounter'])) {
            foreach ($selection['party'] as $key => $member) {
                if($selection['party'][$key]['is_alive'] == 1) {
                    $selection = $this->resolve_partymember_battle($member, $selection);

                    if(isset($selection['reroll'])) {
                        $session_table->reroll_enemies($selection['encounter']);
                        $session_table->reroll_team($selection['party']);
                        break;
                    }

                }
            }
            $session_table->save_encounter_deaths($selection['encounter']);
            $session_table->save_party_deaths($selection['party']);
        }

    }

    public function game(Request $request) {
        $userid = $request->input('usrid');
        if(empty($userid)) {
            echo 'No userid!'; return;
        }
        $session_id = $request->input('session_id');
        if(empty($session_id)) {
            echo 'No $session_id!'; return;
        }

        $session_table = new gamesession_model();

        if(!$session_table->is_user_in_session($session_id, $userid)) {
            echo json_encode(['err' => 'UNAVALIABLE_SESSION']);
            return;
        }

        if(!$session_table->is_session_ready($session_id)) {
            echo json_encode(['err' => 'PLAYERS_NOT_READY']);
            return;
        }

        $session_players = $session_table->get_players_in_session($session_id);

        //$session_table->get_current_player($session_id);

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

        $session_table->roll_team($session_id);
        $session_table->roll_enemies($session_id);

        return json_encode([
            'params' => ['usrid' => $userid, 'session_id' => $session_id],
            'players' => $session_players,
            'curr_player' => $session_table->get_current_player($session_id),
            'curr_party' => $session_table->get_current_party($session_id),
            'curr_encounter' => $session_table->get_current_encounter($session_id),
            'can_level_end' => $this->can_level_end($session_table->get_current_encounter($session_id))
        ]);
        //echo 'the game!';
    }

}
