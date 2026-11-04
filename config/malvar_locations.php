<?php
if (!function_exists('kt_malvar_barangays')) {
    function kt_malvar_barangays(): array
    {
        return [
            'Bagong Pook', 'Bilucao', 'Bulihan', 'Luta Del Norte',
            'Luta Del Sur', 'Poblacion', 'San Andres', 'San Fernando', 'San Isidro East',
            'San Gregorio', 'San Juan', 'San Pedro I', 'San Pedro II', 'San Pioquinto', 'Santiago',
        ];
    }
}
if (!function_exists('kt_malvar_address_is_allowed')) {
    function kt_malvar_address_is_allowed(string $address): bool
    {
        $normalized = mb_strtolower($address);
        if (!str_contains($normalized, 'batangas')) return false;
        if (str_contains($normalized, 'malvar')) return true;
        foreach (kt_malvar_barangays() as $barangay) {
            if (str_contains($normalized, mb_strtolower($barangay))) return true;
        }
        // Bilucao is also identified as San Isidro West in some map records.
        if (str_contains($normalized, 'san isidro west')) return true;
        // Keep the east/west labels attached to the San Pedro barangay names.
        if (str_contains($normalized, 'san pedro i east') || str_contains($normalized, 'san pedro ii west')) return true;
        return false;
    }
}
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
