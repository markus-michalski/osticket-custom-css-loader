<?php

/**
 * Custom CSS Loader Plugin for osTicket
 *
 * Automatically loads custom CSS files from assets/custom/css/
 * for Staff Panel (files with 'staff' in name) and
 * Client Portal (files with 'client' in name).
 */

return [
    'id' => 'net.markus-michalski:custom-css-loader',
    'version' =>        '2.0.1',
    'name' => 'Custom CSS Loader',
    'author' => 'Markus Michalski',
    'description' => 'Automatically loads custom CSS files from assets/custom/css/ for Staff Panel and Client Portal based on filename patterns.',
    'url' => 'https://github.com/markus-michalski/osticket-custom-css-loader',
    'plugin' => 'class.CustomCssLoaderPlugin.php:CustomCssLoaderPlugin',
    'requires' => [
        'php' => '8.1',
        'osticket' => '1.17',
    ],
];
