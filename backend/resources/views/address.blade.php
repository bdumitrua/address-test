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
            <input type="text" required minlength="5" name="address" placeholder="Введите адрес"
                value="{{ old('address', $address ?? '') }}">
            <button type="submit">Поиск</button>
        </form>

        @if (isset($errorMessage))
            <h2 class="error">{{ $errorMessage }}</h2>
        @endif

        @if (isset($results) && count($results) > 0)
            <h1 style="margin-top: 60px">Результаты поиска:</h1>
            @foreach ($results as $index => $result)
                <div class="address-result">
                    <h2>Информация об адресе {{ $index + 1 }}:</h2>
                    <ul>
                        <li><strong>Адрес:</strong> {{ $result['formatted_address'] }}</li>
                        @if ($result['district'])
                            <li><strong>Район:</strong> {{ $result['district'] }}</li>
                        @endif
                        @if ($result['street'])
                            <li><strong>Улица:</strong> {{ $result['street'] }}</li>
                        @endif
                        @if ($result['house'])
                            <li><strong>Дом:</strong> {{ $result['house'] }}</li>
                        @endif
                    </ul>
                    @if (isset($result['metroStations']) && count($result['metroStations']) > 0)
                        <h3>Ближайшие станции метро:</h3>
                        <ol>
                            @foreach ($result['metroStations'] as $station)
                                <li>{{ $station['GeoObject']['name'] }} - {{ $station['GeoObject']['description'] }}
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            @endforeach
        @endif

        <h2>Сохраненные адреса:</h2>
        <ol>
            @foreach (App\Models\Address::all() as $savedAddress)
                <li>{{ $savedAddress->searched_address }}</li>
            @endforeach
        </ol>
    </div>
</body>

</html>
