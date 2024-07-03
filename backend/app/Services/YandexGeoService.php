<?php

namespace App\Services;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;
use App\Models\Address;

class YandexGeoService
{
    private $client;
    private $apiKey;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = env('YANDEX_API_KEY');
    }

    public function fetchAddressDataAndMetros(string $address): array
    {
        // Фетчим базовые данные адрессов по запросу
        $geoObjects = $this->fetchGeoObjects($address);

        $results = [];
        foreach ($geoObjects as $geoObject) {
            // Для каждого найденного совпадения делаем запрос на получение
            // ближайших станций метро и группируем нужные даннные
            $results[] = $this->formatGeoObject($geoObject, $address);
        }

        $this->saveSearchedAddress($address);

        return $results;
    }

    private function fetchGeoObjects(string $address): array
    {
        $response = $this->client->get("https://geocode-maps.yandex.ru/1.x/", [
            'query' => [
                'geocode' => $address,
                'format' => 'json',
                'apikey' => $this->apiKey,
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

    private function formatGeoObject(array $geoObject): array
    {
        $formattedAddress = $this->getFormattedAddress($geoObject);
        list($street, $house, $district) = $this->extractAddressComponents($geoObject);
        $cords = $this->extractCoordinates($geoObject);

        $metroStations = $this->fetchMetroStations($cords);

        return [
            'formatted_address' => $formattedAddress,
            'street' => $street,
            'house' => $house,
            'district' => $district,
            'metroStations' => $metroStations
        ];
    }

    private function getFormattedAddress(array $geoObject): string
    {
        return $geoObject['metaDataProperty']['GeocoderMetaData']['Address']['formatted'];
    }

    private function extractAddressComponents(array $geoObject): array
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

    private function extractCoordinates(array $geoObject): string
    {
        $cords = $geoObject['Point']['pos'];
        $cords = explode(' ', $cords);
        return join(',', $cords);
    }

    private function fetchMetroStations(string $cords): array
    {
        $response = $this->client->get("https://geocode-maps.yandex.ru/1.x/", [
            'query' => [
                'geocode' => $cords,
                'format' => 'json',
                'apikey' => $this->apiKey,
                'kind' => 'metro',
                'results' => 5
            ]
        ]);

        $data = json_decode($response->getBody(), true);
        return $data['response']['GeoObjectCollection']['featureMember'];
    }

    private function saveSearchedAddress(string $address): void
    {
        if (!Address::where('searched_address', $address)->exists()) {
            Address::create(['searched_address' => $address]);
        }
    }
}
