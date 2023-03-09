<?php
namespace Usility\PageFactory;


const VISITS_FILE       = 'site/logs/visits.yaml';
const VISITS_SINCE_FILE = 'site/logs/visits-since.txt';
const VISITS_BOTS_FILE  = 'site/logs/visits_bots.txt';

function countvisits($argStr = '')
{
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'show'      => ['[false|loggedin] Whether to show result or record it silently.', true],
            'prefix'    => ['What to put in front of result', ''],
            'postfix'   => ['What to put behind result', ''],
        ],
        'summary' => <<<EOT
# countvisits()

Counts visits per page and returns the count.

Excludes visits from bots and IP-addresses defined in `site/config/config.php:

    'usility.pagefactory.options' \=> [
        'visitCounterIgnoreIPs' \=> '001.002.003.005,::1', \// define list of IP addresses to exclude from visit counts
    ],

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($str = TransVars::initMacro(__FILE__, $config, $argStr))) {
        return $str;
    } else {
        list($options, $sourceCode, $inx, $funcName) = $str;
        $str = $sourceCode;
    }

    // assemble output:
    $obj = new CountVisits();
    $str .= $obj->render($options);

    return $str;
}


class CountVisits
{
    public static $inx = 1;

    /**
     * Macro rendering method
     * @param array $args
     * @param string $argStr
     * @return string
     */
    public function render($args): string
    {
        $prefix = $args['prefix'].' ';
        $postfix = ' '.$args['postfix'];
        $show = $args['show'];
        if ($show[0]??'' === 'l') {
            $show = isLoggedinOrLocalhost();
        }
        $visits = $this->countVisits();
        if ($show) {
            return "$prefix$visits$postfix";
        }
        return '';
    } // render


    /**
     * @return int|mixed|string
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function countVisits()
    {
        $ipsToIgnore = PageFactory::$config['visitCounterIgnoreIPs']??'';
        $file = VISITS_FILE;
        if (isBot()) {
            $file = VISITS_BOTS_FILE;
        }
        $pgId = page()->id();
        $clientIp = $this->getClientIP(true);
        if (!file_exists($file)) {
            file_put_contents($file, "$pgId: 0");
            file_put_contents(VISITS_SINCE_FILE, date('Y-m-d H:i:s'));
            $count = 0;
        } else {
            $counters = loadFile($file);
            if (isset($counters[$pgId])) {
                $count = $counters[$pgId]++;
            } else {
                $count = $counters[$pgId] = 1;
            }
            if (!str_contains($ipsToIgnore, $clientIp)) { // home
                writeFileLocking($file, $counters);
            }
        }
        //mylog("IP: $clientIp");
        return $count;
    } // countVisits


    /**
     * @param $normalize
     * @return array|false|string
     */
    private function getClientIP($normalize = false)
    {
        $ip = getenv('HTTP_CLIENT_IP')?:
            getenv('HTTP_X_FORWARDED_FOR')?:
                getenv('HTTP_X_FORWARDED')?:
                    getenv('HTTP_FORWARDED_FOR')?:
                        getenv('HTTP_FORWARDED')?:
                            getenv('REMOTE_ADDR');

        if ($normalize) {
            $elems = explode('.', $ip);
            foreach ($elems as $i => $e) {
                $elems[$i] = str_pad($e, 3, "0", STR_PAD_LEFT);
            }
            $ip = implode('.', $elems);
        }
        return $ip;
    } // getClientIP

} // CountVisits