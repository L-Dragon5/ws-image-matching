<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Intervention\Image\Facades\Image;

class CardSearchController extends Controller
{
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
            return json_encode($e);
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
                            ->select('card_id', 'jp_name')
                            ->where('id', $id)
                            ->first();
                        $card = (array) $card;
                        $card['en_translation_link'] = 'https://heartofthecards.com/code/cardlist.html?card=WS_' . $card['card_id'];
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
            return json_encode(true);
        }

        return json_encode(true);
    }
}
