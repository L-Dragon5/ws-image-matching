<?php

namespace App\Http\Controllers;

set_time_limit(0);
header('X-Accel-Buffering: no');

use Illuminate\Support\Facades\DB;
use GrahamCampbell\Flysystem\FlysystemManager;

class DataScraperController extends Controller
{
    /**
     * Flysystem storage instance.
     */
    protected $flysystem;

    /**
     * Create a new controller instance.
     * 
     * @param FlysystemManager  $flysystem
     * @return void
     */
    public function __construct(FlysystemManager $flysystem)
    {
        $this->flysystem = $flysystem;
    }

    /**
     * Retrieve card list from ws-tcg.com site.
     * 
     * @return void
     */
    public function retrieveCardList()
    {
        $counter = 1;
        $maxPage = 2;

        if (ob_get_level() == 0) ob_start();
        while ($counter <= $maxPage) {
            $url = "https://ws-tcg.com/cardlist/search?page=$counter";
            $ch = curl_init();
            $timeout = 5;
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                '_method' => 'POST',
                'cmd' => 'search',
                'show_page_count' => 100,
                'show_small' => 1
            ]));
            $html = curl_exec($ch);
            curl_close($ch);

            if (!empty($html)) {
                $dom = new \DOMDocument;
                @$dom->loadHTML($html);

                $searchResults = $dom->getElementById('searchResults');

                if ($counter === 1) {
                    $maxPage = $searchResults->getElementsByTagName('a')[8];
                    $maxPage = intval($maxPage->textContent);
                }

                $tables = $searchResults->getElementsByTagName('table');
                $tableOfIds = !empty($tables) ? $tables[0] : NULL;
                if ($tableOfIds !== NULL) {
                    $rows = $tableOfIds->getElementsByTagName('tr');
                    for ($i = 1; $i < $rows->length; $i++) {
                        $tds = $rows[$i]->getElementsByTagName('td');
                        $id = $tds[0]->textContent;

                        if (DB::table('cards')->where('card_id', $id)->count() < 1) {
                            DB::table('cards')->insert([
                                'card_id' => $id,
                            ]);
                        }
                    }
                }

                echo "Page $counter complete.<br>";
                $counter++;
                ob_flush();
                flush();
            }
        }

        ob_end_flush();
        return;
    }

    /**
     * Retrieve card data & images from ws-tcg.com site.
     * 
     * @return void
     */
    public function retrieveCardData() {
        $lastUpdatedId = intval(DB::table('settings')
            ->where('setting_key', 'imageScraper_lastId')
            ->first()->value);

        $cards = DB::table('cards')
            ->orderBy('id', 'ASC')
            ->skip($lastUpdatedId - 1)
            ->take(PHP_INT_MAX)
            ->get();

        if (ob_get_level() == 0) ob_start();
        foreach ($cards as $card) {
            $uuid = $card->id;
            $cardId = $card->card_id;

            // Retrieve card information.
            $url = "https://ws-tcg.com/cardlist/?cardno=$cardId";
            $ch = curl_init();
            $timeout = 5;
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            $html = curl_exec($ch);
            curl_close($ch);

            if (!empty($html)) {           
                $dom = new \DOMDocument;
                @$dom->loadHTML($html);

                $tables = $dom->getElementsByTagName('table');
                $tableOfDetail = !empty($tables) ? $tables[0] : NULL;
                if ($tableOfDetail !== NULL) {
                    $imgs = $tableOfDetail->getElementsByTagName('img');
                    $imgLocation = 'https://ws-tcg.com' . $imgs[0]->getAttribute('src');

                    $tds = $tableOfDetail->getElementsByTagName('td');
                    $jpName = $tds[1]->childNodes[0]->textContent;
                    $jpBody = $tds[16]->textContent;
                    $jpTraits = trim($tds[15]->textContent);
                    $jpTraits = explode('・', $jpTraits);

                    DB::table('cards')->updateOrInsert([
                        'id' => $uuid,
                        'card_id' => $cardId,
                    ], [
                        'jp_name' => !empty($jpName) ? $jpName : NULL,
                        'jp_body' => (!empty($jpBody) && strlen($jpBody) > 1)  ? $jpBody : NULL,
                        'jp_trait_1' => (isset($jpTraits[0]) && $jpTraits[0] !== '-') ? $jpTraits[0] : NULL,
                        'jp_trait_2' => (isset($jpTraits[1]) && $jpTraits[1] !== '-') ? $jpTraits[1] : NULL,
                        'jp_trait_3' => (isset($jpTraits[2]) && $jpTraits[2] !== '-') ? $jpTraits[2] : NULL,
                        'jp_trait_4' => (isset($jpTraits[3]) && $jpTraits[3] !== '-') ? $jpTraits[3] : NULL,
                    ]);
                }
            }

            $imgCardId = str_replace('/', '_', $card->card_id);
            // Download card image.
            if (!$this->flysystem->has("$imgCardId.png") && !$this->flysystem->has("$imgCardId.gif")) {
                $img = file_get_contents($imgLocation);
                $this->flysystem->put("$imgCardId.png", $img);
            }

            DB::table('settings')->updateOrInsert([
                'setting_key' => 'imageScraper_lastId'
            ], [
                'setting_key' => 'imageScraper_lastId',
                'value' => $uuid,
            ]);

            echo "$uuid - $cardId complete.<br>";
            ob_flush();
            flush();
        }

        ob_end_flush();
        return;
    }

    /**
     * Retrieve card translations via Google Translate API.
     * 
     * @return void
     */
    public function retrieveCardTranslations() {
        $jpCards = DB::table('cards')
            ->select('jp_name', 'jp_body')
            ->distinct()
            ->get();

        $characterTotal = 0;

        foreach ($jpCards as $jpCard) {
            $characterTotal += strlen($jpCard->jp_body);
        }

        var_dump($characterTotal);

        return;
    }

    /**
     * Retrieve card pricing from yuyu-tei.jp site.
     * 
     * @return void
     */
    public function retrieveYYTPrices() {
        // $setcodes = ['dc', 'dcext1.0', 'dcext2.0', 'dcpc', 'dcext3.0', 'dc3', 'animedcext1.0', 'dc10th', 'dcsakuraext', 'dsdc', 'dcvslbdc', 'dc20th', 'lb', 'lbext1.0', 'lbe', 'animelb', 'animelbrext', 'lbcmext', 'dcvslblb', 'ab', 'abre', 'abext', 'abext2.0', 'zm', 'zmf', 'zmfext', 'ns', 'nsm', 'nsa', 'nsm2', 'nsm1m2', 'nsr', 'nsd', 'vvs', 'ls', 'skext1.0', 'mhext1.0', 'skext2.0', 'mhext2.0', 'pr', 'sh', 'shext', 'shpset', 'ir', 'ir2.0', 'irs', 'irspset', 'ss', 'ssext', 'clext1.0', 'clext2.0', 'clext3.0', 'clpset', 'rw', 'rwhf', 'rwanime', 'njext', 'ddext1.0', 'ddext2.0', 'ddext3.0', 'magica', 'magicamv', 'magicamagireco', 'magicamagireco2.0', 'symphogear', 'symphogearg', 'symphogeargx', 'symphogearxd', 'symphogearxded', 'symphogearaxz', 'symphogearxv', 'robono', 'vivid', 'lovelive', 'lovelive2.0', 'lovelivesif', 'lovelivesif2.0', 'lovelivesif3.0', 'lovelivesifvset', 'loveliveext', 'lovelivesimext', 'lovelivess', 'lovelivessext', 'lovelivess2.0', 'lovelivesssif', 'lovelivenj', 'lovelivesp', 'genei', 'nisekoi', 'nisekoiext', 'gf', 'gf2.0', 'tld2nd', 'tld2nd2.0', 'charlotte1.0', 'imc', 'imc2nd', 'gochiusa', 'gochiusaext', 'gochiusadms', 'gochiusabloom', 'bd', 'bd4.0', 'bd2.0', 'bd3.0', 'bd2.0td', 'bdsp', 'bdpb', 'bdextmorras', 'bdextppros', 'konosuba', 'konosuba2.0', 'konosubare', 'konosuba3.0', 'kmn', 'hinaext1.0', 'hinaext2.0', 'saekano', 'saekano2.0', 'smp', 'smp2.0', 'yys', 'kadokawas', 'ccs', 'sby', 'sby2.0', 'fujimif', 'key20th', 'dal', 'dalext1.0', '5hy', 'prd', 'knk', 'dbg', 'p3', 'p3chronicle', 'p4', 'animep4', 'p5', 'p4ext', 'animep4ext1.0', 'p4uext1.0', 'pqext', 'dg', 'dgext1.0', 'dgd2ext', 'fs', 'fh', 'fz', 'fsubw', 'fsubw2.0', 'fzext', 'fshf', 'fshf2.0', 'fapo', 'fpi', 'fpi2.0', 'fpi2.0helz', 'fpi3.0', 'fpi4.0', 'se', 'sre', 'kf', 'sb', 'sbext1.0', 'im', 'imd', 'im2.0', 'animeim', 'imm', 'im765proext', 'impset', 'ims', 'isc', 'isctd', 'ft', 'ftext1.0', 'mb', 'eva', 'tmh', 'tmh2.0', 'tmh2nd', 'tmhext', 'tmhext2.0', 'tmhmovie', 'tmhffpp', 'tmhext3.0', 'brext1.0', 'katanaext', 'bake', 'nisem', 'monogatari2nd', 'mfi', 'gc', 'aw', 'awib', 'sao', 'sao2.0', 'saore', 'saoos', 'saoaz', 'saoaz2.0', 'sao10th', 'sao2ext', 'sao2ext2.0', 'ggo', 'kk', 'ca', 'miku', 'miku2.0', 'mikuext1.0', 'ppext', 'gargan', 'ds2ext', 'kill', 'killpset', 'logho', 'loghopset', 'kancolle', 'kancolle2.0', 'kancolle3.0', 'kancolle4.0', 'kancolleext', 'shinchan', 'gstext', 'tf', 'aot', 'aot2.0', 'sgs', 'puyopuyo', 'oms', 'rinneext', 'kiznaiver', 'rz', 'rz2.0', 'rz3.0', 'rzext1.0', 'chain', 'starwars', 'gurren', 'rsl', 'rsl2.0', 'godzilla', 'godzillaext1.0', 'darlifra', 'ngl', 'stg', 'ovl', 'gbs', 'jj', 'tsk', 'tsk2.0', 'gri', 'gri2.0', 'fgo', 'lily', 'skr', 'lod', 'bfr', 'kgl', 'mti', 'woo', 'siyoko', 'promo'];
        $setcodes = ['lovelivesp', 'gri2.0', 'mti', 'gochiusabloom', 'p3chronicle', 'bdextppros', 'symphogearxv', 'dbg', 'bdextmorras', 'dalext1.0'];
        if (ob_get_level() == 0) ob_start();
        foreach ($setcodes as $set) {
            // Retrieve set information.
            $url = "https://yuyu-tei.jp/game_ws/sell/sell_price.php?ver=$set";
            $ch = curl_init();
            $timeout = 5;
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            $html = curl_exec($ch);
            curl_close($ch);

            if (!empty($html)) {           
                $dom = new \DOMDocument;
                @$dom->loadHTML($html);
                $finder = new \DomXPath($dom);
                $classname="card_unit";
                $nodes = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");

                foreach ($nodes as $cardListing) {
                    $insertData = [
                        'yyt_set_code' => $set
                    ];
                    $cardListingId = $finder->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' id ')]", $cardListing);
                    $cardListingId = trim($cardListingId[0]->textContent);

                    $cardListingPrice = $finder->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' price ')]", $cardListing);
                    $cardListingPriceSale = $finder->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' sale ')]", $cardListing);
                    // Use sale price. If not, use normal price.
                    if (!empty($cardListingPriceSale) && $cardListingPriceSale->length > 0) {
                        $insertData['yyt_price'] = trim($cardListingPriceSale[0]->textContent);
                    } else {
                        $insertData['yyt_price'] = trim($cardListingPrice[0]->textContent);
                    }

                    DB::table('cards')
                        ->where('card_id', $cardListingId)
                        ->update($insertData);
                }
            }

            echo "$set complete.<br>";
            ob_flush();
            flush();
        }

        ob_end_flush();
        return;
    }
}
