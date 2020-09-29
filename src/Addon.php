<?php
namespace Leafcutter\Addons\Leafcutter\StaticPages;

use Leafcutter\Common\Filesystem;
use Leafcutter\Pages\PageEvent;
use Leafcutter\Response;
use Leafcutter\URL;

class Addon extends \Leafcutter\Addons\AbstractAddon
{
    const DEFAULT_CONFIG = [
        "enabled" => true,
        "directory" => '${directories.web}/',
        "ttl" => 60,
    ];

    /**
     * Before returning a page, convert its query string into a
     * base64-encoded string inside the filename. This is necessary
     * to allow static caching of query-driven content.
     *
     * It's done across the board to provide consistency in page
     * URLs, regardless of whether they are actually cacheable.
     *
     * @param URL $url
     * @return void
     */
    public function onPageReturn(PageEvent $event)
    {
        $page = $event->page();
        $url = $page->url();
        if ($url->query()) {
            $path = $url->path();
            $path .= '__q__' . URL::base64_encode(substr($url->queryString(), 1)) . '.html';
            $url->setPath($path);
            $url->setQuery([]);
            $page->setUrl($url);
        }
    }

    /**
     * Converts requests for URLs with base64-encoded strings in the
     * filename back into normal URLs with query strings. Transforms
     * the given URL in place.
     *
     * @param URL $url
     * @return void
     */
    public function onPageURL(URL $url)
    {
        $path = $url->path();
        if (preg_match('/__q__([a-zA-Z0-9\-_]+).html$/', $path, $matches)) {
            $query = URL::base64_decode($matches[1]);
            parse_str($query, $query);
            if ($query) {
                $path = preg_replace('/__q__([a-zA-Z0-9\-_]+).html$/', '', $path);
                $url->setPath($path);
                $url->setQuery($query);
            }
        }
    }

    public function onResponseURL_namespace_staticPageBuild(URL $url): ?Response
    {
        $rUrl = new URL('@/' . URL::base64_decode($url->sitePath()));
        $response = new Response();
        $response->setMime('application/javascript');
        $response->setTemplate(null);
        $response->header('cache-control', 'max-age=10, public');
        if ($this->needsRebuild($rUrl)) {
            if (is_file($this->urlSavePath($rUrl)) && !$this->config('enabled')) {
                $response->setContent('/*intentionally does nothing*/');
                unlink($this->urlSavePath($rUrl));
                return $response;
            }
            $this->leafcutter->buildResponse($rUrl, false);
            $response->setContent('/*intentionally does nothing*/');
            $response->doAfter(function () use ($rUrl) {
                $this->leafcutter->buildResponse($rUrl, false);
            });
        } else {
            $response->setContent('/*intentionally does nothing*/');
        }
        return $response;
    }

    /**
     * Determine whether a fresh HTML file needs to be built
     * for the given URL.
     *
     * @param URL $url
     * @return boolean
     */
    protected function needsRebuild(URL $url): bool
    {
        $file = $this->urlSavePath($url);
        if (is_file($file) && !$this->config('enabled')) {
            return true;
        }
        if (!is_file($file)) {
            return true;
        }
        $content = file_get_contents($file);
        if (!preg_match('@<!--staticPageMeta{{{(.+?)}}}-->@ms', $content, $matches)) {
            return true;
        }
        $meta = json_decode($matches[1], true);
        if (!$meta) {
            return true;
        }
        $ttl = $this->config('ttl');
        if ($meta['time'] + $ttl < time()) {
            return true;
        }
        if ($meta['hash'] != $this->leafcutter->content()->hash($url->sitePath(), $url->siteNamespace())) {
            return true;
        }
        return false;
    }

    /**
     * Cache page to a static HTML file if possible, and inject
     * a script call that will asynchronously check for whether
     * the generate page needs rebuilding.
     *
     * @param Response $response
     * @return void
     */
    public function onResponseReturn(Response $response)
    {
        if (!$this->config('enabled')) {
            return;
        }
        // delete cached HTML if response status isn't 200
        $path = $this->savePath($response);
        if (!$this->shouldSave($response)) {
            if (is_file($path)) {
                unlink($path);
            }
        } else {
            // save response
            $url = $response->source()->url();
            $content = $response->content();
            $scriptURL = new URL('@/~staticPageBuild/' . base64_encode($response->source()->url()->siteFullPath()));
            $meta = json_encode([
                'hash' => $this->leafcutter->content()->hash($url->sitePath(), $url->siteNamespace()),
                'time' => time(),
            ]);
            $script = <<<EOS

<!--staticPageMeta{{{{$meta}}}}-->
<script>if(window.Worker){new Worker('$scriptURL')}</script>

EOS;
            $content = str_replace('</body>', "$script</body>", $content, $matches);
            if ($matches != 1) {
                // something is wrong with the markup, and this page can't be statically cached
                return;
            }
            $fs = new Filesystem;
            $fs->put($content, $path, true);
        }
    }

    /**
     * Attempts to generate a valid save path for a static HTML
     * file to cache a given Response.
     *
     * @param Response $response
     * @return string
     */
    protected function savePath(Response $response): string
    {
        return $this->urlSavePath($response->url());
    }

    /**
     * Determine whether the given response should be cached as
     * as static HTML page.
     *
     * @param Response $response
     * @return boolean
     */
    protected function shouldSave(Response $response): bool
    {
        if ($response->dynamic()) {
            return false;
        }
        if ($response->status() != 200) {
            return false;
        }
        if ($response->url()->query()) {
            return false;
        }
        return true;
    }

    /**
     * Turns a URL into a full save path for the output file.
     *
     * @param URL $url
     * @return string
     */
    protected function urlSavePath(URL $url): string
    {
        $path = $url->siteFullPath();
        if ($path == '' || substr($path, -1) == '/') {
            $path .= 'index.html';
        }
        return $this->config('directory') . $path;
    }

    /**
     * Method is executed as the first step when this Addon is activated.
     *
     * @return void
     */
    public function activate(): void
    {
    }

    /**
     * Used after loading to give Leafcutter an array of event subscribers.
     * An easy way of rapidly developing simple Addons is to simply return [$this]
     * and put your event listener methods in this same single class.
     *
     * @return array
     */
    public function getEventSubscribers(): array
    {
        return [$this];
    }

    /**
     * Specify the names of the features this Addon provides. Some names may require
     * you to implement certain interfaces.
     *
     * @return array
     */
    public static function provides(): array
    {
        return ['static-pages'];
    }

    /**
     * Specify an array of the names of features this Addon requires. Leafcutter
     * will attempt to automatically load the necessary Addons to provide these
     * features when this Addon is loaded.
     *
     * @return array
     */
    public static function requires(): array
    {
        return [];
    }

    /**
     * Return the canonical name of this plugin. Generally this should be the
     * same as the composer package name, so this example pulls it from your
     * composer.json automatically.
     *
     * @return string
     */
    public static function name(): string
    {
        if ($data = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true)) {
            return $data['name'];
        }
        return 'unknown/unknownaddon';
    }
}
