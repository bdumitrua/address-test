<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;
use App\Models\Address;

class AddressController extends Controller
{
    public function index()
    {
        return view('address');
    }

    public function getInfo(Request $request)
    {
        $address = $request->input('address');

        $client = new Client();
        $apiKey = env('YANDEX_API_KEY');
        $results = [];
        $errorMessage = null;
        $metroStations = [];
        $street = '';
        $house = '';
        $district = '';
        $formattedAddress = '';
        $cords = '';

        try {
            $geoObject = $this->fetchGeoObject($client, $apiKey, $address);

            $formattedAddress = $this->getFormattedAddress($geoObject);
            list($street, $house, $district) = $this->extractAddressComponents($geoObject);

            $cords = $this->extractCoordinates($geoObject);
        } catch (RequestException $e) {
            $errorMessage = 'Ошибка при запросе к API Яндекс.Карт: ' . $e->getMessage();
        }

        if (!$errorMessage && $cords) {
            try {
                $metroStations = $this->fetchMetroStations($client, $apiKey, $cords);

                // Сохранение адреса в базу данных
                if (!Address::where('formatted_address', $formattedAddress)->exists()) {
                    Address::create(['formatted_address' => $formattedAddress]);
                }
            } catch (RequestException $e) {
                $errorMessage = 'Ошибка при запросе к API Яндекс.Карт: ' . $e->getMessage();
            }
        }

        return view('address', compact('results', 'address', 'metroStations', 'errorMessage', 'street', 'house', 'district'));
    }

    private function fetchGeoObject(Client $client, $apiKey, $address)
    {
        $response = $client->get("https://geocode-maps.yandex.ru/1.x/", [
            'query' => [
                'geocode' => $address,
                'format' => 'json',
                'apikey' => $apiKey,
                'results' => 1
            ]
        ]);

        $data = json_decode($response->getBody(), true);
        return $data['response']['GeoObjectCollection']['featureMember'][0]['GeoObject'];
    }

    private function getFormattedAddress($geoObject)
    {
        return $geoObject['metaDataProperty']['GeocoderMetaData']['Address']['formatted'];
    }

    private function extractAddressComponents($geoObject)
    {
        $addressComponents = $geoObject['metaDataProperty']['GeocoderMetaData']['Address']['Components'];
        $street = '';
        $house = '';
        $district = '';

        foreach ($addressComponents as $component) {
            if ($component['kind'] == 'street') {
                $street = $component['name'];
            } elseif ($component['kind'] == 'house') {
                $house = $component['name'];
            } elseif ($component['kind'] == 'locality') {
                $district = $component['name'];
            }
        }

        return [$street, $house, $district];
    }

    private function extractCoordinates($geoObject)
    {
        $cords = $geoObject['Point']['pos'];
        $cords = explode(' ', $cords);
        return join(',', $cords);
    }

    private function fetchMetroStations(Client $client, $apiKey, $cords)
    {
        $response = $client->get("https://geocode-maps.yandex.ru/1.x/", [
            'query' => [
                'geocode' => $cords,
                'format' => 'json',
                'apikey' => $apiKey,
                'kind' => 'metro',
                'results' => 5
            ]
        ]);

        $data = json_decode($response->getBody(), true);
        return $data['response']['GeoObjectCollection']['featureMember'];
    }
}
