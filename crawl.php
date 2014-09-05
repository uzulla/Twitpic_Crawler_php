#!/usr/bin/env php
<?php
/**
 * Twitpic crawler
 * usage: php crawl.php http://twitpic.com/photos/uzulla
 * ファイルは out/ 以下に保存されます。
 */
error_reporting(-1);
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
});
require "vendor/autoload.php";
use \Symfony\Component\DomCrawler\Crawler;
use \Goutte\Client;

class CrawlTwitPic
{
    const RETRY = 5;
    const OUTPUT_DIR = 'out/';

    public function run($url)
    {
        while ($url) {
            echo "\n{$url}\n";
            $full_img_page_url_list = static::retry("getFullImagePageList", $url);

            foreach ($full_img_page_url_list as $full_img_page_url) {
                $image_file_url = static::retry("getImageUrl", $full_img_page_url);
                static::retry("downloadImage", $image_file_url);
                echo ".";
            }

            $url = static::retry('getNextUrl', $url);
        }
    }

    public function getFullImagePageList($url)
    {
        $g = new Client;
        $c = $g->request("GET", $url);
        $img_url = [];
        $c->filter('.user-photo a')->each(function (Crawler $node) use (&$img_url) {
            $detail_path = $node->attr('href');
            $img_url[] = 'http://twitpic.com' . $detail_path . '/full';
        });
        return $img_url;
    }

    public function getNextUrl($url)
    {
        $g = new Client;
        $c = $g->request("GET", $url);
        $base_url = preg_replace('/\?.*\z/u', '', $url);
        $next_url = false;
        $c->filter('.pagination a')->each(
            function (Crawler $node) use (&$next_url, $base_url) {
                if ($node->text() === 'Next') {
                    $next_url = $base_url . $node->attr('href');
                }
            }
        );
        return $next_url;
    }

    public function getImageUrl($url)
    {
        $g = new Client;
        $c = $g->request("GET", $url);
        $img_url = $c->filter('#content img')->attr('src');
        return $img_url;
    }

    public function downloadImage($image_url)
    {
        if (!preg_match('/\/(?<filename>[0-9]+\.[a-zA-Z]{3,4})\?/u', $image_url, $_)) {
            throw new \Exception('file_name get fail. from, ' . $image_url);
        }
        $filename = $_['filename'];
        $raw = file_get_contents($image_url);
        file_put_contents(static::OUTPUT_DIR . $filename, $raw);
    }

    function retry($methodName, $params)
    {
        $retry = static::RETRY;
        while ($retry--) {
            try {
                return call_user_func(['\CrawlTwitPic', $methodName], $params);
            } catch (\Exception $e) {
                echo "retry! {$methodName}\n";
            }
        }
        echo "give up."; // リトライ成功しなかったので死
        exit;
    }
}

(new \CrawlTwitPic)->run($argv[1]);
echo "done\n";
