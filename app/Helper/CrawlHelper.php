<?php


namespace App\Helper;


use App\Models\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use DOMDocument;

class CrawlHelper
{
    protected $pageCount = 5;
    protected $pages = [];
    protected $data;
    protected $domain;
    private $content;
    private $folderPath;

    public function __construct()
    {
        $this->content = new DOMDocument();
        libxml_use_internal_errors(true);
    }

    public function begin(Request $request)
    {
        $parsedURL = $this->parseUrl($request->website);

        // create cookie folder for this domain in Storage
        Storage::makeDirectory('sites/' . $parsedURL['host']);

        $this->folderPath = 'sites/' . $parsedURL['host'];

        // store a cookie file in domain folder
        Storage::put($this->folderPath . '/cookie_jar.txt', '');
        Storage::put($this->folderPath . '/cookies.txt', '');

        // get the web content
        $data = $this->getWebPage([$request->website]);
        $this->processAndLoadPage($data);

        if (count($this->pages) < $this->pageCount) {
            // get the next 4 links in this page get content and redo
            $links = [];
            $linksTags = $this->content->getElementsByTagName('a');
            foreach ($linksTags as $linkTag) {
                $hrefValue = $linkTag->getAttribute('href');
                if ($hrefValue) {
                    $link = $this->relativeToAbsolute($hrefValue, $parsedURL);
                    $fullLink = $link;

                    $link = parse_url($link);
                    if (
                        $link &&
                        (!array_key_exists('host', $link) || $link['host'] == '' || $link['host'] == $this->domain->domain )
                        && array_key_exists('path', $link) &&
                        $link['path'] != '' &&
                        array_search($link['path'], $links) === false
                    ) {
                        $parsedLink = parse_url($fullLink);

                        if(in_array($parsedLink['path'], array_column($this->pages, 'path'))) { // search value in the array
                            continue;
                        }

                        $links[] = $fullLink;
                    }
                }
                if (count($links) === $this->pageCount - 1) {
                    break;
                }
            }

            $data = $this->getWebPage($links);
            $this->processAndLoadPage($data);
        }

        // store pages now
        $this->domain->pages()->delete(); // comment if you want to store all crawls now and in the future
        $this->domain->pages()->createMany($this->pages);
        $this->domain->load('pages');

        return $this->domain;
    }

    public function getDetails(Domain $domain, $find)
    {
        if (!$find)
            return [];

        $domain->load('pages');
        $pages = $domain->pages->pluck('content');

        $all_body = '';
        foreach ($pages as $page) {
            $this->content->loadHTML($page);
            $body = $this->content->getElementsByTagName('body');
            $body = $body->item(0);
            $all_body .= $this->domInnerHTML($body);
        }

        // all docs loaded into one to avoid a loop in a loop
        $this->content->loadHTML($all_body);

        // remove all script tags
        while (($i = $this->content->getElementsByTagName("script")) && $i->length) {
            $i->item(0)->parentNode->removeChild($i->item(0));
        }

        $this->content->saveHTML();

        $items = [];
        switch ($find) {
            case 'img':
                $items = $this->content->getElementsByTagName('img');
                break;
            case 'links':
                $items = $this->content->getElementsByTagName('a');
                break;
            case 'words_count':
                $items = $this->content->getElementsByTagName('body');
                break;
        }

        $itemData = [];
        foreach ($items as $item) {
            if ($find === 'img') {
                $itemData[] = $item->getAttribute('src');
            }

            if ($find === 'links') {
                $itemData[] = $item->getAttribute('href');
            }

            if ($find === 'words_count') {
                $words = str_word_count($item->nodeValue);
                $itemData[] = $words;
            }
        }

        return $itemData;
    }

    private function domInnerHTML($element)
    {
        $innerHTML = '';
        $children = $element->childNodes;
        foreach ($children as $child)
        {
            $tmp_dom = new DOMDocument();
            $tmp_dom->appendChild($tmp_dom->importNode($child, true));
            $innerHTML.=trim($tmp_dom->saveHTML());
        }
        return $innerHTML;
    }

    private function processAndLoadPage($pages)
    {
        foreach ($pages as $data) {
            // check if header gave a status 200
            // not sure if I should store failed responses too, but that is easy to implement here

            if (!$this->checkIfSuccessfull($data['headers'])) {
                continue;
            }

            $http_codes = array_map(function ($header) {
                $http_code = $header['http_code'];
                $http_code = explode(' ', $http_code);
                return $http_code[1];
            }, $data['headers']);

            $parsedUrl = parse_url($data['url']);

            // store domain if not exist
            $this->domain = Domain::updateOrCreate(['domain' => $parsedUrl['host']],
                [
                    'cookie_jar' => $this->folderPath . '/cookie_jar.txt',
                    'cookies' => $this->folderPath . '/cookies.txt',
                ]
            );

            $this->content->loadHTML($data['body']);
            $title = $this->content->getElementsByTagName('title');

            // place pages in array
            $this->pages[] = [
                'path' => $parsedUrl['path'],
                'title' => (count($title) > 0) ? $title[0]->nodeValue : '',
                'content' => $data['body'],
                'parse_time' => $data['time'],
                'http_code' => implode(' -> ', $http_codes)
            ];
        }
    }

