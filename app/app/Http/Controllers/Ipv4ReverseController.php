<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Ipv4ReverseController extends Controller
{
    public function index(Request $request)
    {
        $customer = trim((string) $request->query('kunde', ''));
        $ip = trim((string) $request->query('ip', ''));

        $query = DB::connection('sqlsrv_accountings')
            ->table('tblDNSipv4ReverseEditor')
            ->select('intID','intIPbyte1','intIPbyte2','intIPbyte3','intIPbyte4','intKundenID')
            ->orderBy('intIPbyte1')->orderBy('intIPbyte2')->orderBy('intIPbyte3')->orderBy('intIPbyte4');

        if ($customer !== '' && ctype_digit($customer)) {
            $query->where('intKundenID', (int) $customer);
        }
        if ($ip !== '') {
            $parts = explode('.', $ip);
            foreach (array_slice($parts, 0, 4) as $i => $part) {
                if ($part !== '' && ctype_digit($part)) {
                    $query->where('intIPbyte'.($i + 1), (int) $part);
                }
            }
        }

        $rows = $query->paginate(100)->withQueryString();
        $view = session('frontend_mode','classic') === 'modern'
            ? 'modern.ipv4-reverse.index'
            : 'classic.ipv4-reverse.index';

        return view($view, compact('rows','customer','ip'));
    }
}
