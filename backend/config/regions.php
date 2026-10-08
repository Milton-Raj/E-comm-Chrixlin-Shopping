<?php

/* ISO 3166-2 subdivision names, used where a partner API wants names rather than codes. */

return [
    'countries' => ['IN' => 'India', 'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'US' => 'United States', 'SG' => 'Singapore', 'AU' => 'Australia', 'CA' => 'Canada'],

    'states' => [
        'IN' => [
            'AN' => 'Andaman & Nicobar', 'AP' => 'Andhra Pradesh', 'AR' => 'Arunachal Pradesh', 'AS' => 'Assam', 'BR' => 'Bihar',
            'CH' => 'Chandigarh', 'CT' => 'Chhattisgarh', 'DH' => 'Dadra and Nagar Haveli and Daman and Diu', 'DL' => 'Delhi', 'GA' => 'Goa',
            'GJ' => 'Gujarat', 'HR' => 'Haryana', 'HP' => 'Himachal Pradesh', 'JK' => 'Jammu & Kashmir', 'JH' => 'Jharkhand',
            'KA' => 'Karnataka', 'KL' => 'Kerala', 'LA' => 'Ladakh', 'LD' => 'Lakshadweep', 'MP' => 'Madhya Pradesh',
            'MH' => 'Maharashtra', 'MN' => 'Manipur', 'ML' => 'Meghalaya', 'MZ' => 'Mizoram', 'NL' => 'Nagaland',
            'OR' => 'Odisha', 'PY' => 'Puducherry', 'PB' => 'Punjab', 'RJ' => 'Rajasthan', 'SK' => 'Sikkim',
            'TN' => 'Tamil Nadu', 'TG' => 'Telangana', 'TR' => 'Tripura', 'UP' => 'Uttar Pradesh', 'UT' => 'Uttarakhand', 'WB' => 'West Bengal',
        ],
    ],

    // GST state codes (first two digits of a GSTIN; printed with the place of supply).
    'gst_state_codes' => [
        'JK' => '01', 'HP' => '02', 'PB' => '03', 'CH' => '04', 'UT' => '05', 'HR' => '06', 'DL' => '07', 'RJ' => '08', 'UP' => '09',
        'BR' => '10', 'SK' => '11', 'AR' => '12', 'NL' => '13', 'MN' => '14', 'MZ' => '15', 'TR' => '16', 'ML' => '17', 'AS' => '18',
        'WB' => '19', 'JH' => '20', 'OR' => '21', 'CT' => '22', 'MP' => '23', 'GJ' => '24', 'DH' => '26', 'MH' => '27', 'KA' => '29',
        'GA' => '30', 'LD' => '31', 'KL' => '32', 'TN' => '33', 'PY' => '34', 'AN' => '35', 'TG' => '36', 'AP' => '37', 'LA' => '38',
    ],
];
