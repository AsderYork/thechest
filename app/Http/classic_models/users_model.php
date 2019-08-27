<?php

namespace App\Http\classic_models;
use DB;

class users_model {

    public function add_user($name) {

        $same_name_users = DB::table('tc_users')
            ->select('id')
            ->where('name', $name)
            ->first();

        if(!empty($same_name_users)) {
            return $same_name_users->id;
        }

        return DB::table('tc_users')
            ->insertGetId(['name' => $name, 'last_action_time' => DB::raw('NOW()')]);

    }


    public function get_user_by_id($id) {

        return DB::table('tc_users')
            ->select('*')
            ->where('id', $id)
            ->first();

    }

}
