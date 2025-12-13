<?php

/**
 * Custom CSS Loader Plugin for osTicket
 *
 * Automatically loads custom CSS files from assets/custom/css/
 * for Staff Panel (files with 'staff' in name) and
 * Client Portal (files with 'client' in name).
 */

return array(
    'id' => 'net.markus-michalski:custom-css-loader',
    'version' =>        '0.1.1',
    'name' => 'Custom CSS Loader',
    'author' => 'Markus Michalski',
    'description' => 'Automatically loads custom CSS files from assets/custom/css/ for Staff Panel and Client Portal based on filename patterns.',
    'url' => 'https://github.com/markus-michalski/osticket-custom-css-loader',
    'plugin' => 'class.CustomCssLoaderPlugin.php:CustomCssLoaderPlugin'
);
