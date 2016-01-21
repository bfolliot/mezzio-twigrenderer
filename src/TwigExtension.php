<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Twig;

use Twig_Extension;
use Twig_SimpleFunction;
use Zend\Expressive\Helper\ServerUrlHelper;
use Zend\Expressive\Helper\UrlHelper;

/**
 * Twig extension for rendering URLs and assets URLs from Expressive.
 *
 * @author Geert Eltink (https://xtreamwayz.github.io)
 */
class TwigExtension extends Twig_Extension
{
    /**
     * @var ServerUrlHelper
     */
    private $serverUrlHelper;

    /**
     * @var UrlHelper
     */
    private $urlHelper;

    /**
     * @var string
     */
    private $assetsUrl;

    /**
     * @var string
     */
    private $assetsVersion;

    /**
     * @param ServerUrlHelper $serverUrlHelper
     * @param UrlHelper       $urlHelper
     * @param string          $assetsUrl
     * @param string          $assetsVersion
     */
    public function __construct(
        ServerUrlHelper $serverUrlHelper,
        UrlHelper $urlHelper,
        $assetsUrl,
        $assetsVersion
    ) {
        $this->serverUrlHelper = $serverUrlHelper;
        $this->urlHelper       = $urlHelper;
        $this->assetsUrl       = $assetsUrl;
        $this->assetsVersion   = $assetsVersion;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'zend-expressive';
    }

    /**
     * @return Twig_SimpleFunction[]
     */
    public function getFunctions()
    {
        return [
            new Twig_SimpleFunction('absolute_url', [$this, 'renderUrlFromPath']),
            new Twig_SimpleFunction('asset', [$this, 'renderAssetUrl']),
            new Twig_SimpleFunction('path', [$this, 'renderUri']),
            new Twig_SimpleFunction('url', [$this, 'renderUrl']),
        ];
    }

    /**
     * Render relative uri for a given named route
     *
     * Usage: {{ path('article_show', {'id': '3'}) }}
     * Generates: /article/3
     *
     * @param null  $route
     * @param array $params
     *
     * @return string
     */
    public function renderUri($route = null, $params = [])
    {
        return $this->urlHelper->generate($route, $params);
    }

    /**
     * Render absolute url for a given named route
     *
     * Usage: {{ url('article_show', {'slug': 'article.slug'}) }}
     * Generates: http://example.com/article/article.slug
     *
     * @param null  $route
     * @param array $params
     *
     * @return string
     */
    public function renderUrl($route = null, $params = [])
    {
        return $this->serverUrlHelper->generate($this->urlHelper->generate($route, $params));
    }

    /**
     * Render absolute url from a path
     *
     * Usage: {{ absoulte_url('path/to/something') }}
     * Generates: http://example.com/path/to/something
     *
     * @param $path
     *
     * @return string
     */
    public function renderUrlFromPath($path)
    {
        return $this->serverUrlHelper->generate($path);
    }

    /**
     * Usage: {{ asset('path/to/asset/name.ext', version=3) }}
     *
     * @param $path
     * @param null $version
     * @return string
     */
    public function renderAssetUrl($path, $version = null)
    {
        $assetsVersion = ($version !== null && $version !== '') ? $version : $this->assetsVersion;

        // One more time, in case $this->assetsVersion was null or an empty string
        $assetsVersion = ($assetsVersion !== null && $assetsVersion !== '') ? '?v=' . $assetsVersion : '';

        return $this->assetsUrl . $path . $assetsVersion;
    }
}
