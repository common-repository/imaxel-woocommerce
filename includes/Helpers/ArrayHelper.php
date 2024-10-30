<?php

namespace Printspot\ICP\Helpers;


/**
 * ArrayHelper - Global helper functions for Arrays
 * 
 */
class ArrayHelper {


    /**
     * search
     *
     * Search in array multidimensional by key object value
     * 
     * @param  mixed $array
     * @param  mixed $key
     * @param  mixed $value
     * @return array 
     */
    public static function search($array, $key, $value) {
        $results = array();

        if (is_array($array)) {
            if (isset($array[$key]) && $array[$key] == $value) {
                $results[] = $array;
            }

            foreach ($array as $subarray) {
                $results = array_merge($results, self::search($subarray, $key, $value));
            }
        } else if (is_object($array)) {
            if (isset($array->$key) && $array->$key == $value) {
                $results[] = $array;
            }
        }

        return $results;
    }
}
