<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Admin Panel</title>
    <style>
        * {
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            color: #F0F4F8;
        }

        body {
            display: grid;
            grid-template-rows: 150px 1fr;
            margin: 0;
        }

        header {
            text-align: center;
            background-color: #486581;
        }
        
        main {
            height: calc(100vh - 150px);
            display: grid;
            grid-template-rows: repeat(auto-fit, minmax(0px, 1fr));
        }

        main div {
            background-color: #627D98;
        }

        section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 32px;
        }

        section.two-up {
            grid-template-columns: repeat(2, minmax(150px, 1fr));
        }

        a {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 150px;
            flex-grow: 1;
            background-color: #3E4C59;
            text-decoration: none;
            font-size: 1.25rem;
        }

        a:hover {
            background-color: #1F2933;
        }
    </style>
</head>
<body>
    <header>
        <h1>Admin Panel</h1>
    </header>
    <main>
        <div>
            <h2>Card Information Retrieval</h2>
            <section class="two-up">
                <a href="/retrieveCardList">Retrieve Card List</a>
                <a href="/retrieveCardData">Retrieve Card Data</a>
                <a href="/retrieveYYTPrices">Retrieve YYT Prices</a>
                <a href="/retrieveCardTranslations">Retrieve Card Translations</a>
            </section>
        </div>

        <div>
            <h2>Pastec Index Functions</h2>
            <section>
                <a href="/updateImageIndex">Update Pastec Image Index</a>
                <a href="/saveImageIndex">Save Pastec Image Index</a>
            </section>
        </div>
    </main>
</body>
</html>
