<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use GuzzleHttp\Exception\RequestException;
use App\Services\YandexGeoService;
use App\Models\Address;

class AddressController extends Controller
{
    private $yandexGeoService;

    public function __construct(YandexGeoService $yandexGeoService)
    {
        $this->yandexGeoService = $yandexGeoService;
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
                return $this->yandexGeoService->fetchAddressDataAndMetros($address);
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
}
