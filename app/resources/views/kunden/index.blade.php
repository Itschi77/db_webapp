<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kunden</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            background: #f5f5f5;
        }

        .container {
            max-width: 1400px;
            margin: auto;
            background: white;
            padding: 24px;
            border-radius: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #eee;
        }

        input {
            padding: 10px;
            width: 350px;
        }

        button {
            padding: 10px 16px;
        }

        a {
            color: #005ea8;
            text-decoration: none;
        }

        .search {
            margin-bottom: 20px;
        }

        .pagination {
            margin-top: 20px;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Kunden</h1>

    <form method="GET" class="search">
        <input
            type="text"
            name="q"
            value="{{ request('q') }}"
            placeholder="Name, Ort, PLZ, E-Mail, Telefon..."
        >

        <button type="submit">
            Suchen
        </button>
    </form>

    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>PLZ</th>
            <th>Ort</th>
            <th>Telefon</th>
            <th>E-Mail</th>
            <th>Aktiv</th>
        </tr>
        </thead>

        <tbody>

        @foreach ($kunden as $kunde)

            <tr>
                <td>{{ $kunde->intID }}</td>

                <td>
                    <a href="{{ route('kunden.show', $kunde->intID) }}">
                        {{ $kunde->strName }}
                    </a>
                </td>

                <td>{{ $kunde->strPLZ }}</td>

                <td>{{ $kunde->strOrt }}</td>

                <td>{{ $kunde->strTelefon }}</td>

                <td>{{ $kunde->strEmail }}</td>

                <td>
                    {{ $kunde->boolAktiverKunde ? 'Ja' : 'Nein' }}
                </td>
            </tr>

        @endforeach

        </tbody>
    </table>

    <div class="pagination">
        {{ $kunden->links() }}
    </div>

</div>

</body>
</html>
