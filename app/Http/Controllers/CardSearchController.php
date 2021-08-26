<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Intervention\Image\Facades\Image;
use GrahamCampbell\Flysystem\FlysystemManager;

class CardSearchController extends Controller
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
     * Search card via image and retrieve data.
     * 
     * @param   Request   $request
     * @return  Response
     */
    public function searchByImage(Request $request) {
        // Validate that image is sent.
        try {
            $this->validate($request, [
                'image' => 'imageable|required',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json($e);
        }

        // Read image, resize, and re-encode as jpg.
        $img = Image::make($request->input('image'));
        $img->resize(800, 800, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        $img = (string) $img->encode('jpg', 80);
        $card = ['error' => 'Error processing image'];

        if (!empty($img)) {
            $url = "http://localhost:4212/index/searcher";
            $header = [
                'Accept: application/json',
            ];
            $ch = curl_init();
            $timeout = 20;
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $img);
            $result = json_decode(curl_exec($ch), true);
            curl_close($ch);

            if (!empty($result)) {
                if (isset($result['type']) && $result['type'] === 'SEARCH_RESULTS') {
                    if (!empty($result['image_ids'])) {
                        $id = reset($result['image_ids']);
                        $card = DB::table('cards')
                            ->select('card_id', 'jp_name', 'yyt_price', 'yyt_last_updated')
                            ->where('id', $id)
                            ->first();
                        $card = (array) $card;
                        $card['en_translation_link'] = 'https://heartofthecards.com/code/cardlist.html?card=WS_' . $card['card_id'];

                        DB::table('request_log')->insert([
                            'request_type' => 'image',
                            'card_id' => $card['card_id'],
                            'ip_address' => $request->ip(),
                        ]);
                    } else {
                        $card['error'] = 'Could not find card associated with image.';
                    }
                } else {
                    $card['error'] = 'Failed while processing image.';
                }
            }
        }

        return response()->json($card);
    }

    /**
     * Search cards via text and retrieve data.
     * 
     * @param   Request   $request
     * @return  Response
     */
    public function searchByText(Request $request) {
        try {
            $this->validate($request, [
                'search' => 'string|min:3|max:255|required',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json($e);
        }

        $searchText = $request->input('search');
        $returnValue = [];

        if (!empty($searchText)) {
            $cards = DB::table('cards')
                ->select('card_id', 'jp_name', 'yyt_price', 'yyt_last_updated')
                ->where('card_id', 'LIKE', "%{$searchText}%")
                ->limit(150)
                ->get();

            foreach ($cards as $card) {
                $imgCardId = str_replace('/', '_', $card->card_id);

                $tmp = (array) $card;
                $tmp['en_translation_link'] = 'https://heartofthecards.com/code/cardlist.html?card=WS_' . $tmp['card_id'];
                if ($this->flysystem->has("$imgCardId.png")) {
                    $img = Image::make($this->flysystem->read("$imgCardId.png"));
                    $img->resize(100, 100, function ($constraint) {
                        $constraint->aspectRatio();
                    });
                    $tmp['image'] = (string) $img->encode('data-url');
                }
                
                $returnValue[] = $tmp;
            }

            DB::table('request_log')->insert([
                'request_type' => 'search',
                'card_id' => $searchText,
                'ip_address' => $request->ip(),
            ]);
        }

        return response()->json($returnValue);
    }

    /**
     * Search cards via ids and retrieve data.
     * 
     * @param   Request   $request
     * @return  Response
     */
    public function searchByIds(Request $request) {
        try {
            $this->validate($request, [
                'cards' => 'array|required',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json($e);
        }

        $cardIds = $request->input('cards');
        $returnValue = [];

        if (!empty($cardIds)) {
            $cards = DB::table('cards')
                ->select('card_id', 'jp_name', 'yyt_price', 'yyt_last_updated')
                ->whereIn('card_id', $cardIds)
                ->get();

            foreach ($cards as $card) {
                $imgCardId = str_replace('/', '_', $card->card_id);

                $tmp = (array) $card;
                $tmp['en_translation_link'] = 'https://heartofthecards.com/code/cardlist.html?card=WS_' . $tmp['card_id'];
                if ($this->flysystem->has("$imgCardId.png")) {
                    $img = Image::make($this->flysystem->read("$imgCardId.png"));
                    $img->resize(100, 100, function ($constraint) {
                        $constraint->aspectRatio();
                    });
                    $tmp['image'] = (string) $img->encode('data-url');
                }
                
                $returnValue[] = $tmp;
            }

            DB::table('request_log')->insert([
                'request_type' => 'history',
                'card_id' => 'bulk',
                'ip_address' => $request->ip(),
            ]);
        }

        return response()->json($returnValue);
    }

    /**
     * Ping server for a response.
     * 
     * @return  Response
     */
    public function pingServer() {
        $url = "http://localhost:4212/";
        $header = [
            'Accept: application/json',
        ];
        $data = [
            'type' => 'PING',
        ];
        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $result = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (isset($result['type']) && $result['type'] === 'PONG') {
            return response()->json(true);
        }

        return response()->json(true);
    }
}
