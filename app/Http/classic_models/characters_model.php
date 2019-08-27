<?php
/**
 * Created by PhpStorm.
 * User: d.mosin
 * Date: 27.08.2019
 * Time: 12:02
 */

namespace App\Http\classic_models;
use DB;

class characters_model
{

    public function get_characters() {

        $list = json_decode(json_encode(DB::table('tc_characters')->select('*')->orderBy('level_requirement', 'ASC')->get()), true);

        $result = [];
        foreach ($list as $item) {
            $result[$item['character_type']][] = $item;
        }

        return $result;

    }

}