<?php

return [

    // Master switch. When off, the seller editor section and PDP trust panel
    // hide, and metafields drop out of the search index.
    'enabled' => env('METAFIELDS_ENABLED', true),

    /*
     * Curated product metafield registry (M2.7). Each definition: a label
     * (also the default English string), the display group, an input type
     * (text | textarea | date), and whether its value feeds Scout search.
     * Adding a key here is all it takes to surface a new field everywhere.
     */
    'definitions' => [
        'halal_cert_body' => ['label' => 'Halal certification body', 'group' => 'halal', 'type' => 'text', 'searchable' => true],
        'halal_cert_number' => ['label' => 'Halal certificate number', 'group' => 'halal', 'type' => 'text', 'searchable' => false],
        'halal_cert_expiry' => ['label' => 'Certificate valid until', 'group' => 'halal', 'type' => 'date', 'searchable' => false],
        'sirim_number' => ['label' => 'SIRIM registration number', 'group' => 'certification', 'type' => 'text', 'searchable' => true],
        'country_of_origin' => ['label' => 'Country of origin', 'group' => 'details', 'type' => 'text', 'searchable' => true],
        'ingredients' => ['label' => 'Ingredients', 'group' => 'details', 'type' => 'textarea', 'searchable' => true],
        'allergens' => ['label' => 'Allergen information', 'group' => 'details', 'type' => 'text', 'searchable' => true],
        'shelf_life' => ['label' => 'Shelf life', 'group' => 'details', 'type' => 'text', 'searchable' => false],
        'storage_instructions' => ['label' => 'Storage instructions', 'group' => 'details', 'type' => 'textarea', 'searchable' => false],
        'expiry_date' => ['label' => 'Best before / expiry', 'group' => 'details', 'type' => 'date', 'searchable' => false],
    ],

    // Display groups, in render order. 'halal' is the brass trust badge group.
    'groups' => [
        'halal' => 'Halal certification',
        'certification' => 'Certifications',
        'details' => 'Product details',
    ],

];
