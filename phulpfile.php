<?php

use Phulp\Minifier\CssMinifier;
use Phulp\Minifier\JsMinifier;
use Phulp\ScssCompiler\ScssCompiler;

$config = [
    'templateVars' => include 'templateVars.php'
];

$phulp->task('default', function ($phulp) {
    $phulp->start(['composer-update', 'html', 'phpdoc']);
});

$phulp->task('html', function (Phulp\Phulp $phulp) {
    $phulp->start(['js', 'css', 'twig', 'wiki']);
});

$phulp->task('composer-update', function (Phulp\Phulp $phulp) {
    $phulp->exec(['command' => 'composer update --no-dev phile-cms/phile', 'cwd' => '.']);
});

$phulp->task('js', function ($phulp) {
    $phulp->src(['src/scripts/'], '/js$/')
        ->pipe(new JsMinifier(['join' => true]))
        ->pipe($phulp->dest('js/'));
});

$phulp->task('css', function ($phulp) {
    // comile scss
    $phulp->src(['src/styles/'], '/scss$/')
        ->pipe(new ScssCompiler(['import_paths' => ['src/styles/']]))
        ->pipe($phulp->dest('css/'));

    // join and minify
    $phulp->src(['css/'], '/z_site.css/')
        ->pipe(new CssMinifier(['join' => true, 'joinName' => 'style.min.css']))
        ->pipe($phulp->dest('css/'));

    // cleanup temp files 
    $phulp->src(['css/'], '/.*(?<!\.min\.css)$/')
        ->pipe($phulp->iterate(function(Phulp\DistFile $distFile) {
            $file = $distFile->getFullpath() . '/' . $distFile->getName();
            if (file_exists($file)) {
                unlink($file);
            }
    }));
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

/**
 * Create documentation from wiki
 */
$phulp->task('wiki', function (Phulp\Phulp $phulp) use ($config) {
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
        ->pipe($phulp->iterate(function(Phulp\DistFile $distFile) {
            $content = $distFile->getContent();
            $content = preg_replace('/href="(.*?)"/', "href='\\1.html'", $content);
            $distFile->setContent($content); 
        }))
        ->pipe($phulp->dest('wiki'));

    // convert github links to local 
    $phulp->src(['wiki'], '/.html$/')
        ->pipe($phulp->iterate(function(Phulp\DistFile $distFile) {
            $content = $distFile->getContent();
            $content = preg_replace(
                '/href="https:\/\/github.*?\/wiki\/(.*?)"/',
                "href=\"\\1.html\"",
                $content
            );
            $distFile->setContent($content); 
        }))
        ->pipe($phulp->dest('wiki'));

    // pipe HTML pages through twig layout
    $phulp->src(['wiki'], '/^[^_].*\.html$/')
        ->pipe($phulp->iterate(function(Phulp\DistFile $distFile) use ($config) {
            $content = $distFile->getContent();

            // This ensures that all header tags have `id` attributes so they can be used as anchor links
            $markupFixer = new TOC\MarkupFixer();
            $content = $markupFixer->fix($content);

            // This generates the Table of Contents in HTML
            $tocGenerator = new TOC\TocGenerator();
            $toc = $tocGenerator->getHtmlMenu($content, 2);

            $html = '{% extends "wiki.twig" %}'
                . "\n{% block content %}\n{% verbatim %}\n"
                . $content 
                . "\n{% endverbatim %}\n{% endblock %}";
            $loader1 = new Twig_Loader_Array(array(
                $distFile->getName() => $html,
            ));

            $loader2 = new \Twig_Loader_Filesystem(['templates', 'wiki']);
            
            $loader = new Twig_Loader_Chain(array($loader1, $loader2));
            $twig = new Twig_Environment($loader);

            $fullname = explode('.', $distFile->getName())[0];
            $title = preg_replace('/\[.*\]-/', '', $fullname);
            $base_url = '../';

            $vars = compact(['title', 'fullname', 'base_url', 'toc']);
            $vars += $config['templateVars'];
            $content = $twig->render($distFile->getName(), $vars);

            $distFile->setContent($content); 
        }))
        ->pipe($phulp->dest('wiki'));

    // index redirect to Home page 
    file_put_contents('wiki/index.html', "<!DOCTYPE html><html><head><meta http-equiv='refresh' content='0; url=Home.html' /></head></html>");
    
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
