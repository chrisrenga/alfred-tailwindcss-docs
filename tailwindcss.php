<?php

use Alfred\Workflows\Workflow;
use AlgoliaSearch\Client as Algolia;
use AlgoliaSearch\Version as AlgoliaUserAgent;

require __DIR__ . '/vendor/autoload.php';

$query = $argv[1];

preg_match('/^\h*?v?(master|(?:[\d]+)(?:\.[\d]+)?(?:\.[\d]+)?)?\h*?(.*?)$/', $query, $matches);

$subtext = empty($_ENV['alfred_theme_subtext']) ? '0' : $_ENV['alfred_theme_subtext'];

$workflow = new Workflow;
$parsedown = new Parsedown;
$algolia = new Algolia('BH4D9OD16A', '3df93446658cd9c4e314d4c02a052188');

AlgoliaUserAgent::addSuffixUserAgentSegment('Alfred Workflow', '0.2.2');

$index = $algolia->initIndex('tailwindcss');
$search = $index->search($query);

$results = $search['hits'];

$subtextSupported = $subtext === '0' || $subtext === '2';

if (empty($results)) {
    $google = sprintf('https://www.google.com/search?q=%s', rawurlencode("tailwindcss {$query}"));

    $workflow->result()
        ->title($subtextSupported ? 'Search Google' : 'No match found. Search Google...')
        ->icon('google.png')
        ->subtitle(sprintf('No match found. Search Google for: "%s"', $query))
        ->arg($google)
        ->quicklookurl($google)
        ->valid(true);

    $workflow->result()
        ->title($subtextSupported ? 'Open Docs' : 'No match found. Open docs...')
        ->icon('icon.png')
        ->subtitle('No match found. Open laravel.com/docs...')
        ->arg('https://tailwindcss.com/docs/')
        ->quicklookurl('https://tailwindcss.com/docs/')
        ->valid(true);

    echo $workflow->output();
    exit;
}

$urls = [];

foreach ($results as $hit) {
    $url = sprintf($hit['url']);

    if (in_array($url, $urls)) {
        continue;
    }

    $urls[] = $url;

    $hasText = isset($hit['_highlightResult']['content']['value']);

    $title = $hit['hierarchy']['lvl1'];
    $subtitle = subtitle($hit);
    if (!$subtextSupported && $subtitle) {
        $title = "{$title} » {$subtitle}";
    }

    if ($subtextSupported) {
        $text = $subtitle;

        if ($hasText) {
            $text = $hit['_highlightResult']['content']['value'];

            if ($subtitle) {
                $title = "{$title} » {$subtitle}";
            }
        }
    }

    $title = strip_tags(html_entity_decode($title, ENT_QUOTES, 'UTF-8'));

    $text = $parsedown->line($text);
    $text = strip_tags(html_entity_decode($text, ENT_QUOTES, 'UTF-8'));

    $workflow->result()
        ->uid($hit['objectID'])
        ->title($title)
        ->autocomplete($title)
        ->subtitle($text)
        ->arg($url)
        ->quicklookurl($url)
        ->valid(true);
}

echo $workflow->output();

function subtitle($hit)
{
    if (isset($hit['hierarchy'])) {
        return $hit['hierarchy']['lvl0'] . (isset($hit['content']) ? ' - ' . $hit['content'] : null);
    }
    return null;
}
