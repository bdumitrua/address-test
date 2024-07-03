<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
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
        $validatedData = $request->validate([
            'address' => 'required|string|min:5|max:255',
        ]);
        $address = trim(preg_replace('/\s+/', ' ', $validatedData['address']));

        $client = new Client();
        $apiKey = env('YANDEX_API_KEY');

        $results = [];
        $errorMessage = null;

        $resultsCacheKey = 'results.' . $address;
        if (Cache::has($resultsCacheKey)) {
            $results = Cache::get($resultsCacheKey);
        } else {
            try {
                $geoObjects = $this->fetchGeoObjects($client, $apiKey, $address);

                foreach ($geoObjects as $geoObject) {
                    $formattedAddress = $this->getFormattedAddress($geoObject);
                    list($street, $house, $district) = $this->extractAddressComponents($geoObject);
                    $cords = $this->extractCoordinates($geoObject);

                    $metroStations = $this->fetchMetroStations($client, $apiKey, $cords);

                    // Сохранение входящего запроса в базу данных
                    if (!Address::where('searched_address', $address)->exists()) {
                        Address::create(['searched_address' => $address]);
                    }

                    $results[] = [
                        'formatted_address' => $formattedAddress,
                        'street' => $street,
                        'house' => $house,
                        'district' => $district,
                        'metroStations' => $metroStations
                    ];
                }

                Cache::put('results.' . $address, $results, now()->addDay());
            } catch (RequestException $e) {
                Log::error('Ошибка при запросе к API Яндекс.Карт: ' . $e->getMessage());

                $errorMessage = 'Ошибка при запросе к API Яндекс.Карт. Пожалуйста, попробуйте позже.';
            } catch (NotFoundHttpException $e) {
                $errorMessage = 'Ошибка при запросе к API Яндекс.Карт: данных по указаному адресу не найдено.';
            }
        }

        return view('address', compact('results', 'address', 'errorMessage'));
    }

    private function fetchGeoObjects(Client $client, $apiKey, $address)
    {
        $response = $client->get("https://geocode-maps.yandex.ru/1.x/", [
            'query' => [
                'geocode' => $address,
                'format' => 'json',
                'apikey' => $apiKey,
                'results' => 5
            ]
        ]);

        $data = json_decode($response->getBody(), true);
        if (empty($data['response']['GeoObjectCollection']['featureMember'])) {
            throw new NotFoundHttpException('No results found for the provided address.');
        }

        return array_map(function ($featureMember) {
            return $featureMember['GeoObject'];
        }, $data['response']['GeoObjectCollection']['featureMember']);
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
