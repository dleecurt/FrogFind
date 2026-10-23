<?php
// Readability with correct relative-URL resolution.
//
// The library's own getPathInfo() rebuilds the page's base URL from just the
// scheme and host, so it drops the port, mishandles paths ending in "/", and
// misreads a <base href> that holds a full URL. This override resolves links
// the standard way (RFC 3986) against the real page URL and any <base href>.

use fivefilters\Readability\Readability;
use League\Uri\Http;
use League\Uri\UriResolver;

class FrogReadability extends Readability
{
    public function getPathInfo($url)
    {
        try {
            $base = Http::createFromString($url);

            // Honour <base href="..."> if the page has one (relative or absolute)
            if ($this->baseURI !== null && trim($this->baseURI) !== '') {
                $base = UriResolver::resolve(Http::createFromString(trim($this->baseURI)), $base);
            }

            $scheme = $base->getScheme() ?: 'http';
            $prePath = $scheme . '://' . $base->getAuthority();

            // The full base URL works as pathBase: the library resolves
            // ordinary relative links against it with UriResolver.
            return [(string) $base, $scheme, $prePath];
        } catch (\Throwable $e) {
            // Malformed URL: fall back to the library's own behaviour
            return parent::getPathInfo($url);
        }
    }
}