    private function checkIfSuccessfull($headers)
    {
        // check if the headers has code 200
        $headerOkay = array_filter($headers, function ($header) {
            $http_code = explode(' ', $header['http_code']);
            return $http_code[1] == 200;
        });

        if (count($headerOkay) === 0) {
            return false;
        }

        return true;
    }

    private function parseUrl($url)
    {
        return parse_url($url);
    }

    private function getWebPage($urls, $ref='')
    {
        $multiCurl = [];
        $result = [];
        $mc = curl_multi_init();

        foreach ($urls as $index => $url) {
            $multiCurl[$index] = curl_init();

            curl_setopt($multiCurl[$index], CURLOPT_URL, $url);
            curl_setopt($multiCurl[$index], CURLOPT_HEADER,0);
            curl_setopt($multiCurl[$index], CURLOPT_RETURNTRANSFER,1);
            curl_setopt($multiCurl[$index], CURLOPT_COOKIEJAR, storage_path('app/sites/' . $this->domain . '/cookie_jar.txt'));
            curl_setopt($multiCurl[$index], CURLOPT_COOKIEFILE, storage_path('app/sites/' . $this->domain . '/cookies.txt'));
            curl_setopt($multiCurl[$index], CURLOPT_HTTPGET, true);
            curl_setopt($multiCurl[$index], CURLOPT_HEADER, true);
            curl_setopt($multiCurl[$index], CURLOPT_USERAGENT, 'fire-crawler_' . $index);
            curl_setopt($multiCurl[$index], CURLOPT_REFERER, $ref);
            curl_setopt($multiCurl[$index], CURLOPT_FOLLOWLOCATION, true );
            curl_setopt($multiCurl[$index], CURLOPT_MAXREDIRS, 2);
            curl_setopt($multiCurl[$index], CURLOPT_RETURNTRANSFER, true);
            curl_multi_add_handle($mc, $multiCurl[$index]);
        }

        $index=null;
        do {
            curl_multi_exec($mc,$index);
        } while($index > 0);

        // get content and remove handles
        foreach($multiCurl as $k => $ch) {

            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            list($headers, $body, $time, $url) = [
                $this->getHeadersInArray(substr(curl_multi_getcontent($ch), 0, $header_size)),
                substr(curl_multi_getcontent($ch), $header_size),
                curl_getinfo($ch, CURLINFO_TOTAL_TIME),
                curl_getinfo($ch, CURLINFO_EFFECTIVE_URL)
            ];

            $result[$k] = [
                'body' => $body,
                'headers' => $headers,
                'time' => $time,
                'url' => $url
            ];

            curl_multi_remove_handle($mc, $ch);
        }

        // close
        curl_multi_close($mc);

        return $result;
    }

    private function getHeadersInArray($headerContent)
    {
        $headers = [];
        $arr = explode("\r\n\r\n", $headerContent);
        for ($index = 0; $index < count($arr) -1; $index++) { // I allow this as headers are not going to be much
            foreach (explode("\r\n", $arr[$index]) as $i => $l) {
                if ($i === 0) {
                    $headers[$index]['http_code'] = $l;
                } else {
                    list ($key, $value) = explode(': ', $l);
                    $headers[$index][$key] = $value;
                }
            }
        }

        return $headers;
    }

    private function relativeToAbsolute( $relative, $base ){
        if($relative == "" || $base == "") return "";

        // check Base
        if( !array_key_exists( 'scheme', $base ) || !array_key_exists( 'host', $base ) || !array_key_exists( 'path', $base ) ) {
            //echo "Base Path " . $base['host'] ." Not Absolute Link\n"; handle errors the right way
            return "";
        }

        // parse Relative
        $relative_parsed = parse_url($relative);

        // if relative URL already has a scheme, it's already absolute
        if( array_key_exists( 'scheme', $relative_parsed ) && $relative_parsed['scheme'] != '' ) {
            return $relative;
        }

        // if only a query or a fragment, return base (without any fragment or query) + relative
        if( !array_key_exists( 'scheme', $relative_parsed ) && !array_key_exists( 'host', $relative_parsed ) && !array_key_exists( 'path', $relative_parsed ) ) {
            return $base['scheme']. '://'. $base['host']. $base['path']. $relative;
        }

        // remove non-directory portion from path
        $path = preg_replace( '#/[^/]*$#', '', $base['path'] );

        // if relative path already points to root, remove base return absolute path
        if( $relative[0] == '/' ) {
            $path = '';
        }

        // working Absolute URL
        $abs = '';

        // if user in URL
        if( array_key_exists( 'user', $base ) ) {
            $abs .= $base['user'];

            // if password in URL as well
            if( array_key_exists( 'pass', $base ) ) {
                $abs .= ':'. $base['pass'];
            }

            // append location prefix
            $abs .= '@';
        }

        // append Host
        $abs .= $base['host'];

        // if port in URL
        if( array_key_exists( 'port', $base ) ) {
            $abs .= ':'. $base['port'];
        }

        // append New Relative Path
        $abs .= $path. '/'. $relative;

        // replace any '//' or '/./' or '/foo/../' with '/'
        $regex = array('#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#');
        for( $n=1; $n>0; $abs = preg_replace( $regex, '/', $abs, -1, $n ) ) {}

        // return Absolute URL
        return $base['scheme']. '://'. $abs;
    }
}
