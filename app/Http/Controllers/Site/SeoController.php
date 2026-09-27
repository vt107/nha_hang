<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\Site;
use Illuminate\Http\Response;

/**
 * robots.txt / sitemap.xml sinh theo Cài đặt → SEO ("Cho phép Google tìm thấy website").
 */
class SeoController extends Controller
{
    public function robots(Site $site): Response
    {
        $lines = $site->allowsIndexing()
            ? [
                'User-agent: *',
                'Disallow: /admin',
                'Disallow: /staff',
                'Disallow: /kitchen',
                'Disallow: /menu',
                'Disallow: /mon-da-goi',
                'Disallow: /thanh-toan',
                'Disallow: /t/',
                'Disallow: /login',
                'Disallow: /horizon',
                '',
                'Sitemap: '.url('/sitemap.xml'),
            ]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(Site $site): Response
    {
        $urls = [url('/')];

        if ($site->reservationsEnabled()) {
            $urls[] = route('reservations.create');
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .implode('', array_map(fn (string $url) => '  <url><loc>'.e($url).'</loc></url>'."\n", $urls))
            .'</urlset>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
