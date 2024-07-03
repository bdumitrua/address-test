<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;
use App\Models\Address;

class AddressController extends Controller
{
    private $client;
    private $apiKey;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = env('YANDEX_API_KEY');
    }

    public function index(): View
    {
        return view('address');
    }

    public function getInfo(Request $request): View
    {
        $address = $this->validateAndCleanAddress($request);

        $resultsCacheKey = 'results.' . $address;
        $errorMessage = null;

        $results = Cache::remember($resultsCacheKey, now()->addDay(), function () use ($address, &$errorMessage) {
            try {
                return $this->fetchAddressData($address);
            } catch (RequestException $e) {
                Log::error('Ошибка при запросе к API Яндекс.Карт: ' . $e->getMessage());
                $errorMessage = 'Ошибка при запросе к API Яндекс.Карт. Пожалуйста, попробуйте позже.';
            } catch (NotFoundHttpException $e) {
                $errorMessage = 'Ошибка при запросе к API Яндекс.Карт: данных по указанному адресу не найдено.';
            } catch (\Throwable $e) {
                Log::error('Непредвиденная ошибка при запросе к API Яндекс.Карт:' . $e->getMessage());
                $errorMessage = 'Непредвиденная ошибка: ' . $e->getMessage();
            }
        });

        return view('address', ['results' => $results, 'address' => $address, 'errorMessage' => $errorMessage]);
    }

    private function validateAndCleanAddress(Request $request): string
    {
        $validatedData = $request->validate([
            'address' => 'required|string|min:5|max:255',
        ]);
        return trim(preg_replace('/\s+/', ' ', $validatedData['address']));
    }

    private function fetchAddressData(string $address): array
    {
        $geoObjects = $this->fetchGeoObjects($address);

        $results = [];
        foreach ($geoObjects as $geoObject) {
            $results[] = $this->formatGeoObject($geoObject, $address);
        }

        return $results;
    }

    /**
     * @throws NotFoundHttpException
     */
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

    private function formatGeoObject(array $geoObject, string $address): array
    {
        $formattedAddress = $this->getFormattedAddress($geoObject);
        list($street, $house, $district) = $this->extractAddressComponents($geoObject);
        $cords = $this->extractCoordinates($geoObject);

        $metroStations = $this->fetchMetroStations($cords);

        $this->saveSearchedAddress($address);

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
