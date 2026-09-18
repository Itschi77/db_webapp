<?php

namespace App\Http\Controllers;

use App\Services\DunningLetterService;
use App\Services\DunningService;
use App\Services\DunningWriteService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use RuntimeException;

class MahnwesenController extends Controller
{
    public function index(Request $request, DunningService $service)
    {
        $data = $service->overview([
            'status'=>$request->get('status','overdue'),
            'stage'=>$request->get('stage'),
            'q'=>$request->get('q'),
        ]);
        $mode = session('frontend_mode','classic');
        return view($mode.'.mahnwesen.index',$data);
    }

    public function letter(Request $request, int $rechnung, DunningLetterService $letters)
    {
        $data = $request->validate([
            'stage'=>['required','integer','between:1,3'],
            'fee'=>['nullable','numeric','min:0','max:1000'],
        ]);
        try {
            $pdf = $letters->render($rechnung,(int)$data['stage'],(float)($data['fee'] ?? 0));
        } catch (RuntimeException $e) {
            return response($e->getMessage(),422,['Content-Type'=>'text/plain; charset=UTF-8']);
        }
        return response($pdf,200,[
            'Content-Type'=>'application/pdf',
            'Content-Disposition'=>'inline; filename="Mahnung-Vorschau-'.$rechnung.'.pdf"',
            'Cache-Control'=>'no-store, no-cache, must-revalidate',
        ]);
    }

    public function reminder(Request $request, int $rechnung, DunningWriteService $writes)
    {
        $data=$request->validate([
            'stage'=>['required','integer','between:1,3'],
            'fee'=>['nullable','numeric','min:0','max:1000'],
        ]);
        try {
            $writes->applyReminder($rechnung,(int)$data['stage'],(float)($data['fee'] ?? 0),$this->actor($request));
            return back()->with('status','Mahnstufe '.$data['stage'].' wurde gebucht.');
        } catch (RuntimeException $e) {
            return back()->withErrors(['dunning'=>$e->getMessage()]);
        }
    }

    public function dispute(Request $request, int $rechnung, DunningWriteService $writes)
    {
        $data=$request->validate([
            'reason'=>['required','string','max:1000'],
            'followup'=>['required','date'],
        ]);
        try {
            $writes->markDisputed($rechnung,$data['reason'],CarbonImmutable::parse($data['followup']),$this->actor($request));
            return back()->with('status','Rechnung wurde als strittig markiert.');
        } catch (RuntimeException $e) {
            return back()->withErrors(['dunning'=>$e->getMessage()]);
        }
    }

    public function clearDispute(Request $request, int $rechnung, DunningWriteService $writes)
    {
        try {
            $writes->clearDisputed($rechnung,$this->actor($request));
            return back()->with('status','Strittig-Markierung wurde aufgehoben.');
        } catch (RuntimeException $e) {
            return back()->withErrors(['dunning'=>$e->getMessage()]);
        }
    }

    public function lock(Request $request, int $kunde, DunningWriteService $writes)
    {
        try {
            $count=$writes->lockCustomer($kunde,$this->actor($request));
            return back()->with('status',"Kundensperre wurde für {$count} offene Rechnung(en) gesetzt.");
        } catch (RuntimeException $e) {
            return back()->withErrors(['dunning'=>$e->getMessage()]);
        }
    }

    public function unlock(Request $request, int $kunde, DunningWriteService $writes)
    {
        try {
            $count=$writes->unlockCustomer($kunde,$this->actor($request));
            return back()->with('status',"Kundensperre wurde für {$count} offene Rechnung(en) aufgehoben.");
        } catch (RuntimeException $e) {
            return back()->withErrors(['dunning'=>$e->getMessage()]);
        }
    }

    private function actor(Request $request): string
    {
        return (string)($request->attributes->get('ad_username') ?: $request->header('X-Remote-User') ?: 'unknown');
    }
}
