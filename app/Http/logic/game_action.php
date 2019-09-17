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
    private $loot_to_grant = 0;

    public $required_input = ['action'];

    private function multikill_of_type($type, $thismember) {

        $killcount = 0;

        foreach ($this->input['enemy'] as $key => $emeny) {
            if($this->input['enemy'][$key]['is_alive'] == 1) {
                if ($emeny['name'] != $type && $killcount !== 0) {
                    continue;
                }
                $killcount++;

                if($this->input['enemy'][$key]['name'] == 'CHEST' && $this->session['stage'] != 'looting') {
                    continue;
                }

                $this->input['enemy'][$key]['is_alive'] = 0;
                $this->input['party'][$thismember['id']]['is_alive'] = 0;

                if($this->input['enemy'][$key]['name'] === 'CHEST') {
                    $this->loot_to_grant++;
                }


            }
        }
    }
    private function resolve_partymember_battle($partymember) {
        switch ($partymember['name']) {
            case 'MAGE':
                $this->multikill_of_type('SLIME',$partymember);
                break;

            case 'CLERIC':
                $this->multikill_of_type('SKELETON', $partymember);
                break;

            case 'WARRIOR':
                $this->multikill_of_type('GOBLIN', $partymember);
                break;

            case 'THEIF':
                $this->multikill_of_type('CHEST', $partymember);
                break;

            case 'PALADIN':
                $this->multikill_of_type($this->input['enemy'][0]['name'], $partymember);
                break;

            case 'SCROLL':
                if($partymember['id'] == $this->input['party'][0]['id']) {
                    $this->input['party'][$partymember['id']]['is_alive'] = 0;
                    $this->input['reroll'] = 1;
                }
                break;

        }
    }


    /**
     * Atempts to kill all consequent enemies of provided type
     * @param $type
     * @return bool true if as a result of this action, combatant should die, false otherwise
     */
    private function general_multikill_of_type($type) {

        $killcount = 0;

        foreach ($this->input['enemy'] as $key => $emeny) {
            if ($this->input['enemy'][$key]['is_alive'] === 0) { continue; }

            if ($emeny['name'] != $type && $killcount !== 0) { continue;}

            if ($this->input['enemy'][$key]['name'] == 'CHEST') {
                if($this->session['stage'] == 'looting') {
                    $this->loot_to_grant++;
                    $killcount++;
                    $this->input['enemy'][$key]['is_alive'] = 0;
                }
                continue;
            }

            $killcount++;
            $this->input['enemy'][$key]['is_alive'] = 0;

        }

        return $killcount > 0;
    }

    /**
     * Resolvs a battle for a provided type
     * @param $combatant_type
     * @param $is_first
     * @return bool True if combatant should die, false otherwise
     */
    private function resolve_general_battle($combatant_type, $is_first) {
        switch ($combatant_type) {
            case 'MAGE':
                return $this->general_multikill_of_type('SLIME');
            case 'CLERIC':
                return $this->general_multikill_of_type('SKELETON');
            case 'WARRIOR':
                return $this->general_multikill_of_type('GOBLIN');
            case 'THEIF':
                return $this->general_multikill_of_type('CHEST');
            case 'PALADIN':
                return $this->general_multikill_of_type($this->input['enemy'][0]['name']);
            case 'SCROLL':
                if ($is_first && $this->input['reroll'] === 0) {
                    $this->input['reroll'] = 1;
                    return true;
                }
        }
        return false;
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
        $elixirs = array_filter($this->input['loot'], function ($x){return !$x['spent'] && ($x['name'] === 'ELIXIR');});

        echo_r('Elixirs'); exit;

        if($pots_count > 0 && $this->session['stage'] === 'looting') {
            $this->process_potions($pots_count, $elixirs_count);
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


    private function prepare_teams_and_loot_data($curr_battle) {

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

        $filled_loot = [];
        if(isset($curr_battle['loot']) && !empty($curr_battle['loot'])) {
            foreach ($curr_battle['loot'] as $key => $val) {
                $filled_loot[$val] = $this->session['loot'][$val];
            }
        }

        return ['party' => $filled_party, 'enemy' => $filled_encounter, 'loot' => $filled_loot];

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
        $session['loot'] = $session_table->get_avaliable_player_loot($post['usrid']);

        $session['stage'] = $this->deduce_game_stage($session);

        return $session;
    }

    private function process_dragon() {

        $rings = array_filter($this->input['loot'], function ($x) {return $x['name'] == 'RING' && !$x['spent'];});
        if(!empty($rings)) {
            $ring = array_shift($rings);

            $this->input['loot'][$ring['id']]['spent'] = 1;
            return ['dragon_killed' => true, 'grant_loot' => false];
        }


        $victims = [];

        foreach ($this->input['loot'] as $lootitem) {
            if($lootitem['spent'] === 0 && isset($lootitem['as_partymember']) && !empty($lootitem['as_partymember'])) {
                $victims[$lootitem['as_partymember']] = ['type' => 'loot', 'id' => $lootitem['id']];
            }
        }

        foreach ($this->input['party'] as $member) {
            if($member['is_alive'] === 1) {
                $victims[$member['partymember_type']] = ['type' => 'party', 'id' => $member['id']];
            }
        }


        if(count($victims) >= 3) {
            foreach (array_slice($victims, 0, 3) as $item) {
                $propper_term = ['party' => ['term' => 'is_alive', 'unsetval' => 0 ], 'loot' => ['term' => 'spent', 'unsetval' => 1 ]];
                $this->input[$item['type']][$item['id']][$propper_term[$item['type']]['term']] = $propper_term[$item['type']]['unsetval'];
            }
            return ['dragon_killed' => true, 'grant_loot' => true];
        }

        return ['dragon_killed' => false];

    }

    public function action($post) {

        $session_table = new gamesession_model();

        $this->session = $this->fill_session_data($post, $session_table);
        if($this->curr_error !== 'OK') { return $this->curr_error;}

        $this->input = $this->prepare_teams_and_loot_data($post['action']);
        $this->input['changes'] = (isset($post['changes']) && count($post['changes']) > 0) ? $post['changes'] : null;


        if($this->input['changes'] != null) {
           $this->process_changes();
        }

        foreach ($this->input['loot'] as $lootitem) {
            if($lootitem['name'] === 'DRAGONBAIT') {
                $session_table->turn_encounter_to_dragon($post['session_id']);
                return;
            }
        }

        foreach ($this->input['loot'] as $lootitem) {
            if($lootitem['name'] === 'TOWN_PORTAL') {
                $session_table->save_spent_loot($this->input['loot']);
                $session_table->end_dungeon($post['session_id'], true);
                return;
            }
        }


        if(!empty($this->input['party']) && any($this->input['party'], function ($x){return $x['name'] == 'SCROLL';})) {
            if($this->resolve_scroll($this->input, $post['session_id'])) {
                return;
            }
        }


        if($this->session['stage'] === 'dragon') {
            $dragon_result = $this->process_dragon();
            if($dragon_result['dragon_killed']) {
                $session_table->beat_dragon($post['session_id'], $post['usrid'], $dragon_result['grant_loot']);
                $session_table->save_spent_loot($this->input['loot']);
                $session_table->save_party_deaths($this->input['party']);
                $session_table->save_encounter_deaths($this->input['enemy']);
                return;
            }
        }

        if(!empty($this->input['enemy'])) {
            $first_counter = 0;

            foreach ($this->input['loot'] as $key => $loot) {
                $first_counter++;
                if(!$loot['spent'] && !empty($loot['as_partymember'])) {
                    $this->input['loot'][$key]['spent'] = $this->resolve_general_battle($loot['as_partymember'], $first_counter === 1);
                }
                if(isset($this->input['reroll'])) {
                    $session_table->reroll_enemies($this->input['enemy']);
                    $session_table->reroll_team($this->input['party']);
                    break;
                }
            }
        }


        if(!empty($this->input['party']) && !empty($this->input['enemy'] && !isset($this->input['reroll']))) {

            $first_counter = 0;
            foreach ($this->input['party'] as $key => $member) {
                $first_counter++;
                if($this->input['party'][$key]['is_alive'] == 1) {

                    $this->input['party'][$key]['is_alive'] = 1 - $this->resolve_general_battle($member['name'], $first_counter === 1);

                    if(isset($this->input['reroll'])) {
                        $session_table->reroll_enemies($this->input['enemy']);
                        $session_table->reroll_team($this->input['party']);
                        break;
                    }

                }
            }
        }

        if($this->loot_to_grant > 0) {
            $session_table->give_player_loot($this->session['id'], $this->session['curr_player']['player_id'], $this->loot_to_grant);
        }


        $session_table->save_spent_loot($this->input['loot']);
        $session_table->save_party_deaths($this->input['party']);
        $session_table->save_encounter_deaths($this->input['enemy']);

    }

}
