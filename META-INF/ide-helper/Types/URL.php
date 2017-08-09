<?php
namespace Zan\Framework\Utilities\Types;

use Zan\Framework\Foundation\Core\Config;
use Zan\Framework\Foundation\Exception\System\InvalidArgumentException;
use Zan\Framework\Network\Http\Response\RedirectResponse;
use Zan\Framework\Sdk\Cdn\Qiniu;

class URL
{

    const SCHEME_HTTP = 'http';
    const SCHEME_HTTPS = 'https';

    private static $schemes = [
        self::SCHEME_HTTP,
        self::SCHEME_HTTPS,
    ];

    private static $config;

    public static function setConfig(array &$config)
    {
        self::$config = &$config;
    }

    /**
     * @param bool $index
     * @param bool $scheme
     * @return string
     * @throws InvalidArgumentException
     */
    public static function base($index = FALSE, $scheme = false)
    {
        if (false !== $scheme && !self::_checkScheme($scheme)) {
            throw new InvalidArgumentException('Invalid scheme for URL');
        }
        $baseUrl = '/';
        $siteDomain = '';
        $scheme = (false === $scheme) ? self::SCHEME_HTTP : $scheme;

        if (is_string($index) || strlen($index)) {
            $siteDomain = isset(self::$config[$index]) ? self::$config[$index] : null;
            if (empty(($siteDomain))) {
                $siteDomain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
                $siteDomain = $scheme . '://' . $siteDomain;
            }
        }

        if (true === $index) {
            $siteDomain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
            $siteDomain = $scheme . '://' . $siteDomain;
        }

        return rtrim($siteDomain . $baseUrl, '/') . '/';
    }

    /**
     * @param string $url
     * @param bool $index
     * @param bool $scheme
     * @return string
     * @throws InvalidArgumentException
     */
    public static function site($url = '', $index = TRUE, $scheme = false)
    {
        if (false !== $scheme && !self::_checkScheme($scheme)) {
            throw new InvalidArgumentException('Invalid scheme for URL::site');
        }

        $urlAnalysis = parse_url($url);
        $host = isset($urlAnalysis['host']) ? $urlAnalysis['host'] : '';
        $path = isset($urlAnalysis['path']) ? trim($urlAnalysis['path'], '/') : '';
        $query = isset($urlAnalysis['query']) ? '?' . $urlAnalysis['query'] : '';
        $fragment = isset($urlAnalysis['fragment']) ? '#' . $urlAnalysis['fragment'] : '';

        if (!empty($host)) {
            $scheme = isset($urlAnalysis['scheme']) ? $urlAnalysis['scheme'] : self::SCHEME_HTTPS;
            if (!self::_checkScheme($scheme)) {
                throw new InvalidArgumentException('Invalid url for URL::site');
            }
            $baseUrl = $scheme . '://' . $host . '/';
        } else {
            $baseUrl = URL::base($index, $scheme);
        }

        $url = $baseUrl . $path . $query . $fragment;

        return $url;
    }


    /**
     * This method returns cdn url.
     *
     * @param $url
     * @param $imgExt
     * @param $scheme
     * @param $removeImgExt
     * @return string
     * @throws InvalidArgumentException
     */
    public static function cdnSite($url, $imgExt = null, $scheme = false, $removeImgExt = false)
    {
        if (false !== $scheme && !self::_checkScheme($scheme)) {
            throw new InvalidArgumentException('Invalid scheme for URL::cdnSite');
        }

        if ($removeImgExt && ($pos = strrpos($url, '!'))) {
            $url = substr($url, 0, $pos);
        }

        $noPic = Config::get("zan_url.cdn.no_pic", "no_pic.png");
        $url = self::site((strlen($url) ? ($url . $imgExt) : $noPic), 'imgqn', $scheme);

        if ($pattern = Config::get("zan_url.cdn.qn_pattern")) {
            if (!preg_match($pattern, $url)) {
                $url = Qiniu::site($url);
            }
        }

        return self::_convertWebp($url);
    }


    public static function removeParams($ps=null,$url=null)
    {
        if(!$ps || !$url){
            yield $url;
            return;
        }
        $pos   = strpos($url,'?');
        if(false === $pos){
            yield $url;
            return;
        }
        if(!is_array($ps)){
            $ps = [$ps];
        }
        $prefix = substr($url,0,$pos);
        $suffix = substr($url,$pos+1);
        $pMap   = [];
        parse_str($suffix,$pMap);
        foreach($ps as $p){
            if(isset($pMap[$p])){
                unset($pMap[$p]);
            }
        }

        yield $prefix . '?' . http_build_query($pMap);
    }

    public static function redirect($url,$code=302)
    {
        return  new RedirectResponse($url,$code);
    }

    /**
     * check the scheme is valid
     *
     * @param $scheme
     * @return bool
     */
    private static function _checkScheme($scheme)
    {
        return in_array($scheme, self::$schemes);
    }

    /**
     * cdn url convert to webp
     *
     * @param $imgSrc
     * @param $canWebp
     * @return string
     */
    private static function _convertWebp($imgSrc, $canWebp = false)
    {
        $multiple = 1;
        $pattern = '/\.([^.!]+)\!([0-9]{1,4})x([0-9]{1,4})(\+2x)?\.(.*)/';
        preg_match($pattern, $imgSrc, $matches);
        if ($matches && count($matches) >= 4) {
            if ('+2x' == $matches[4]) {
                $multiple = 2;
            }
            $extName = strtolower($matches[1]);
            $imgSrc = preg_replace($pattern, '.', $imgSrc) . $matches[1] . '?imageView2/2/w/' . (int)$matches[2] * $multiple . '/h/' . (int)$matches[3] * $multiple . '/q/75/format/' . ($canWebp ? ($extName == 'gif' ? 'gif' : 'webp') : $extName);
        }

        return $imgSrc;
    }

    public static function parseUrl($url)
    {
        $result = parse_url($url);
        $result['path'] = isset($result['path']) ? ltrim($result['path'], '/v2/') : '';
        if(isset($result['query'])) {
            parse_str($result['query'], $result['query']);
        }else{
            $result['query'] = [];
        }
        $result['fragment'] = isset($result['fragment']) ? $result['fragment'] : [];
        return $result;
    }

}
