<?php

namespace App\Http\Controllers;

use App\Helper\CrawlHelper;
use App\Models\Domain;
use App\Models\Page;
use DOMDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CrawlerController extends Controller
{
    public function index()
    {
        return view('crawler');
    }

    public function crawl(Request $request)
    {
        $parsedUrl = parse_url($request->website);
        $crawler = new CrawlHelper();
        $crawledData = $crawler->begin($request);

        list($images, $links, $words_count) = [
            array_unique($crawler->getDetails($crawledData, 'img')),
            array_unique($crawler->getDetails($crawledData, 'links')),
            $crawler->getDetails($crawledData, 'words_count')
        ];

        $internal = array_filter($links, function($link) use ($parsedUrl) {
            return strpos($link, $parsedUrl['host']) || !strpos($link, '://');
        });

        $external = array_filter($links, function($link) use ($parsedUrl) {
            return !strpos($link, $parsedUrl['host']) && strpos($link, '://');
        });

        $avgPageLoads = $crawledData->pages->average('parse_time');
        $titles = $crawledData->pages->pluck('title');

        $titlesLength = strlen(implode(',', $titles->toArray()));

        $data = [
            'crawled_domain' => $crawledData,
            'pages_crawled' => $crawledData->pages->count(),
            'unique_images' => count($images),
            'number_unique_internal_links' => count($internal),
            'number_unique_external_links' => count($external),
            'average_page_loads' => $avgPageLoads,
            'average_word_count' => $words_count[0] / $crawledData->pages->count(), // word count is always going to be an array with one item in it
            'average_title_length' => $titlesLength / $crawledData->pages->count(), // word count is always going to be an array with one item in it
        ];

        return view('crawler', ['response' => $data]);
    }
}
