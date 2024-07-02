<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Московский Адрес</title>
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}">
</head>

<body>
    <div class="container">
        <h1>Введите адрес в Москве</h1>
        <form action="/get-info" method="post">
            @csrf
            <input type="text" name="address" placeholder="Введите адрес"
                value="{{ old('address', $address ?? '') }}">
            <button type="submit">Поиск</button>
        </form>

        @if (isset($errorMessage))
            <h2 class="error">{{ $errorMessage }}</h2>
        @endif

        @if (isset($district) || isset($street) || isset($house))
            <h2>Информация об адресе:</h2>
            <ul>
                @if ($district)
                    <li><strong>Район:</strong> {{ $district }}</li>
                @endif
                @if ($street)
                    <li><strong>Улица:</strong> {{ $street }}</li>
                @endif
                @if ($house)
                    <li><strong>Дом:</strong> {{ $house }}</li>
                @endif
            </ul>
        @endif

        @if (isset($metroStations) && count($metroStations) > 0)
            <h2>Ближайшие станции метро:</h2>
            <ul>
                @foreach ($metroStations as $station)
                    <li>{{ $station['GeoObject']['name'] }} - {{ $station['GeoObject']['description'] }}</li>
                @endforeach
            </ul>
        @endif

        <h2>Сохраненные адреса:</h2>
        <ul>
            @foreach (App\Models\Address::all() as $savedAddress)
                <li>{{ $savedAddress->formatted_address }}</li>
            @endforeach
        </ul>
    </div>
</body>

</html>
