<?php
if (!function_exists('kt_malvar_coordinates_in_bounds')) {
    function kt_malvar_coordinates_in_bounds($latitude, $longitude): bool
    {
        if (!is_numeric($latitude) || !is_numeric($longitude)) return false;
        $lat = (float) $latitude;
        $lng = (float) $longitude;
        // Municipality envelope; the map picker also verifies the reverse-geocoded
        // municipality and province before it accepts a new pin.
        return $lat >= 13.997231 && $lat <= 14.066481
            && $lng >= 121.103757 && $lng <= 121.183872;
    }
}
