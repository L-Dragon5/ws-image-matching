<?php

namespace App\Http\Controllers;

set_time_limit(0);
header('X-Accel-Buffering: no');

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use GrahamCampbell\Flysystem\FlysystemManager;
use Intervention\Image\Facades\Image;

class PastecIndexController extends Controller
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
     * Updated Pastec image index via local DB and images.
     * 
     * @return void
     */
    public function updateImageIndex() {
        $lastUpdatedId = intval(DB::table('settings')
            ->where('setting_key', 'indexInsert_lastId')
            ->first()->value);

        $cards = DB::table('cards')
            ->select('id', 'card_id')
            ->orderBy('id', 'ASC')
            ->skip($lastUpdatedId - 1)
            ->take(PHP_INT_MAX)
            ->get();

        $counter = 0;

        if (ob_get_level() == 0) ob_start();
        foreach ($cards as $card) {
            $uuid = $card->id;
            $cardId = $card->card_id;

            $imgCardId = str_replace('/', '_', $cardId);
            if ($this->flysystem->has("$imgCardId.png")) {
                $img = (string) Image::make($this->flysystem->read("$imgCardId.png"))->encode('jpg', 100);
            } else if ($this->flysystem->has("$imgCardId.gif")) {
                $img = (string) Image::make($this->flysystem->read("$imgCardId.gif"))->encode('jpg', 100);
            }

            if (!empty($img)) {
                $url = "http://localhost:4212/index/images/$uuid";
                $header = [
                    'Accept: application/json',
                ];
                $ch = curl_init();
                $timeout = 10;
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $img);
                $result = json_decode(curl_exec($ch), true);
                curl_close($ch);

                if (!empty($result)) {
                    echo "$uuid complete.<br>";
                } else {
                    echo "Something went wrong with $uuid";
                }

                ob_flush();
                flush();            
            }

            $counter++;
            if ($counter >= 50) {
                $this->saveImageIndex();

                DB::table('settings')->updateOrInsert([
                    'setting_key' => 'indexInsert_lastId'
                ], [
                    'setting_key' => 'indexInsert_lastId',
                    'value' => $uuid,
                ]);

                $counter = 0;
            }
        }

        ob_end_flush();
        return;
    }

    /**
     * Updated Pastec image index via local DB and images.
     * 
     * @return void
     */
    public function updateImageIndexById(Request $request, $id) {
        if (ob_get_level() == 0) ob_start();
        
        $card = DB::table('cards')
            ->select('id', 'card_id')
            ->where('id', $id)
            ->first();
        $uuid = $card->id;
        $cardId = $card->card_id;

        $imgCardId = str_replace('/', '_', $cardId);
        if ($this->flysystem->has("$imgCardId.png")) {
            $img = (string) Image::make($this->flysystem->read("$imgCardId.png"))->encode('jpg', 100);
        } else if ($this->flysystem->has("$imgCardId.gif")) {
            $img = (string) Image::make($this->flysystem->read("$imgCardId.gif"))->encode('jpg', 100);
        }

        if (!empty($img)) {
            $url = "http://localhost:4212/index/images/$uuid";
            $header = [
                'Accept: application/json',
            ];
            $ch = curl_init();
            $timeout = 10;
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $img);
            $result = json_decode(curl_exec($ch), true);
            curl_close($ch);

            if (!empty($result)) {
                echo "$uuid complete.<br>";
            } else {
                echo "Something went wrong with $uuid";
            }

            ob_flush();
            flush();            
        }

        $this->saveImageIndex();

        ob_end_flush();
        return;
    }

    /**
     * Save Pastec image index.
     * 
     * @return void
     */
    public function saveImageIndex() {
        $url = "http://localhost:4212/index/io";
        $header = [
            'Accept: application/json',
        ];
        $data = [
            'type' => 'WRITE',
            'index_path' => 'cards.dat',
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

        echo "Saved image index.<br>";
    }
}
