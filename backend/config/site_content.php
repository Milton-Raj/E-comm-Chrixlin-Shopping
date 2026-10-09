<?php

/*
| Storefront text the owner can edit in Admin → Content → Site text. Keys are the
| storefront's message keys; a blank value falls back to the storefront's default copy.
| This list is the whitelist: nothing else can be stored or served.
*/
return [
    'fields' => [
        ['key' => 'announce.preview', 'group' => 'Announcement bar', 'label' => 'Main message', 'max' => 120],
        ['key' => 'announce.shipping', 'group' => 'Announcement bar', 'label' => 'Second message (computers only)', 'max' => 120],

        ['key' => 'brand.tagline', 'group' => 'Brand', 'label' => 'Slogan (footer)', 'max' => 80],
        ['key' => 'brand.description', 'group' => 'Brand', 'label' => 'About the brand (footer and Google description)', 'max' => 300, 'multiline' => true],
        ['key' => 'brand.titleSuffix', 'group' => 'Brand', 'label' => 'Homepage title in Google and browser tabs (after “Chrixlin ·”)', 'max' => 80],

        ['key' => 'home.categoriesTitle', 'group' => 'Homepage sections', 'label' => 'Categories heading', 'max' => 80],
        ['key' => 'home.newArrivals', 'group' => 'Homepage sections', 'label' => 'New arrivals heading', 'max' => 80],
        ['key' => 'home.bestSellers', 'group' => 'Homepage sections', 'label' => 'Best sellers heading', 'max' => 80],

        ['key' => 'home.editorialEyebrow', 'group' => 'Homepage story', 'label' => 'Small heading', 'max' => 60],
        ['key' => 'home.editorialTitle', 'group' => 'Homepage story', 'label' => 'Headline', 'max' => 100],
        ['key' => 'home.editorialBody', 'group' => 'Homepage story', 'label' => 'Text', 'max' => 600, 'multiline' => true],
        ['key' => 'home.editorialCta', 'group' => 'Homepage story', 'label' => 'Button text', 'max' => 40],

        ['key' => 'home.valueSecure', 'group' => 'Homepage promises', 'label' => 'Promise 1 title', 'max' => 50],
        ['key' => 'home.valueSecureBody', 'group' => 'Homepage promises', 'label' => 'Promise 1 text', 'max' => 120],
        ['key' => 'home.valueDelivery', 'group' => 'Homepage promises', 'label' => 'Promise 2 title', 'max' => 50],
        ['key' => 'home.valueDeliveryBody', 'group' => 'Homepage promises', 'label' => 'Promise 2 text', 'max' => 120],
        ['key' => 'home.valueInstant', 'group' => 'Homepage promises', 'label' => 'Promise 3 title', 'max' => 50],
        ['key' => 'home.valueInstantBody', 'group' => 'Homepage promises', 'label' => 'Promise 3 text', 'max' => 120],
        ['key' => 'home.valueSupport', 'group' => 'Homepage promises', 'label' => 'Promise 4 title', 'max' => 50],
        ['key' => 'home.valueSupportBody', 'group' => 'Homepage promises', 'label' => 'Promise 4 text', 'max' => 120],

        ['key' => 'catalog.delivery', 'group' => 'Product page', 'label' => 'Delivery box heading', 'max' => 50],
        ['key' => 'catalog.deliveryPhysical', 'group' => 'Product page', 'label' => 'Delivery box text', 'max' => 300, 'multiline' => true],
    ],
];
