<?php

namespace App\Http\logic;


use App\Http\classic_models\gamesession_model;
use function App\Helpers\any;
use function App\Helpers\echo_r;
use function App\Helpers\first;

class game_action
{
    private $input = null;
    private $session = null;
    private $curr_error = 'OK';
    private $dedicated_ation = null;

    public $required_input = ['action'];

    private function multikill_of_type($type, $thismember, $encounter) {

        foreach ($encounter['enemy'] as $key => $emeny) {
            if($encounter['enemy'][$key]['is_alive'] == 1 && $encounter['enemy'][$key]['avoidable'] == 0) {
                $encounter['enemy'][$key]['is_alive'] = 0;
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
                return $this->multikill_of_type($encounter['enemy'][0]['name'], $partymember, $encounter);
            case 'SCROLL':
                if($partymember['id'] == $encounter['party'][0]['id']) {
                    $encounter['party'][$partymember['id']]['is_alive'] = 0;
                    $encounter['reroll'] = 1;
                }
                return $encounter;

        }
    }

    private function deduce_game_stage($session) {

        $result = [];
        $result['enemies_to_kill'] = array_filter($session['enemy'], function ($x) {return $x['is_alive'] && !$x['avoidable'];});
        $result['loot_to_get'] = array_filter($session['enemy'], function ($x) {return $x['is_alive'] && $x['avoidable'];});
        $result['is_dragon'] = $session['curr_dragons'] >= 3;

        if(count($result['enemies_to_kill']) > 0) {
            $result['stage'] = 'battle';

        } elseif(count($result['loot_to_get'])) {//TODO:Must pass loot
            $result['stage'] = 'looting';

        } elseif ($result['is_dragon']) {
            $result['stage'] = 'dragon';

        } else {
            $result['stage'] = 'end';
        }

        return $result['stage'];

    }
    private function process_potions($pots_count) {

        $filtered_for_victum = array_filter($this->input['party'], function ($x) {return $x['is_alive'];});
        if(empty($filtered_for_victum)) { return;}
        $victum_id = array_shift($filtered_for_victum)['id'];

        $pots_count = min($pots_count, count($this->input['changes']));


        for($i = 0; $i < $pots_count; $i++) {
            $this->input['party'][$this->input['changes'][$i]['id']]['is_alive'] = 1;
            $this->input['party'][$this->input['changes'][$i]['id']]['partymember_type'] = $this->input['changes'][$i]['val'];
        }
        $this->input['party'][$victum_id]['is_alive'] = 0;



        foreach ($this->input['enemy'] as $key => $item) {
            if($pots_count === 0) {
                break;
            }
            if($item['is_alive'] && $item['name'] === 'POTION') {
                $this->input['enemy'][$key]['is_alive'] = 0;
                $pots_count--;
            }
        }


        $session_table = new gamesession_model();
        $session_table->save_party_change_resurrection($this->input['party']);

    }
    private function process_changes() {

        $pots_count = count(array_filter($this->input['enemy'], function ($x){return $x['is_alive'] && ($x['name'] === 'POTION');}));
        if($pots_count > 0 && $this->session['stage'] === 'looting') {
            $this->process_potions($pots_count);
        }


    }
    private function resolve_scroll($selection, $session_id) {

        $alive_party = array_filter($selection['party'], function ($x){return $x['is_alive'];});
        $alive_enemy = array_filter($selection['enemy'], function ($x){return $x['is_alive'] && ($x['name'] != 'DRAGON');});
        $active_potions = array_filter($selection['enemy'], function ($x){return $x['is_alive'] && ($x['name'] === 'POTION');});

        if((count($alive_party) !== count($selection['party'])) && (count($active_potions) > 0)) {
            return false;//There must be a potion in play, so just assume the scroll was chosen as a sacrifice
        }

        $scroll = first($alive_party, function ($x){return $x['name'] == 'SCROLL';});
        if($scroll != null && (count($alive_party) + count($alive_enemy) > 1)) {

            $session_table = new gamesession_model();

            $selection['party'][$scroll['id']]['is_alive'] = 0;
            $session_table->save_party_deaths($selection['party']);

            unset($alive_party[$scroll['id']]);
            if(count($alive_party) > 0) {
                $session_table->reroll_team($session_id, array_column($alive_party, 'id'));
            }

            if(count($alive_enemy) > 0) {
                $session_table->reroll_enemies($session_id, array_column($alive_enemy, 'id'));
            }

            return true;
        }

        return false;

    }


    private function prepare_teams_data($curr_battle) {

        $filled_party = [];
        if(isset($curr_battle['party']) && !empty($curr_battle['party'])) {
            foreach ($curr_battle['party'] as $key => $val) {
                $filled_party[$val] = $this->session['party'][$val];
            }
        }

        $filled_encounter = [];
        if(isset($curr_battle['enemies']) && !empty($curr_battle['enemies'])) {
            foreach ($curr_battle['enemies'] as $key => $val) {
                $filled_encounter[$key] = $this->session['enemy'][$val];
            }
        }

        return ['party' => $filled_party, 'enemy' => $filled_encounter];

    }
    private function fill_session_data($post, $session_table) {

        $session = $session_table->get_session_data($post['session_id']);

        if($session['finished']) {
            $this->curr_error = 'GAME_ENDED'; return;
        }

        $session['curr_player'] = $session_table->get_current_player($session['id']);
        if($session['curr_player']['player_id'] != $post['usrid']) {
            $this->curr_error = 'NOT_YOUR_TURN'; return;
        }

        $session['party'] = $session_table->get_current_party($session['id']);
        $session['enemy'] = $session_table->get_current_encounter($session['id']);

        $session['stage'] = $this->deduce_game_stage($session);

        return $session;
    }

    private function process_dragon() {

        $victims = [];

        foreach ($this->input['party'] as $member) {
            if($member['is_alive'] === 1) {
                $victims[$member['partymember_type']] = $member['id'];
            }
        }


        if(count($victims) >= 3) {
            foreach (array_slice($victims, 0, 3) as $item) {
                $this->input['party'][$item]['is_alive'] = 0;
            }
            return true;
        }

        return false;

    }

    public function action($post) {

        $session_table = new gamesession_model();

        $this->session = $this->fill_session_data($post, $session_table);
        if($this->curr_error !== 'OK') { return $this->curr_error;}

        $this->input = $this->prepare_teams_data($post['action']);
        $this->input['changes'] = (isset($post['changes']) && count($post['changes']) > 0) ? $post['changes'] : null;


        if($this->input['changes'] != null) {
           $this->process_changes();
        }

        if(!empty($this->input['party']) && any($this->input['party'], function ($x){return $x['name'] == 'SCROLL';})) {
            if($this->input = $this->resolve_scroll($this->input, $post['session_id'])) {
                return;
            }
        }

        if($this->session['stage'] === 'dragon') {
            if($this->process_dragon()) {
                $session_table->beat_dragon($post['session_id']);
            }
        }

        if(!empty($this->input['party']) && !empty($this->input['enemy'])) {
            foreach ($this->input['party'] as $key => $member) {
                if($this->input['party'][$key]['is_alive'] == 1) {
                    $this->input = $this->resolve_partymember_battle($member, $this->input);

                    if(isset($this->input['reroll'])) {
                        $session_table->reroll_enemies($this->input['enemy']);
                        $session_table->reroll_team($this->input['party']);
                        break;
                    }

                }
            }
        }

        $session_table->save_party_deaths($this->input['party']);
        $session_table->save_encounter_deaths($this->input['enemy']);

    }

}
