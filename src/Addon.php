<?php
namespace Leafcutter\Addons\Leafcutter\StaticPages;

use Leafcutter\Common\Filesystem;
use Leafcutter\Leafcutter;
use Leafcutter\Pages\PageInterface;
use Leafcutter\Plugins\AbstractPlugin;
use Leafcutter\Response;
use Leafcutter\URL;

class Addon extends \Leafcutter\Addons\AbstractAddon
{
    const DEFAULT_CONFIG = [
        "enabled" => true,
        "directory" => '${base_dir}/',
        "ttl" => 30,
    ];

    public function onResponseURL_namespace_staticPageBuild($url): ?Response
    {
        $rUrl = new URL('@/' . $url->sitePath());
        $currentHash = $this->leafcutter->content()->hash($rUrl->sitePath(), $rUrl->siteNamespace());
        $response = new Response();
        $response->setMime('application/javascript');
        $response->setTemplate(null);
        $response->header('cache-control', 'max-age=60, public');
        if ($this->needsRebuild($rUrl)) {
            if (is_file($this->urlSavePath($rUrl)) && !$this->config('enabled')) {
                $response->setText('');
                unlink($this->urlSavePath($rUrl));
                return $response;
            }
            $this->leafcutter->buildResponse($rUrl, false);
            $response->setText('');
            $response->doAfter(function () use ($rUrl) {
                $this->leafcutter->buildResponse($rUrl, false);
            });
        } else {
            $response->setText('');
        }
        return $response;
    }

    protected function needsRebuild($url)
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

    public function onResponseReturn($response)
    {
        if (!$this->config('enabled')) {
            return;
        }
        if ($path = $this->savePath($response)) {
            $url = $response->source()->url();
            $content = $response->content();
            $scriptURL = new URL('@/~staticPageBuild/' . $response->source()->url()->sitePath());
            $meta = json_encode([
                'hash' => $this->leafcutter->content()->hash($url->sitePath(), $url->siteNamespace()),
                'time' => time(),
            ]);
            $script = <<<EOS

<!--staticPageMeta{{{{$meta}}}}-->
<script src='$scriptURL' async defer></script>

EOS;
            $content = str_replace('</body>', "$script</body>", $content, $matches);
            if ($matches != 1) {
                return;
            }
            $fs = new Filesystem;
            $fs->put($content, $path, true);
        }
    }

    protected function savePath(Response $response): ?string
    {
        if ($response->dynamic()) {
            return null;
        }
        if ($response->status() != 200) {
            return null;
        }
        if (!($response->source() instanceof PageInterface)) {
            return null;
        }
        if ($response->source()->url()->query()) {
            return null;
        }
        return $this->urlSavePath($response->source()->url());
    }

    protected function urlSavePath(URL $url): string
    {
        $path = $url->siteFullPath();
        if ($path == '' || substr($path, -1) == '/') {
            $path .= 'index.html';
        }
        return $this->config('directory') .'/'. $path;
    }

    /**
     * Method is executed as the first step when this Addon is loaded for use.
     *
     * @return void
     */
    public function load(): void
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
        return ['staticpages'];
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
        if ($data = json_decode(file_get_contents(__DIR__.'/../composer.json'),true)) {
            return $data['name'];
        }
        return 'unknown/unknownaddon';
    }
}
