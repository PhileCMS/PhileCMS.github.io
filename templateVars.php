<?php

$vars = [
    'base_url' => ''
];

// last released Phile version
$vars['current'] = (function () {
        exec('curl https://api.github.com/repos/PhileCMS/phile/releases/latest -s', $response);
        $json = json_decode(implode("\n", $response), true);
        return $json['tag_name'];
})();

$vars['phile_download'] = 'https://github.com/PhileCMS/Phile/releases/download/' 
    . $vars['current']
    . '/Phile.zip';

return $vars;

