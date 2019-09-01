<?php
/**
 * Created by PhpStorm.
 * User: d.mosin
 * Date: 28.08.2019
 * Time: 17:03
 */

namespace App\Helpers;


/**
 * Выводит в output_buffer содержимое переменной в удобном для просмотре в браузере виде
 *
 * @todo Очень некрасивый код с $return.
 * @param mixed $mixed переменная
 * @param string $label подпись
 */
function echo_r($mixed, $label = '', $return = false) {
    $result = '';
    if($return)
        $result .= "<pre>";
    else
        echo "<pre>";

    if(!empty($label))
        if($return)
            $result .= "$label:\n";
        else
            echo "$label:\n";

    $result .= print_r($mixed, $return);
    if($return)
        $result .= "</pre>";
    else
        echo "</pre>";

    if($return)
        return $result;
    else
        return true;
}

/**
 * Checks wether any of the elements in array matching the predicate
 * @param $array Array to be checked
 * @param $predicate Predicate to check against
 * @return bool True if any of the elements in array made predicate return true, false otherwise
 */
function any($array, $predicate) {
    foreach ($array as $value) {
        if($predicate($value)) {
            return true;
        }
    }
    return false;
}

/**
 * Returns first element in array, that matched the predicate
 * @param $array
 * @param $predicate
 * @return mixed|null
 */
function first($array, $predicate) {

    foreach ($array as $value) {
        if($predicate($value)) {
            return $value;
        }
    }
    return null;
}
