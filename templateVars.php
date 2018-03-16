<?php

return [
    // last released Phile version
    'current' => (function () {
        exec('curl https://api.github.com/repos/PhileCMS/phile/releases/latest -s', $response);
        $json = json_decode(implode("\n", $response), true);
        return $json['tag_name'];
    })()
];
