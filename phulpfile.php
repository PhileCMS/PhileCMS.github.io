<?php

use Phulp\Minifier\CssMinifier;
use Phulp\Minifier\JsMinifier;
use Phulp\ScssCompiler\ScssCompiler;

$config = [
    'templateVars' => include 'templateVars.php'
];

$phulp->task('default', function (Phulp\Phulp $phulp) {
    $phulp->start(['js', 'scss', 'css', 'twig', 'cleanup']);
});

$phulp->task('release', function ($phulp) {
    $phulp->start(['phpdoc', 'wiki', 'default']);
});

$phulp->task('js', function ($phulp) {
    $phulp->src(['src/scripts/'], '/js$/')
        ->pipe(new JsMinifier(['join' => true]))
        ->pipe($phulp->dest('js/'));
});

$phulp->task('scss', function ($phulp) {
    $phulp->src(['src/styles/'], '/scss$/')
        ->pipe(new ScssCompiler(['import_paths' => ['src/styles/']]))
        ->pipe($phulp->dest('css/'));
});

$phulp->task('css', function ($phulp) {
    $phulp->src(['css/'], '/z_site.css/')
        ->pipe(new CssMinifier(['join' => true, 'joinName' => 'style.min.css']))
        ->pipe($phulp->dest('css/'));
});


$phulp->task('twig', function ($phulp) use ($config) {
    $phulp->src(['pages/'], '/\.html$/')
        ->pipe($phulp->iterate(function(Phulp\DistFile $distFile) use ($config) {
            $loader = new \Twig_Loader_Filesystem(['templates/', 'pages']);
            $twig = new Twig_Environment($loader);
            $content = $twig->render($distFile->getName(), $config['templateVars']);
            $distFile->setContent($content); 
        }))
        ->pipe($phulp->dest(__DIR__));
});

$phulp->task('cleanup', function ($phulp) {
    $phulp->src(['css/'], '/.*(?<!\.min\.css)$/')
        ->pipe($phulp->iterate(function(Phulp\DistFile $distFile) {
            $file = $distFile->getFullpath() . '/' . $distFile->getName();
            if (file_exists($file)) {
                unlink($file);
            }
        }));
});

/**
 * Create documentation from wiki
 */
$phulp->task('wiki', function (Phulp\Phulp $phulp) {
    // clean wiki dir
    $phulp->src(['wiki'])->pipe($phulp->clean());

    // pull newest wiki changes
    $phulp->exec([
        'command' => 'git submodule update --remote',
        'cwd' => '.'
    ]);

    // convert markdown to HTML
    $phulp->src(['Phile.wiki'], '/\.md$/')
        ->pipe($phulp->iterate(function(Phulp\DistFile $distFile) {
            $Parsedown = new Parsedown();
            $html = $Parsedown->text($distFile->getContent());
            $distFile->setContent($html);
            $distFile->setDistpathname(str_replace('.md', '.html', $distFile->getName()));
        }))
        ->pipe($phulp->dest('wiki/'));

    // prepare sidebar for twig usage
    $phulp->src(['wiki'], '/_Sidebar/')
        ->pipe($phulp->iterate(function(Phulp\DistFile $distFile) use ($config) {
            $content = $distFile->getContent();
            $content = preg_replace('/href="(?=http)(.*?)"/', "href=\"Home\"", $content);
            $content = preg_replace('/href="(.*?)"/', "href='\\1.html'", $content);
            $distFile->setContent($content); 
        }))
        ->pipe($phulp->dest('wiki'));

    // prepare HTML for twig usage
    $phulp->src(['wiki'], '/^[^_].*\.html$/')
        ->pipe($phulp->iterate(function(Phulp\DistFile $distFile) {
            $html = '{% extends "wiki.twig" %}'
                . "\n{% block content %}\n{% raw %}\n"
                . $distFile->getContent() 
                . "\n{% endraw %}\n{% endblock %}";
            $distFile->setContent($html);
        }))
        ->pipe($phulp->dest('wiki/'));


    // pipe HTML pages through twig layout
    $phulp->src(['wiki'], '/^[^_].*\.html$/')
        ->pipe($phulp->iterate(function(Phulp\DistFile $distFile) use ($config) {
            $loader = new \Twig_Loader_Filesystem(['templates', 'wiki']);
            $twig = new Twig_Environment($loader);
            $title = explode('.', $distFile->getName())[0];
            $title = preg_replace('/\[.*\]-/', '', $title);
            $vars = compact(['title']);
            $content = $twig->render($distFile->getName(), $vars);
            $distFile->setContent($content); 
        }))
        ->pipe($phulp->dest('wiki'));
});

/**
 * Create phpDocumentor doc
 */
$phulp->task('phpdoc', function (Phulp\Phulp $phulp) {
    $phulp->src(['api/'])->pipe($phulp->clean());
    if (!file_exists(__DIR__ . '/phpDocumentor.phar')) {
        $phulp->exec([
            'command' => 'curl -LOk https://github.com/phpDocumentor/phpDocumentor2/releases/download/v2.9.0/phpDocumentor.phar',
            'cwd' => '.'
        ]);
    }
    $phulp->exec(['command' => 'php phpDocumentor.phar', 'cwd' => '.']);
});
